<?php

namespace App\Command;

use App\Entity\Document;
use App\Repository\MarcheBeRepository;
use App\Repository\Theme;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Tool\SimilaritySearch;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Store\Bridge\Postgres\Store;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:rag',
    description: 'Rag the website',
)]
class RagCommand extends Command
{
    private SymfonyStyle $io;
    private OutputInterface $output;
    private Store $store;
    private Platform $platform;
    private Vectorizer $vectorizer;
    private array $documents = [];

    public function __construct(
        #[Autowire('%env(DATABASE_URL_RAG)%')]
        private readonly string $dsn,
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private readonly string $apiKey,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Manage server db');
        $this->addOption('index', "index", InputOption::VALUE_NONE, 'index documents');
        $this->addOption('query', "query", InputOption::VALUE_NONE, 'query documents');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;

        $index = (bool)$input->getOption('index');
        $query = (bool)$input->getOption('query');

        // initialize the store
        $this->store = Store::fromDbal(
            connection: DriverManager::getConnection((new DsnParser())->parse($this->dsn)),
            tableName: 'my_table',
        );

        // initialize the table
        $this->store->setup();

        // create embeddings for documents
        $this->platform = PlatformFactory::create($this->apiKey, $this->http_client($this->output));
        $this->vectorizer = new Vectorizer($this->platform, 'text-embedding-3-small', $this->logger($this->output));

        if ($index) {
            $this->indexDocuments();

            return Command::SUCCESS;
        }

        if ($query) {
            $this->query();

            return Command::SUCCESS;
        }

        $this->io->error('Please specify an action. --index or --query');

        return Command::FAILURE;
    }

    private function indexDocuments(): void
    {
        // create embeddings and documents
        $documents = [];
        $this->getAllPosts();
        foreach ($this->documents as $post) {
            $documents[] = new TextDocument(
                id: Uuid::v4(),
                content: 'Title: '.$post->title.\PHP_EOL.'Site: '.$post->siteName.\PHP_EOL.'Description: '.$post->content,
                metadata: new Metadata($post->toArray()),
            );
        }

        $indexer = new Indexer(
            new InMemoryLoader($documents), $this->vectorizer, $this->store, logger: $this->logger($this->output)
        );
        $indexer->index($documents);
    }

    private function query(): void
    {
        $similaritySearch = new SimilaritySearch($this->vectorizer, $this->store);
        $toolbox = new Toolbox([$similaritySearch], logger: $this->logger($this->output));
        $processor = new AgentProcessor($toolbox);
        $agent = new Agent($this->platform, 'gpt-4o-mini', [$processor], [$processor]);

        $messages = new MessageBag(
            Message::forSystem('Please answer all user questions only using SimilaritySearch function.'),
            Message::ofUser('Donne moi une friterie à Marloie')
        );
        try {
            $result = $agent->call($messages);
            $this->io->writeln($result->getContent());
        } catch (ExceptionInterface $e) {
            $this->io->error($e->getMessage());
        }

    }

    private function getAllPosts(): void
    {
        $repository = new MarcheBeRepository();
        foreach (Theme::getSites() as $siteName) {
            $posts = $repository->getPosts($siteName);
            foreach ($posts as $post) {
                $post->categories = $repository->getCategoriesByPost($siteName, $post->id);
                $document = Document::createFromPost($post, $siteName);
                if ($this->validateDocument($document->content)) {
                    $this->documents[] = $document;
                }
            }
            $posts = $repository->getPosts(2);
            foreach ($posts as $post) {
                $post->categories = $repository->getCategoriesByPost($siteName, $post->id);
                $document = Document::createFromPost($post, $siteName);
                if ($this->validateDocument($document->content)) {
                    $this->documents[] = $document;
                }
            }
        }
    }

    private function validateDocument(?string $content): bool
    {
        $content = trim($content);
        if (empty($content)) {
            return false;
        }
        $maxChars = 30000;
        if (strlen($content) > $maxChars) {
            return false;
        }

        return true;
    }

    function http_client(OutputInterface $output): HttpClientInterface
    {
        $httpClient = HttpClient::create();

        if ($httpClient instanceof LoggerAwareInterface) {
            $httpClient->setLogger($this->logger($output));
        }

        return $httpClient;
    }


    function logger(OutputInterface $output): LoggerInterface
    {
        return new class($output) extends ConsoleLogger {
            private ConsoleOutput $output;

            public function __construct(ConsoleOutput $output)
            {
                parent::__construct($output);
                $this->output = $output;
            }

            /**
             * @param Stringable|string $message
             */
            public function log($level, $message, array $context = []): void
            {
                // Call parent to handle the base logging
                parent::log($level, $message, $context);

                // Add context display for debug verbosity
                if ($this->output->getVerbosity() >= ConsoleOutput::VERBOSITY_DEBUG && [] !== $context) {
                    // Filter out special keys that are already handled
                    $displayContext = array_filter($context, function ($key) {
                        return !in_array($key, ['exception', 'error', 'object'], true);
                    }, \ARRAY_FILTER_USE_KEY);

                    if ([] !== $displayContext) {
                        $contextMessage = '  '.json_encode(
                                $displayContext,
                                \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
                            );
                        $this->output->writeln(sprintf('<comment>%s</comment>', $contextMessage));
                    }
                }
            }
        };
    }

}

<?php

namespace App\Command;

use App\Entity\Document;
use App\OpenAI\Client;
use App\Repository\BottinRepository;
use App\Repository\DocumentRepository;
use App\Repository\MarcheBeRepository;
use App\Repository\PivotRepository;
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
        private readonly Client $client,
        private readonly BottinRepository $bottinRepository,
        private readonly MarcheBeRepository $marcheBeRepository,
        private readonly PivotRepository $pivotRepository,
        private readonly DocumentRepository $documentRepository,
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
        $this->documents = [
            ...$this->marcheBeRepository->getAllPosts(),
            ...$this->bottinRepository->getBottin(),
            ...$this->pivotRepository->getEvents(),
        ];

        /**
         * @var TextDocument[] $textDocuments
         */
        $textDocuments = [];
        foreach ($this->documents as $document) {
            if ($this->validateDocument($document) === true) {
                $textDocuments[] = new TextDocument(
                    id: Uuid::v4(),
                    content: 'Title: '.$document->title.\PHP_EOL.'Site: '.$document->siteName.\PHP_EOL.'Description: '.$document->content,
                    metadata: new Metadata($document->toArray()),
                );
            }
        }

        // Vectorize documents first to get embeddings
        $vectorDocuments = [];
        foreach ($textDocuments as $textDoc) {
            $vectorDocuments[] = $this->vectorizer->vectorize($textDoc);
        }

        // Index with Symfony AI (stores in my_table)
        $indexer = new Indexer(
            new InMemoryLoader($textDocuments), $this->vectorizer, $this->store, logger: $this->logger($this->output)
        );
        $indexer->index($textDocuments);

        // Now save to your custom document table with embeddings
        foreach ($vectorDocuments as $index => $vectorDoc) {
            // Get the embedding vector from the vectorized document
            $embedding = $vectorDoc->vector->getData();

            // Set embeddings on your custom Document entity
            $this->documents[$index]->setEmbeddings($embedding);

            // Persist your custom entity
            $this->documentRepository->persist($this->documents[$index]);
        }

        $this->documentRepository->flush();
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

    private function validateDocument(Document $document): bool
    {
        $content = $document->content;
        $content = trim($content);
        if (empty($content)) {
            return false;
        }
        $maxChars = 30000;
        if (strlen($content) > $maxChars) {
            $this->io->error(sprintf('Document content is too long. %s', $document->title));

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

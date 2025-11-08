<?php

namespace App\Command;

use App\Entity\Document;
use App\Repository\MarcheBeRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
use Symfony\AI\Store\Bridge\Postgres\Store;
use Symfony\AI\Store\Document\Loader\InMemoryLoader;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\Indexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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

    public function __construct(
        #[Autowire('%env(DATABASE_URL_RAG)%')]
        private readonly string $dsn,
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private readonly string $apiKey,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        // initialize the store
        $store = Store::fromDbal(
            connection: DriverManager::getConnection((new DsnParser())->parse($this->dsn)),
            tableName: 'my_table',
        );

        // create embeddings and documents
        $documents = [];
        foreach ($this->getAllPosts() as $i => $post) {
            $documents[] = new TextDocument(
                id: Uuid::v4(),
                content: 'Title: '.$post->title.\PHP_EOL.'Site: '.$post->siteName.\PHP_EOL.'Description: '.$post->content,
                metadata: new Metadata($post->toArray()),
            );
        }

        // initialize the table
        $store->setup();

        // create embeddings for documents
        $platform = PlatformFactory::create($this->apiKey, $this->http_client($output));
        $vectorizer = new Vectorizer($platform, 'text-embedding-3-small', $this->logger($output));
        $indexer = new Indexer(new InMemoryLoader($documents), $vectorizer, $store, logger: $this->logger($output));
        $indexer->index($documents);

        return Command::SUCCESS;
    }

    /**
     * @return array<Document>
     */
    private function getAllPosts(): array
    {
        $repository = new MarcheBeRepository();
        $documents = [];

        $siteName = 'citoyen';
        $posts = $repository->getPosts($siteName);
        foreach ($posts as $post) {
            if ($this->checkSize($post->content)) {
                $documents[] = Document::createFromPost($post, $siteName);
            }
        }

        return $documents;
    }

    private function checkSize(?string $content): bool
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

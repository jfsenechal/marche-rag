<?php

namespace App\Command;

use App\Entity\Document;
use App\Ocr\Ocr;
use App\OpenAI\Client;
use App\Repository\BottinRepository;
use App\Repository\DocumentRepository;
use App\Repository\MarcheBeRepository;
use App\Repository\PivotRepository;
use App\Repository\TaxeRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:crawl',
    description: 'Crawl the website to extract content',
)]
class CrawlCommand extends Command
{
    /**
     * @var Document[]
     */
    private array $documents = [];
    private SymfonyStyle $io;

    public function __construct(
        private readonly Client $client,
        private readonly BottinRepository $bottinRepository,
        private readonly MarcheBeRepository $marcheBeRepository,
        private readonly PivotRepository $pivotRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly TaxeRepository $taxeRepository,
        private readonly Ocr $ocr,
        #[Autowire(env: 'WP_DIRECTORY')] private readonly string $wpDir,
        #[Autowire(env: 'TAXE_DIRECTORY')] private readonly string $taxeDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Manage server db');
        $this->addOption('post', "post", InputOption::VALUE_NONE, 'index post');
        $this->addOption('bottin', "bottin", InputOption::VALUE_NONE, 'index bottin');
        $this->addOption('event', "event", InputOption::VALUE_NONE, 'index event');
        $this->addOption('attachment', "attachment", InputOption::VALUE_NONE, 'index attachment');
        $this->addOption('taxe', "taxe", InputOption::VALUE_NONE, 'index taxe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->info('Crawling the website.');

        if ($input->getOption('post')) {
            $this->importPosts();
        }
        if ($input->getOption('bottin')) {
            $this->importBottin();
        }
        if ($input->getOption('event')) {
            $this->importEvents();
        }
        if ($input->getOption('attachment')) {
            $this->importAttachments();
        }
        if ($input->getOption('taxe')) {
            $this->importTaxes();
        }

        try {
            $this->documentRepository->flush();
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
        }

        $this->io->success('Finished crawling this website.');

        return Command::SUCCESS;
    }

    private function importPosts(): void
    {
        foreach ($this->marcheBeRepository->getAllPosts() as $document) {
            $this->treatment($document);
        }
    }

    private function importBottin(): void
    {
        foreach ($this->bottinRepository->getBottin() as $document) {
            $this->treatment($document);
        }
    }

    private function importEvents(): void
    {
        foreach ($this->pivotRepository->getEvents() as $document) {
            $this->treatment($document);
        }
    }

    private function importAttachments(): void
    {
        $this->ocr->setBaseDataDirectory($this->wpDir);
        foreach ($this->marcheBeRepository->getAllAttachments() as $document) {
            if (strlen($document->content) > 100) {
                $this->treatment($document);
                continue;
            }

            $filePath = $this->ocr->resolvePathForWpPost($document);
            if ($this->ocr->fileExists($filePath)) {
                $ocrFilePath = $this->ocr->getOcrOutputPath($filePath);
                if ($this->ocr->fileExists($ocrFilePath)) {
                    $document->content = trim(file_get_contents($ocrFilePath));
                    $this->documentRepository->flush();
                    if ($document->content) {
                        $this->treatment($document);
                    }
                }
            }
        }
    }

    private function importTaxes(): void
    {
        $this->ocr->setBaseDataDirectory($this->taxeDir);
        foreach ($this->taxeRepository->getAllTaxes() as $document) {
            $this->treatment($document);
        }
    }

    private function treatment(Document $document): void
    {
        if ($this->documentRepository->findByReferenceId($document->referenceId)) {
            return;
        }
        try {
            $content = "$document->title $document->typeOf $document->content";
            $embeddings = $this->client->getEmbeddings($content, $document);
            $document->setEmbeddings($embeddings);
            $this->documentRepository->persist($document);
        } catch (\InvalidArgumentException|\Exception|InvalidArgumentException $e) {
            $this->io->warning(sprintf('Skipping document "%s": %s', $document->title, $e->getMessage()));
        }
    }
}

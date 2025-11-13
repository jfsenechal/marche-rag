<?php

namespace App\Command;

use App\Entity\Document;
use App\Ocr\Ocr;
use App\OpenAI\Client;
use App\Repository\BottinRepository;
use App\Repository\DocumentRepository;
use App\Repository\MarcheBeRepository;
use App\Repository\PivotRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        private readonly Ocr $ocr
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->info('Crawling the website.');

        $this->documents = [
            ...$this->marcheBeRepository->getAllPosts(),
            ...$this->bottinRepository->getBottin(),
            ...$this->pivotRepository->getEvents(),
        ];

        $this->io->note(sprintf('Found %d documents.', \count($this->documents)));

        $this->io->info('Extracting embeddings.');

        $i = 0;
        foreach ($this->documents as $document) {
            $this->treatment($document);
            $i++;
            if ($i > 30) {
                try {
                    $this->documentRepository->flush();
                } catch (\Exception $e) {
                    $this->io->error($e->getMessage());
                }
                $this->io->note(sprintf('Flush %d documents.', $i));
                $i = 0;
            }
        }

        $this->io->info('Extracting attachments.');
        foreach ($this->marcheBeRepository->getAllAttachments() as $document) {
            if (strlen($document->content) > 100) {
                $this->treatment($document);
                continue;
            }
            $filePath = $this->ocr->resolveAttachmentPath($document);
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

        try {
            $this->documentRepository->flush();
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
        }

        $this->io->success('Finished crawling this website.');

        return Command::SUCCESS;
    }

    private function treatment(Document $document): void
    {
        if ($this->documentRepository->findByReferenceId($document->referenceId)) {
            return;
        }
        try {
            $content = "$document->title $document->typeOf $document->content";
            $embeddings = $this->client->getEmbeddings($content);
            $document->setEmbeddings($embeddings);
            $this->documentRepository->persist($document);
        } catch (\InvalidArgumentException|\Exception|InvalidArgumentException $e) {
            $this->io->warning(sprintf('Skipping document "%s": %s', $document->title, $e->getMessage()));
        }
    }
}

<?php

namespace App\Command;

use App\Entity\Document;
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

    public function __construct(
        private readonly Client $client,
        private readonly BottinRepository $bottinRepository,
        private readonly MarcheBeRepository $marcheBeRepository,
        private readonly PivotRepository $pivotRepository,
        private readonly DocumentRepository $documentRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Crawling the website.');

        $this->documents = [
            ...$this->marcheBeRepository->getAllPosts(),
            ...$this->bottinRepository->getBottin(),
            ...$this->pivotRepository->getEvents(),
        ];

        $io->note(sprintf('Found %d documents.', \count($this->documents)));

        $io->info('Extracting embeddings.');

        $validDocuments = [];
        $i = 0;
        foreach ($this->documents as $document) {
            if ($this->documentRepository->findByReferenceId($document->referenceId)) {
                continue;
            }
            try {
                $content = "$document->title $document->typeOf $document->content";
                $embeddings = $this->client->getEmbeddings($content);
                $document->setEmbeddings($embeddings);
                $validDocuments[] = $document;
                $this->documentRepository->persist($document);
            } catch (\InvalidArgumentException $e) {
                $io->warning(sprintf('Skipping document "%s": %s', $document->title, $e->getMessage()));
            } catch (InvalidArgumentException $e) {
                $io->warning(sprintf('Skipping document "%s": %s', $document->title, $e->getMessage()));
            }
            if ($i > 30) {
                try {
                    $this->documentRepository->flush();
                } catch (\Exception $e) {
                    $io->error($e->getMessage());
                }
                $io->note(sprintf('Flush %d documents.', \count($validDocuments)));
                $i = 0;
            }
            $i++;
        }

        $io->note(sprintf('Found %d valid documents.', \count($validDocuments)));

        try {
            $this->documentRepository->flush();
        } catch (\Exception $e) {
            $io->error($e->getMessage());
        }

        $io->success('Finished crawling this website.');

        return Command::SUCCESS;
    }
}

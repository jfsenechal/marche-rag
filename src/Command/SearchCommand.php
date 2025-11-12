<?php

namespace App\Command;

use App\OpenAI\Client;
use App\Repository\DocumentRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search',
    description: 'Searching',
)]
class SearchCommand extends Command
{
    private SymfonyStyle $io;
    private OutputInterface $output;

    public function __construct(
        private readonly Client $client,
        private readonly DocumentRepository $documentRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('query', InputArgument::REQUIRED, 'The search query');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;

        $query = (string)$input->getArgument('query');

        if ($query) {

            $this->io->info('Searching documents: '.$query);
            try {
                $embeddings = $this->client->getEmbeddings($query);
                $documents = $this->documentRepository->findNearest($embeddings);
                $this->io->success(sprintf('Found %d documents.', \count($documents)));
                foreach ($documents as $document) {
                    $this->io->writeln($document->title);
                }
            } catch (\Exception|InvalidArgumentException$e) {
                $this->io->error($e->getMessage());
            }
        }

        $this->io->success('Finished');

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use App\Repository\DiscussionRepository;
use App\Repository\DocumentRepository;
use App\Repository\MessageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db',
    description: 'Crawl the website to extract content',
)]
class DbCommand extends Command
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly MessageRepository $messageRepository,
        private readonly DiscussionRepository $discussionRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Manage server db');
        $this->addOption('reset', "reset", InputOption::VALUE_NONE, 'Remove discussions and messages');
        $this->addOption('with-docs', "with-docs", InputOption::VALUE_NONE, 'Remove documents');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $reset = (bool)$input->getOption('reset');
        $docs = (bool)$input->getOption('with-docs');

        if ($reset) {
            foreach ($this->discussionRepository->findAll() as $discussion) {
                $this->messageRepository->removeAllByDiscussion($discussion);
                $this->discussionRepository->remove($discussion);
            }
            $this->discussionRepository->flush();
            if ($docs) {
                $this->documentRepository->removeAll();
                $this->documentRepository->flush();
            }
            $io->success('Finished reset db.');
        }

        return Command::SUCCESS;

    }
}

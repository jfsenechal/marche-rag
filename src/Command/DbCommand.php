<?php

namespace App\Command;

use App\Repository\DiscussionRepository;
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
        private readonly MessageRepository $messageRepository,
        private readonly DiscussionRepository $discussionRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Manage server db');
        $this->addOption('reset', "reset", InputOption::VALUE_NONE, 'Search engine reset');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $reset = (bool)$input->getOption('reset');

        if ($reset) {
            foreach ($this->discussionRepository->findAll() as $discussion) {
                $this->messageRepository->removeAllByDiscussion($discussion);
                $this->discussionRepository->remove($discussion);
            }
            $this->discussionRepository->flush();
            $io->success('Finished reset db.');
        }

        return Command::SUCCESS;

    }
}
<?php

namespace App\Command;

use App\Ocr\Ocr;
use App\Repository\MarcheBeRepository;
use App\Repository\Theme;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ocr',
    description: 'Extract text from pdf files',
)]
class OcrCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly MarcheBeRepository $marcheBeRepository,
        private readonly Ocr $ocr,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('filePath', InputArgument::OPTIONAL, 'The file path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $filePath = (string)$input->getArgument('filePath');

        foreach (Theme::getSites() as $siteName) {
            foreach ($this->marcheBeRepository->getAttachments($siteName) as $attachment) {
                $this->io->writeln('Extracting pdf: '.$attachment->guid->rendered);
                $filePath = $this->ocr->getAbsolutePathFromAttachment($attachment);
                if ($filePath) {
                    $this->io->writeln($filePath);
                } else {
                    dd($filePath);
                }
                if ($this->ocr->fileExists($filePath)) {
                    $this->io->info('convert ');
                    try {
                        $this->ocr->convertToImages($filePath);
                        $this->ocr->convertToTxt($filePath);
                    } catch (\Exception$e) {
                        $this->io->error($e->getMessage());
                    }
                    $this->io->success('Finished');
                }
            }
        }

        $this->io->success('Finished');

        return Command::SUCCESS;
    }
}

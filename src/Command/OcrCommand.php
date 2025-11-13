<?php

namespace App\Command;

use App\Ocr\Ocr;
use App\Repository\MarcheBeRepository;
use App\Repository\Theme;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->addOption('extract', 'extract', InputOption::VALUE_NONE, 'Extract text from pdf files');
        $this->addOption('check', 'check', InputOption::VALUE_NONE, 'Check ocr file exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $filePath = (string)$input->getArgument('filePath');
        $check = (bool)$input->getOption('check');
        $extract = (bool)$input->getOption('extract');

        if ($extract) {
            $this->extractText();
            $this->io->success('Finished');

            return Command::SUCCESS;
        }

        if ($check) {
            $this->checkOcrFileExist();
            $this->io->success('Finished');

            return Command::SUCCESS;
        }

        $this->io->error('Please specify an action. --extract or --check');

        return Command::FAILURE;
    }

    private function extractText(): void
    {
        foreach (Theme::getSites() as $siteName) {
            foreach ($this->marcheBeRepository->getAttachments($siteName) as $attachment) {
                $this->io->title('Extracting pdf: '.$attachment->source_url);
                $filePath = $this->ocr->resolveAttachmentPath($attachment);
                $this->io->writeln("Full path: ".$filePath);

                if ($this->ocr->fileExists($filePath)) {
                    $ocrFilePath = $this->ocr->getOcrOutputPath($filePath);
                    if (!$this->ocr->fileExists($ocrFilePath)) {
                        try {
                            $this->io->writeln("Directory: ".$this->ocr->getTempDirectoryForFile($filePath));
                            $this->ocr->convertPdfToImages($filePath);
                            $this->ocr->extractTextFromImages($filePath);
                            $this->io->writeln("OcrFile: ".$ocrFilePath);
                        } catch (\Exception$e) {
                            $this->io->error($e->getMessage());
                        }
                    }
                } else {
                    $this->io->error('File not found');
                }
            }
        }
    }

    private function checkOcrFileExist(): void
    {
        foreach (Theme::getSites() as $siteName) {
            foreach ($this->marcheBeRepository->getAttachments($siteName) as $attachment) {
                $filePath = $this->ocr->resolveAttachmentPath($attachment);
                if (!$this->ocr->fileExists($filePath)) {
                    $this->io->writeln("File not found: ".$filePath);
                } else {
                    $ocrFilePath = $this->ocr->getOcrOutputPath($filePath);
                    if (!$this->ocr->fileExists($ocrFilePath)) {
                        $this->io->writeln("Ocr file not found: ".$filePath);
                    }
                }
            }
        }
    }
}

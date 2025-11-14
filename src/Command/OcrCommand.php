<?php

namespace App\Command;

use App\Entity\Document;
use App\Ocr\Ocr;
use App\Repository\MarcheBeRepository;
use App\Repository\TaxeRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:ocr',
    description: 'Extract text from pdf files',
)]
class OcrCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly MarcheBeRepository $marcheBeRepository,
        private readonly TaxeRepository $taxeRepository,
        private readonly Ocr $ocr,
        #[Autowire(env: 'WP_DIRECTORY')] private readonly string $wpDir,
        #[Autowire(env: 'TAXE_DIRECTORY')] private readonly string $taxeDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('type', InputArgument::REQUIRED, 'The type of document');
        $this->addOption('extract', 'extract', InputOption::VALUE_NONE, 'Extract text from pdf files');
        $this->addOption('check', 'check', InputOption::VALUE_NONE, 'Check ocr file exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $type = (string)$input->getArgument('type');
        $check = (bool)$input->getOption('check');
        $extract = (bool)$input->getOption('extract');

        if ($extract) {
            $documents = [];
            if ($type === 'attachment') {
                $documents = $this->marcheBeRepository->getAllAttachments();
            }
            if ($type === 'taxe') {
                $documents = $this->taxeRepository->getAllTaxes();
            }
            $this->extractText($documents, $type);
            $this->io->success('Finished');

            return Command::SUCCESS;
        }

        if ($check) {
            $documents = [];
            if ($type === 'attachment') {
                $documents = $this->marcheBeRepository->getAllAttachments();
            }
            if ($type === 'taxe') {
                $documents = $this->taxeRepository->getAllTaxes();
            }
            $this->checkOcrFileExist($documents, $type);
            $this->io->success('Finished');

            return Command::SUCCESS;
        }

        $this->io->error('Please specify an action. --extract or --check');

        return Command::FAILURE;
    }

    /**
     * @param array<Document> $documents
     * @return void
     */
    private function extractText(array $documents, string $type): void
    {
        if ($type === 'attachment') {
            $this->ocr->setBaseDataDirectory($this->wpDir);
        }
        if ($type === 'taxe') {
            $this->ocr->setBaseDataDirectory($this->wpDir);
        }
        foreach ($documents as $document) {
            if ($document->typeOf === 'attachment') {
                $filePath = $this->ocr->resolvePathForWpPost($document);
            }
            if ($document->typeOf === 'taxe') {
                $filePath = $this->taxeDir.'/'.$document->fileName;
            }
            if ($this->ocr->fileExists($filePath)) {
                $ocrFilePath = $this->ocr->getOcrOutputPath($filePath);
                if (!$this->ocr->fileExists($ocrFilePath)) {
                    $this->io->title('Extracting pdf: '.$document->source_url);
                    $this->io->writeln("Full path: ".$filePath);
                    try {
                        $this->io->writeln("Directory: ".$this->ocr->getWorkingDirectory($filePath));
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

    /**
     * @param array<Document> $documents
     * @param string $type
     * @return void
     */
    private function checkOcrFileExist(array $documents, string $type): void
    {
        if ($type === 'attachment') {
            $this->ocr->setBaseDataDirectory($this->wpDir);
        }
        if ($type === 'taxe') {
            $this->ocr->setBaseDataDirectory($this->wpDir);
        }
        foreach ($documents as $document) {
            if ($document->typeOf === 'attachment') {
                $filePath = $this->ocr->resolvePathForWpPost($document);
            }
            if ($document->typeOf === 'taxe') {
                $filePath = $this->taxeDir.'/'.$document->fileName;
            }
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

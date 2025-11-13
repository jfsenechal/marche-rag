<?php

namespace App\Ocr;

use App\Entity\Document;
use App\Repository\Theme;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
  //'/var/www/marchebe/wp-content//blogs.dir/11/files/2015/01/Infos-pratiques-et-formulaire-dinscription.doc'
class Ocr
{
    private const OCR_FILENAME = 'ocr.txt';
    public Filesystem $filesystem;
    public string $baseDataDirectory;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire(env: 'TMP_DIRECTORY')] private readonly string $tmpDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * The base path where the PDF files are store
     * @param string $directory
     * @return void
     */
    public function setBaseDataDirectory(string $directory): void
    {
        $this->baseDataDirectory = $directory;
    }

    /**
     * Where the PDF file is extracting and ocr file store
     * @param string $filePath
     * @return string
     */
    public function getWorkingDirectory(string $filePath): string
    {
        return dirname(str_replace($this->baseDataDirectory, $this->projectDir.$this->tmpDir, $filePath));
    }

    /**
     * @param string $filePath
     * @return void
     */
    public function convertPdfToImages(string $filePath): void
    {
        $tmpDirectory = $this->getWorkingDirectory($filePath);
        // Create the directory if it doesn't exist
        $this->filesystem->mkdir($tmpDirectory);

        $process = new Process([
            'pdftoppm',
            '-png',
            $filePath,
            $tmpDirectory.'/img-ocr',
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * @param string $filePath
     * @return void
     */
    public function extractTextFromImages(string $filePath): void
    {
        $workingDirectory = $this->getWorkingDirectory($filePath);
        $files = scandir($workingDirectory);
        $files = array_filter($files, function ($file) use ($workingDirectory) {
            return (str_contains($file, 'img-ocr'));
        });

        $i = 1;
        foreach ($files as $item) {
            $imagePath = Path::makeAbsolute($item, $workingDirectory);
            $outputPath = $workingDirectory.'/text-'.$i;

            $process = new Process([
                'tesseract',
                $imagePath,
                $outputPath,
                '--oem',
                '1',
                '--psm',
                '3',
                '-l',
                'fra',
                'logfile',
            ]);

            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $i++;
        }

        // Merge all text files into one OCR output file
        $ocrFile = $this->getOcrOutputPath($filePath);
        $process = new Process([
            'sh',
            '-c',
            "cat $workingDirectory/text-* > $ocrFile",
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function getOcrOutputPath(string $filePath): string
    {
        $workingDirectory = $this->getWorkingDirectory($filePath);

        return $workingDirectory.DIRECTORY_SEPARATOR.self::OCR_FILENAME;
    }

    public function resolveAttachmentPath(Document $document): ?string
    {
        $guid = $document->source_url;

        // Remove https://www.marche.be from the URL
        $path = str_replace('https://www.marche.be', '', $guid);

        if (str_contains($guid, 'uploads')) {
            return $this->baseDataDirectory.$path;
        }

        // Extract the first segment (theme name like 'sante', 'sport', etc.)
        $pathParts = explode('/', trim($path, '/'));
        $themeName = $pathParts[0] ?? null;

        if ($themeName) {
            $pathParts[0] = 'blogs.dir/'.Theme::getSiteIdByName($themeName);
            $path = '/'.implode('/', $pathParts);

            return $this->baseDataDirectory.'/wp-content/'.$path;
        }

        return null;
    }

    public function fileExists(string $filePath): bool
    {
        return is_readable($filePath) && is_file($filePath);
    }

    /**
     * @param string $filePath
     * @return void
     */
    public function cleanupTempDirectory(string $filePath): void
    {
        $workingDirectory = $this->getWorkingDirectory($filePath);
        $files = scandir($workingDirectory);
        // Filter out the '.' and '..' entries
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            $filePath = Path::makeAbsolute($file, $workingDirectory);
            if (is_file($filePath)) {
                $this->filesystem->remove($filePath);
            }
        }
    }
}

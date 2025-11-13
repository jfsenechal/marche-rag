<?php

namespace App\Ocr;

use App\Entity\Document;
use App\Repository\Theme;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Ocr
{
    private const OCR_FILENAME = 'ocr.txt';

    public Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire(env: 'WP_DIRECTORY')] private readonly string $wpDir,
        #[Autowire(env: 'TMP_DIRECTORY')] private readonly string $tmpDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function getTempDirectoryForFile(string $filePath): string
    {
        return dirname(str_replace($this->wpDir, $this->projectDir.$this->tmpDir, $filePath));
    }


    /**
     * @param string $filePath
     * @return void
     */
    public function convertPdfToImages(string $filePath): void
    {
        $tmpDirectory = $this->getTempDirectoryForFile($filePath);
        // Create the directory if it doesn't exist
        $this->filesystem->mkdir($tmpDirectory);

        $process = new Process([
            'pdftoppm',
            '-png',
            $filePath,
            $tmpDirectory . '/img-ocr'
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
        $tmpDirectory = $this->getTempDirectoryForFile($filePath);
        $files = scandir($tmpDirectory);
        $files = array_filter($files, function ($file) use ($tmpDirectory) {
            return (str_contains($file, 'img-ocr'));
        });

        $i = 1;
        foreach ($files as $item) {
            $imagePath = Path::makeAbsolute($item, $tmpDirectory);
            $outputPath = $tmpDirectory . '/text-' . $i;

            $process = new Process([
                'tesseract',
                $imagePath,
                $outputPath,
                '--oem', '1',
                '--psm', '3',
                '-l', 'fra',
                'logfile'
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
            "cat $tmpDirectory/text-* > $ocrFile"
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function getOcrOutputPath(string $filePath): string
    {
        $tmpDirectory = $this->getTempDirectoryForFile($filePath);

        return $tmpDirectory.DIRECTORY_SEPARATOR.self::OCR_FILENAME;
    }

    public function resolveAttachmentPath(Document $document): ?string
    {
        $guid = $document->source_url;

        // Remove https://www.marche.be from the URL
        $path = str_replace('https://www.marche.be', '', $guid);

        if (str_contains($guid, 'uploads')) {
            return $this->wpDir.$path;
        }

        // Extract the first segment (theme name like 'sante', 'sport', etc.)
        $pathParts = explode('/', trim($path, '/'));
        $themeName = $pathParts[0] ?? null;

        if ($themeName) {
            $pathParts[0] = 'blogs.dir/'.Theme::getSiteIdByName($themeName);
            $path = '/'.implode('/', $pathParts);

            return $this->wpDir.'/wp-content/'.$path;
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
        $tmpDirectory = $this->getTempDirectoryForFile($filePath);
        $files = scandir($tmpDirectory);
        // Filter out the '.' and '..' entries
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            $filePath = Path::makeAbsolute($file, $tmpDirectory);
            if (is_file($filePath)) {
                $this->filesystem->remove($filePath);
            }
        }
    }
}

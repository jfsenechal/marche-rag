<?php

namespace App\Ocr;

use App\Repository\Theme;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Ocr
{
    public Filesystem $filesystem;
    public static $ocrFilename = 'ocr.txt';
    public string $department;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire(env: 'WP_DIRECTORY')] private readonly string $wpDir,
        #[Autowire(env: 'TMP_DIRECTORY')] private readonly string $tmpDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function getTemporaryDirectory(string $filePath): string
    {
        return dirname(str_replace($this->wpDir, $this->projectDir.$this->tmpDir, $filePath));
    }


    public function convertToImages(string $filePath): void
    {
        $tmpDirectory = $this->getTemporaryDirectory($filePath);
        // Create the directory if it doesn't exist
        $this->filesystem->mkdir($tmpDirectory);

        shell_exec("pdftoppm -png \"$filePath\" $tmpDirectory/img-ocr");
    }

    public function convertToTxt(string $filePath): void
    {
        $tmpDirectory = $this->getTemporaryDirectory($filePath);
        $files = scandir($tmpDirectory);
        $files = array_filter($files, function ($file) use ($tmpDirectory) {
            return (str_contains($file, 'img-ocr'));
        });

        $i = 1;
        foreach ($files as $item) {
            $filePath = Path::makeAbsolute($item, $tmpDirectory);
            shell_exec("tesseract $filePath $tmpDirectory/text-$i --oem 1 --psm 3 -l fra logfile");
            dd("tesseract $filePath $tmpDirectory/text-$i --oem 1 --psm 3 -l fra logfile");
            $i++;
        }
        //merge files
        $ocrFile = $this->getPathOcr($filePath);
        shell_exec("cat $tmpDirectory/text-* > $ocrFile");
    }

    public function getPathOcr(string $filePath): string
    {
        $tmpDirectory = $this->getTemporaryDirectory($filePath);
        return $tmpDirectory.DIRECTORY_SEPARATOR.$this::$ocrFilename;
    }

    public function getAbsolutePathFromAttachment(\stdClass $attachment): ?string
    {
        $guid = $attachment->source_url;

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
    public function cleanTmpDirectory(string $filePath): void
    {
        $tmpDirectory = $this->getTemporaryDirectory($filePath);
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

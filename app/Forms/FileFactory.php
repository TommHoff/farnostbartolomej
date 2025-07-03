<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Random;
use RuntimeException;

class FileFactory
{
    public function processFile(FileUpload $file, string $baseDir = 'documents'): string
    {
        if (!$file->isOk()) throw new RuntimeException('File upload failed.');
        // Define directories and save the file
        $yearDir = date('Ym');
        $uniqueFolder = Random::generate();
        $fullDirPath = sprintf('%s/%s/%s', $baseDir, $yearDir, $uniqueFolder);
        FileSystem::createDir($fullDirPath);

        $extension = pathinfo($file->getSanitizedName(), PATHINFO_EXTENSION);
        $filename = Random::generate() . '.' . $extension;
        $finalFilePath = $fullDirPath . '/' . $filename;

        // Save the file
        $file->move($finalFilePath);

        return $yearDir . '/' . $uniqueFolder . '/' . $filename;
    }
}

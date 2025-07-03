<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Utils\FileSystem;

class StorageManager
{
    private string $baseDir;

    public function __construct(string $baseDir = 'documents')
    {
        $this->baseDir = $baseDir;
    }

    public function deleteFileOrImage(string $filePath): void
    {
        $fullPath = $this->baseDir . '/' . $filePath;

        // Delete the file
        if (file_exists($fullPath)) {
            unlink($fullPath);

            // Delete the folder if it's empty
            $this->deleteEmptyDirectory(dirname($fullPath));
        }
    }

    private function deleteEmptyDirectory(string $directoryPath): void
    {
        // Check if the directory exists and whether it's a directory
        if (is_dir($directoryPath)) {
            // Check if directory is empty (no valid iterator)
            if (!(new \FilesystemIterator($directoryPath))->valid()) {
                // Attempt to remove it
                if (!@rmdir($directoryPath)) {
                    // Log or handle the warning silently if desired (e.g., permissions issue)
                    // You can log this or skip it completely if no action is necessary
                    return;
                }

                // Optionally delete the parent directory if it's empty
                $parentDirectoryPath = dirname($directoryPath);
                $this->deleteEmptyDirectory($parentDirectoryPath);
            }
        }
    }

	public function fileExists(string $filePath): bool
	{
		$fullPath = $this->baseDir . '/' . $filePath;
		return file_exists($fullPath);
	}
}
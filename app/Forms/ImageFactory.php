<?php

declare(strict_types=1);

namespace App\Forms;

use GdImage;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\ImageException;
use Nette\Utils\Random;
use RuntimeException;

final class ImageFactory
{
    // EXIF Orientation Constants
    private const ORIENTATION_NORMAL = 1;
    private const ORIENTATION_ROTATE_180 = 3;
    private const ORIENTATION_ROTATE_90 = 6;
    private const ORIENTATION_ROTATE_270 = 8;

    // Configuration Properties
    private readonly string $baseDir;
    private readonly int $webpQuality;
    private readonly int $maxWidth;
    private readonly int $maxHeight;
    // Logger removed

    public function __construct(
        string $baseDir = 'documents',
        int $webpQuality = 80,
        int $maxWidth = 1920,
        int $maxHeight = 1920
        // Logger parameter removed
    ) {
        $this->baseDir = rtrim($baseDir, '/');
        $this->webpQuality = max(0, min(100, $webpQuality));
        $this->maxWidth = max(1, $maxWidth);
        $this->maxHeight = max(1, $maxHeight);
        // Logger initialization removed

        if (!extension_loaded('gd')) {
            throw new RuntimeException('PHP GD extension is required but not loaded.');
        }
    }

    /**
     * @throws RuntimeException
     * @throws ImageException
     */
    public function processImage(FileUpload $fileUpload): string
    {
        if (!$fileUpload->isOk()) {
            throw new RuntimeException('File upload failed with error code: ' . $fileUpload->getError());
        }
        if (!$fileUpload->isImage()) {
            throw new RuntimeException('Uploaded file is not a valid image for GD processing.');
        }

        $tempFilePath = $fileUpload->getTemporaryFile();
        if (!$tempFilePath || !is_file($tempFilePath)) {
            throw new RuntimeException('Could not access temporary upload file.');
        }

        $relativePath = $this->generateRelativePath();
        $targetDir = dirname($this->baseDir . '/' . $relativePath);
        $finalFilePath = $this->baseDir . '/' . $relativePath;

        try {
            FileSystem::createDir($targetDir);
        } catch (\Nette\IOException $e) {
            throw new RuntimeException("Storage directory creation failed.", 0, $e);
        }

        $gdResource = null;
        try {
            $image = Image::fromFile($tempFilePath);
            $this->resizeImageIfNeeded($image);
            $gdResource = $image->getImageResource();

            // Correct orientation based ONLY on EXIF
            $gdResource = $this->correctImageOrientation($gdResource, $tempFilePath);

            $currentWidth = imagesx($gdResource);
            $currentHeight = imagesy($gdResource);

            if (!imageistruecolor($gdResource)) {
                $gdResource = $this->convertToTrueColor($gdResource, $currentWidth, $currentHeight);
            }

            $this->saveAsWebp($gdResource, $finalFilePath);
            return $relativePath;

        } catch (ImageException | RuntimeException $e) {
            throw new RuntimeException('Image processing failed: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new RuntimeException('An unexpected error occurred during image processing.', 0, $e);
        } finally {
            $this->destroyGdResource($gdResource);
        }
    }

    private function generateRelativePath(): string
    {
        $yearMonthDir = date('Ym');
        $uniqueFolder = Random::generate(16);
        $filename = Random::generate(12) . '.webp';
        return sprintf('%s/%s/%s', $yearMonthDir, $uniqueFolder, $filename);
    }

    private function resizeImageIfNeeded(Image $image): void
    {
        if ($image->getWidth() > $this->maxWidth || $image->getHeight() > $this->maxHeight) {
            $image->resize($this->maxWidth, $this->maxHeight, Image::FIT);
        }
    }

    /**
     * @param resource|GdImage|false $gdResource
     * @throws RuntimeException
     */
    private function saveAsWebp(GdImage|bool $gdResource, string $finalFilePath): void
    {
        if (!($gdResource instanceof GdImage) && !is_resource($gdResource)) {
            throw new RuntimeException('Invalid GD resource provided for saving.');
        }
        imagealphablending($gdResource, false);
        imagesavealpha($gdResource, true);
        if (!imagewebp($gdResource, $finalFilePath, $this->webpQuality)) {
            if (!is_writable(dirname($finalFilePath))) {
                throw new RuntimeException('Failed to save the image as WebP. Directory not writable.');
            }
            throw new RuntimeException('Failed to save the image as WebP using imagewebp(). Unknown error.');
        }
    }

    /** @param resource|GdImage|false|null $gdResource */
    private function destroyGdResource(GdImage|bool|null $gdResource): void
    {
        if ($gdResource instanceof GdImage || is_resource($gdResource)) {
            @imagedestroy($gdResource);
        }
    }

    /**
     * Corrects image orientation based ONLY on EXIF data.
     * @param resource|GdImage|false $gdResource GD resource or GdImage object
     * @param string $filePath Original file path for EXIF reading
     * @return resource|GdImage|false Potentially rotated GD resource or GdImage object
     */
    private function correctImageOrientation(GdImage|bool $gdResource, string $filePath): GdImage|bool
    {
        if (!($gdResource instanceof GdImage) && !is_resource($gdResource)) return $gdResource;

        // Get rotation angle ONLY from EXIF data
        $rotationAngle = $this->getRotationAngleFromExif($filePath);

        if ($rotationAngle !== null) {
            // Apply rotation if EXIF specified it
            $originalResource = $gdResource;
            $transparentColor = imagecolorallocatealpha($originalResource, 0, 0, 0, 127);
            if ($transparentColor === false) {
                $transparentColor = 0; // Fallback to black background
            }

            $rotatedResource = imagerotate($originalResource, $rotationAngle, $transparentColor);

            if ($rotatedResource !== false) {
                $this->destroyGdResource($originalResource); // Destroy original only on success
                imagealphablending($rotatedResource, false);
                imagesavealpha($rotatedResource, true);
                return $rotatedResource;
            } else {
                // Rotation failed, return original resource
                // Consider logging this failure if logging was enabled
                return $originalResource;
            }
        }

        // No rotation needed based on EXIF (or EXIF missing/normal)
        return $gdResource;
    }

    /** Determines rotation angle ONLY from EXIF data */
    private function getRotationAngleFromExif(string $filePath): ?int
    {
        if (!extension_loaded('exif')) {
            // Cannot read EXIF if extension is missing
            return null;
        }

        $exif = @exif_read_data($filePath); // Suppress warning on failure
        $orientation = ($exif && isset($exif['Orientation'])) ? (int)$exif['Orientation'] : self::ORIENTATION_NORMAL;

        $angle = match ($orientation) {
            self::ORIENTATION_ROTATE_180 => 180,
            self::ORIENTATION_ROTATE_90 => -90, // 90 deg CW
            self::ORIENTATION_ROTATE_270 => 90,  // 270 deg CW
            default => null // Includes ORIENTATION_NORMAL (1) and any other/invalid values
        };

        // NO aspect ratio fallback here

        return $angle;
    }

    /**
     * @param resource|GdImage|false $gdResource
     * @return resource|GdImage|false
     * @throws RuntimeException
     */
    private function convertToTrueColor(GdImage|bool $gdResource, int $width, int $height): GdImage|bool
    {
        if (!($gdResource instanceof GdImage) && !is_resource($gdResource)) return $gdResource;

        $trueColor = imagecreatetruecolor($width, $height);
        if ($trueColor === false) {
            throw new RuntimeException("Failed to create true color image canvas ({$width}x{$height}).");
        }

        imagealphablending($trueColor, false);
        imagesavealpha($trueColor, true);

        $transparentIndex = imagecolortransparent($gdResource);
        if ($transparentIndex >= 0) {
            $rgba = @imagecolorsforindex($gdResource, $transparentIndex);
            if ($rgba) {
                $newTransparentColor = imagecolorallocatealpha(
                    $trueColor, $rgba['red'], $rgba['green'], $rgba['blue'], intval($rgba['alpha'] / 2)
                );
                if ($newTransparentColor !== false) {
                    imagefill($trueColor, 0, 0, $newTransparentColor);
                    imagecolortransparent($trueColor, $newTransparentColor);
                }
            }
        }

        if (!imagecopy($trueColor, $gdResource, 0, 0, 0, 0, $width, $height)) {
            // Log warning differently if desired
        }

        $this->destroyGdResource($gdResource);

        return $trueColor;
    }
}
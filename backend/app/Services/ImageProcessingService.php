<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessingService
{
    protected ImageManager $manager;

    // Max dimensions for main images
    protected int $maxWidth = 1920;
    protected int $maxHeight = 1080;

    // Thumbnail dimensions
    protected int $thumbnailWidth = 300;
    protected int $thumbnailHeight = 300;

    // JPEG quality (0-100)
    protected int $quality = 80;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Process and store an uploaded image with compression
     *
     * @param UploadedFile $file The uploaded file
     * @param string $directory Storage directory path
     * @param bool $createThumbnail Whether to create a thumbnail
     * @return array Image info including paths, dimensions, sizes
     */
    public function processAndStore(
        UploadedFile $file,
        string $directory,
        bool $createThumbnail = true
    ): array {
        $originalSize = $file->getSize();
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        // Generate unique filename
        $baseName = uniqid() . '_' . time();
        $storedName = $baseName . '.jpg'; // Always convert to JPEG for compression
        $thumbnailName = $baseName . '_thumb.jpg';

        // Read and process the image
        $image = $this->manager->read($file->getPathname());

        // Get original dimensions
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Resize if larger than max dimensions (maintain aspect ratio)
        if ($originalWidth > $this->maxWidth || $originalHeight > $this->maxHeight) {
            $image->scaleDown(width: $this->maxWidth, height: $this->maxHeight);
        }

        // Auto-orient based on EXIF data
        $image->orient();

        // Encode as JPEG with quality setting
        $encoded = $image->toJpeg($this->quality);

        // Store the processed image
        $filePath = $directory . '/' . $storedName;
        Storage::disk('public')->put($filePath, $encoded);

        $newSize = Storage::disk('public')->size($filePath);
        $newWidth = $image->width();
        $newHeight = $image->height();

        $result = [
            'original_filename' => $originalName,
            'stored_filename' => $storedName,
            'file_path' => $filePath,
            'thumbnail_path' => null,
            'mime_type' => 'image/jpeg',
            'file_size' => $newSize,
            'original_size' => $originalSize,
            'width' => $newWidth,
            'height' => $newHeight,
        ];

        // Create thumbnail if requested
        if ($createThumbnail) {
            $thumbnail = $this->manager->read($file->getPathname());
            $thumbnail->orient();
            $thumbnail->cover($this->thumbnailWidth, $this->thumbnailHeight);
            $thumbnailEncoded = $thumbnail->toJpeg($this->quality);

            $thumbnailPath = $directory . '/' . $thumbnailName;
            Storage::disk('public')->put($thumbnailPath, $thumbnailEncoded);

            $result['thumbnail_path'] = $thumbnailPath;
        }

        return $result;
    }

    /**
     * Extract EXIF metadata from image
     *
     * @param UploadedFile $file
     * @return array
     */
    public function extractMetadata(UploadedFile $file): array
    {
        $metadata = [
            'latitude' => null,
            'longitude' => null,
            'taken_at' => null,
        ];

        // Only process JPEG/TIFF which support EXIF
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, ['image/jpeg', 'image/tiff'])) {
            return $metadata;
        }

        // Check if EXIF extension is available
        if (!function_exists('exif_read_data')) {
            return $metadata;
        }

        try {
            $exif = @exif_read_data($file->getPathname());

            if ($exif) {
                // Extract GPS coordinates
                if (isset($exif['GPSLatitude'], $exif['GPSLongitude'])) {
                    $lat = $this->getGpsCoordinate(
                        $exif['GPSLatitude'],
                        $exif['GPSLatitudeRef'] ?? 'N'
                    );
                    $lng = $this->getGpsCoordinate(
                        $exif['GPSLongitude'],
                        $exif['GPSLongitudeRef'] ?? 'E'
                    );

                    if ($lat !== null && $lng !== null) {
                        $metadata['latitude'] = $lat;
                        $metadata['longitude'] = $lng;
                    }
                }

                // Extract date taken
                $dateFields = ['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'];
                foreach ($dateFields as $field) {
                    if (isset($exif[$field]) && $exif[$field]) {
                        try {
                            $metadata['taken_at'] = \Carbon\Carbon::createFromFormat(
                                'Y:m:d H:i:s',
                                $exif[$field]
                            );
                            break;
                        } catch (\Exception $e) {
                            // Continue to next field
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // EXIF reading failed, return empty metadata
        }

        return $metadata;
    }

    /**
     * Convert GPS coordinate from EXIF format to decimal
     */
    protected function getGpsCoordinate(array $coordinate, string $hemisphere): ?float
    {
        if (count($coordinate) !== 3) {
            return null;
        }

        $degrees = $this->exifValueToFloat($coordinate[0]);
        $minutes = $this->exifValueToFloat($coordinate[1]);
        $seconds = $this->exifValueToFloat($coordinate[2]);

        if ($degrees === null || $minutes === null || $seconds === null) {
            return null;
        }

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if (in_array($hemisphere, ['S', 'W'])) {
            $decimal *= -1;
        }

        return round($decimal, 7);
    }

    /**
     * Convert EXIF rational value to float
     */
    protected function exifValueToFloat(string $value): ?float
    {
        $parts = explode('/', $value);
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && $parts[1] != 0) {
            return (float) $parts[0] / (float) $parts[1];
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }

    /**
     * Set max dimensions for processed images
     */
    public function setMaxDimensions(int $width, int $height): self
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;
        return $this;
    }

    /**
     * Set JPEG quality
     */
    public function setQuality(int $quality): self
    {
        $this->quality = max(1, min(100, $quality));
        return $this;
    }

    /**
     * Set thumbnail dimensions
     */
    public function setThumbnailDimensions(int $width, int $height): self
    {
        $this->thumbnailWidth = $width;
        $this->thumbnailHeight = $height;
        return $this;
    }
}

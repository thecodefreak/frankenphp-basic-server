<?php

declare(strict_types=1);

namespace App\Content;

use App\Support\Settings;
use GdImage;

final readonly class ImageStore
{
    private const MAX_WIDTH = 1440;
    private const MAX_BYTES = 8_000_000;
    private const MIN_ASPECT = 4 / 5;
    private const MAX_ASPECT = 1.91;

    public function __construct(
        private Settings $settings,
        private string $directory,
    ) {
    }

    /** Normalizes arbitrary provider image bytes into an Instagram-compliant JPEG and saves it. Returns the filename. */
    public function save(string $bytes): string
    {
        $image = @imagecreatefromstring($bytes);
        if (!$image instanceof GdImage) {
            throw new ImageStoreException('Generated image data could not be decoded.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $aspect = $width / $height;

        if ($aspect < self::MIN_ASPECT - 0.01 || $aspect > self::MAX_ASPECT + 0.01) {
            throw new ImageStoreException(sprintf(
                'Generated image has aspect ratio %.2f, outside Instagram\'s allowed 0.80–1.91 range.',
                $aspect
            ));
        }

        if ($width > self::MAX_WIDTH) {
            $newHeight = (int) round(self::MAX_WIDTH / $aspect);
            $resized = imagecreatetruecolor(self::MAX_WIDTH, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, self::MAX_WIDTH, $newHeight, $width, $height);
            $image = $resized;
        }

        // Flatten onto white in case the source had alpha (JPEG has none).
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        $encoded = $this->encodeUnderLimit($flat);

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new ImageStoreException('Unable to create image storage directory.');
        }

        $filename = bin2hex(random_bytes(16)) . '.jpg';
        if (file_put_contents($this->directory . '/' . $filename, $encoded) === false) {
            throw new ImageStoreException('Unable to write generated image to disk.');
        }

        return $filename;
    }

    public function publicUrl(string $filename): string
    {
        return $this->settings->publicBaseUrl() . '/storage/images/' . $filename;
    }

    public function delete(string $filename): void
    {
        $path = $this->directory . '/' . basename($filename);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function encodeUnderLimit(GdImage $image): string
    {
        for ($quality = 90; $quality >= 40; $quality -= 10) {
            ob_start();
            imagejpeg($image, null, $quality);
            $data = (string) ob_get_clean();

            if (strlen($data) <= self::MAX_BYTES) {
                return $data;
            }
        }

        throw new ImageStoreException('Generated image could not be compressed under the 8 MB Instagram limit.');
    }
}

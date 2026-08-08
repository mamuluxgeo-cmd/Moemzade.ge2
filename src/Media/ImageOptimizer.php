<?php

declare(strict_types=1);

namespace Moemzade\Media;

use Imagick;
use RuntimeException;

final class ImageOptimizer
{
    private const VARIANTS = [
        'large' => ['width' => 1600, 'quality' => 85],
        'profile' => ['width' => 720, 'quality' => 84],
        'thumbnail' => ['width' => 360, 'quality' => 82],
    ];

    /** @return list<array{variant:string,path:string,width:int,height:int,bytes:int,mime:string}> */
    public function optimize(string $source, int $maxBytes): array
    {
        if (!is_file($source) || filesize($source) === false || filesize($source) > $maxBytes) {
            throw new RuntimeException('Image is missing or exceeds the upload limit.');
        }

        $info = getimagesize($source);
        if ($info === false || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Only JPEG, PNG and WebP images are supported.');
        }
        if ($info[0] < 100 || $info[1] < 100 || $info[0] > 12000 || $info[1] > 12000) {
            throw new RuntimeException('Image dimensions are not supported.');
        }

        if (class_exists(Imagick::class)) {
            return $this->withImagick($source);
        }
        if (function_exists('imagewebp') && function_exists('imagecopyresampled')) {
            return $this->withGd($source, (string) $info['mime']);
        }

        throw new RuntimeException('Imagick or GD with WebP support is required.');
    }

    /** @return list<array{variant:string,path:string,width:int,height:int,bytes:int,mime:string}> */
    private function withImagick(string $source): array
    {
        $original = new Imagick($source);
        if ($original->getNumberImages() > 1) {
            $original->setIteratorIndex(0);
        }
        if (method_exists($original, 'autoOrientImage')) {
            $original->autoOrientImage();
        }

        $results = [];
        foreach (self::VARIANTS as $variant => $settings) {
            $image = clone $original;
            $targetWidth = min((int) $settings['width'], $image->getImageWidth());
            if ($targetWidth < $image->getImageWidth()) {
                $image->resizeImage($targetWidth, 0, Imagick::FILTER_LANCZOS, 1, true);
            }
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality((int) $settings['quality']);
            $image->stripImage();
            $path = $this->temporaryPath();
            if (!$image->writeImage($path)) {
                throw new RuntimeException('Cannot write optimized WebP image.');
            }
            $results[] = $this->result($variant, $path, $image->getImageWidth(), $image->getImageHeight());
            $image->clear();
        }
        $original->clear();
        return $results;
    }

    /** @return list<array{variant:string,path:string,width:int,height:int,bytes:int,mime:string}> */
    private function withGd(string $source, string $mime): array
    {
        $create = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
            default => null,
        };
        if ($create === null || !function_exists($create)) {
            throw new RuntimeException('The server cannot decode this image type.');
        }
        $original = $create($source);
        if ($original === false) {
            throw new RuntimeException('Cannot decode image.');
        }

        $sourceWidth = imagesx($original);
        $sourceHeight = imagesy($original);
        $results = [];
        foreach (self::VARIANTS as $variant => $settings) {
            $width = min((int) $settings['width'], $sourceWidth);
            $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            imagecopyresampled($canvas, $original, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

            $path = $this->temporaryPath();
            if (!imagewebp($canvas, $path, (int) $settings['quality'])) {
                imagedestroy($canvas);
                throw new RuntimeException('Cannot write optimized WebP image.');
            }
            imagedestroy($canvas);
            $results[] = $this->result($variant, $path, $width, $height);
        }
        imagedestroy($original);
        return $results;
    }

    private function temporaryPath(): string
    {
        $directory = BASE_PATH . '/storage/tmp';
        $path = tempnam($directory, 'img-');
        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary image file.');
        }
        return $path;
    }

    /** @return array{variant:string,path:string,width:int,height:int,bytes:int,mime:string} */
    private function result(string $variant, string $path, int $width, int $height): array
    {
        $bytes = filesize($path);
        if ($bytes === false) {
            throw new RuntimeException('Cannot inspect optimized image.');
        }
        return [
            'variant' => $variant,
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'bytes' => $bytes,
            'mime' => 'image/webp',
        ];
    }
}

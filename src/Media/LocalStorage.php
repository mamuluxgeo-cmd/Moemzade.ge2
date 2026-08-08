<?php

declare(strict_types=1);

namespace Moemzade\Media;

use RuntimeException;

final class LocalStorage implements Storage
{
    public function __construct(
        private readonly string $root,
        private readonly string $publicUrl
    ) {
    }

    public function driver(): string
    {
        return 'local';
    }

    public function put(string $key, string $localFile, string $mimeType): string
    {
        $key = ltrim(str_replace('..', '', $key), '/');
        $destination = rtrim($this->root, '/') . '/' . $key;
        $directory = dirname($destination);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create media directory.');
        }
        if (!copy($localFile, $destination)) {
            throw new RuntimeException('Cannot save optimized image.');
        }

        return rtrim($this->publicUrl, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    public function delete(string $key): void
    {
        $key = ltrim(str_replace('..', '', $key), '/');
        $path = rtrim($this->root, '/') . '/' . $key;
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Cannot remove the old image.');
        }
    }
}

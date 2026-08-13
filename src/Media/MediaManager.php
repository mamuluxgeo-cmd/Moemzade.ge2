<?php

declare(strict_types=1);

namespace Moemzade\Media;

use RuntimeException;

final class MediaManager
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /** @param array<string, mixed> $upload
     *  @return list<array{variant:string,driver:string,key:string,url:string,width:int,height:int,bytes:int,mime:string}>
     */
    public function storeTeacherPhoto(int $teacherId, array $upload): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The photo upload failed.');
        }
        $source = (string) ($upload['tmp_name'] ?? '');
        if ($source === '' || !is_uploaded_file($source)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $optimizer = new ImageOptimizer();
        $optimized = $optimizer->optimize($source, (int) $this->config['max_upload_bytes']);
        $storage = StorageFactory::make($this->config);
        $token = bin2hex(random_bytes(10));
        $saved = [];

        try {
            foreach ($optimized as $variant) {
                $key = "teachers/{$teacherId}/{$token}-{$variant['variant']}.webp";
                $saved[] = [
                    'variant' => $variant['variant'],
                    'driver' => $storage->driver(),
                    'key' => $key,
                    'url' => $storage->put($key, $variant['path'], $variant['mime']),
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                    'bytes' => $variant['bytes'],
                    'mime' => $variant['mime'],
                ];
            }
        } catch (\Throwable $exception) {
            foreach ($saved as $item) {
                try {
                    $storage->delete((string) $item['key']);
                } catch (\Throwable $cleanupException) {
                    error_log($cleanupException->__toString());
                }
            }
            throw $exception;
        } finally {
            foreach ($optimized as $variant) {
                if (is_file($variant['path'])) {
                    unlink($variant['path']);
                }
            }
        }

        return $saved;
    }

    /** @param list<array<string, mixed>> $media */
    public function deleteStored(array $media): void
    {
        foreach ($media as $item) {
            $key = (string) ($item['storage_key'] ?? $item['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $driverConfig = $this->config;
            $driverConfig['driver'] = (string) ($item['storage_driver'] ?? $item['driver'] ?? $this->config['driver']);
            StorageFactory::make($driverConfig)->delete($key);
        }
    }
}

<?php

declare(strict_types=1);

namespace Moemzade\Media;

use RuntimeException;

final class StorageFactory
{
    /** @param array<string, mixed> $config */
    public static function make(array $config): Storage
    {
        return match ($config['driver'] ?? 'local') {
            'local' => new LocalStorage((string) $config['local_root'], (string) $config['public_url']),
            'r2' => new R2Storage($config['r2']),
            default => throw new RuntimeException('Unsupported media driver.'),
        };
    }
}


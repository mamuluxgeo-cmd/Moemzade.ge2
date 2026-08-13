<?php

declare(strict_types=1);

namespace Moemzade\Media;

interface Storage
{
    public function driver(): string;

    public function put(string $key, string $localFile, string $mimeType): string;

    public function delete(string $key): void;
}

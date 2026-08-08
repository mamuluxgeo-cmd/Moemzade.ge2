<?php

declare(strict_types=1);

namespace Moemzade;

final class Translator
{
    /** @var array<string, string> */
    private array $messages;

    public function __construct(private readonly string $locale)
    {
        $file = BASE_PATH . '/lang/' . $locale . '.php';
        $fallback = BASE_PATH . '/lang/ka.php';
        $this->messages = require is_file($file) ? $file : $fallback;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /** @param array<string, scalar> $replace */
    public function get(string $key, array $replace = []): string
    {
        $message = $this->messages[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $message = str_replace(':' . $name, (string) $value, $message);
        }
        return $message;
    }
}


<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Moemzade\\';
    if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        require BASE_PATH . '/src/' . $relative . '.php';
    }
});

require BASE_PATH . '/src/helpers.php';

$failures = [];
if (slugify('Test Teacher 42') !== 'test-teacher-42') {
    $failures[] = 'ASCII slug generation failed.';
}
if (slugify('ქართული ენა') !== 'ქართული-ენა') {
    $failures[] = 'Georgian slug generation failed.';
}

$ka = require BASE_PATH . '/lang/ka.php';
$en = require BASE_PATH . '/lang/en.php';
$ru = require BASE_PATH . '/lang/ru.php';
foreach (['en' => $en, 'ru' => $ru] as $locale => $messages) {
    $missing = array_diff_key($ka, $messages);
    if ($missing) {
        $failures[] = $locale . ' is missing translation keys: ' . implode(', ', array_keys($missing));
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Smoke checks passed.\n");


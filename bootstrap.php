<?php

declare(strict_types=1);

use Moemzade\Database;
use Moemzade\Repository;
use Moemzade\Support\Env;
use Moemzade\Translator;

define('BASE_PATH', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Moemzade\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/helpers.php';

Env::load(BASE_PATH . '/.env');
$config = require BASE_PATH . '/config/app.php';

date_default_timezone_set((string) $config['timezone']);

if (($config['env'] ?? 'production') === 'production' && strlen((string) $config['key']) < 32) {
    throw new RuntimeException('APP_KEY must contain at least 32 characters in production.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('moemzade_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$language = resolve_language();
$translator = new Translator($language);
$database = Database::connect($config['db']);
$repository = new Repository($database, $config['taxonomy'] ?? []);

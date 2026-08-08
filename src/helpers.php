<?php

declare(strict_types=1);

use Moemzade\Support\Env;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function resolve_language(): string
{
    $supported = ['ka', 'en', 'ru'];
    $requested = strtolower((string) ($_GET['lang'] ?? $_COOKIE['moemzade_lang'] ?? 'ka'));
    $language = in_array($requested, $supported, true) ? $requested : 'ka';

    if (isset($_GET['lang']) && !headers_sent()) {
        setcookie('moemzade_lang', $language, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    return $language;
}

/** @param array<string, scalar> $replace */
function t(string $key, array $replace = []): string
{
    global $translator;
    return $translator->get($key, $replace);
}

function locale(): string
{
    global $translator;
    return $translator->locale();
}

function url(string $path = ''): string
{
    global $config;
    return $config['url'] . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return '/' . ltrim($path, '/');
}

function localized(array $row, string $field, ?string $language = null): string
{
    $language ??= locale();
    $preferred = trim((string) ($row[$field . '_' . $language] ?? ''));
    if ($preferred !== '') {
        return $preferred;
    }

    foreach (['ka', 'en', 'ru'] as $fallback) {
        $value = trim((string) ($row[$field . '_' . $fallback] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return trim((string) ($row[$field] ?? ''));
}

/** @param array<string, mixed> $data */
function view(string $template, array $data = [], int $status = 200): never
{
    global $config, $translator;
    http_response_code($status);
    extract($data, EXTR_SKIP);
    ob_start();
    require BASE_PATH . '/views/' . $template . '.php';
    $content = (string) ob_get_clean();
    require BASE_PATH . '/views/layout.php';
    exit;
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = (string) ($_POST['_csrf'] ?? '');
    if ($provided === '' || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}

function visitor_hash(): string
{
    global $config;
    $cookieName = 'moemzade_vid';
    $identifier = (string) ($_COOKIE[$cookieName] ?? '');

    if (!preg_match('/^[a-f0-9]{64}$/', $identifier)) {
        $identifier = bin2hex(random_bytes(32));
        $_COOKIE[$cookieName] = $identifier;
        setcookie($cookieName, $identifier, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return hash_hmac('sha256', $identifier, (string) $config['key']);
}

function is_admin(): bool
{
    return ($_SESSION['admin_authenticated'] ?? false) === true;
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('error', t('admin.login_required'));
        redirect('/admin/login');
    }
}

function admin_attempt(string $email, string $password): bool
{
    global $config;
    $expectedEmail = (string) $config['admin']['email'];
    $hash = (string) $config['admin']['password_hash'];

    if ($hash === '' || !hash_equals($expectedEmail, strtolower(trim($email))) || !password_verify($password, $hash)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_email'] = $expectedEmail;
    return true;
}

function query_url(string $path, array $changes = []): string
{
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }
    return $path . ($query ? '?' . http_build_query($query) : '');
}

function slugify(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
    return trim($value, '-');
}


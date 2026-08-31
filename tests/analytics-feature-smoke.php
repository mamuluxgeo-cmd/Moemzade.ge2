<?php

declare(strict_types=1);

use Moemzade\Repository;

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/Repository.php';
require BASE_PATH . '/src/helpers.php';

$_SESSION = [];
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/151.0 Safari/537.36';
unset($_SERVER['HTTP_PURPOSE'], $_SERVER['HTTP_SEC_PURPOSE']);
$expect(analytics_request_is_human(), 'A normal browser was rejected.');
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
$expect(!analytics_request_is_human(), 'A crawler was counted as a human.');
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/151.0 Safari/537.36';
$_SERVER['HTTP_SEC_PURPOSE'] = 'prefetch';
$expect(!analytics_request_is_human(), 'A prefetch request was counted.');
unset($_SERVER['HTTP_SEC_PURPOSE']);
$expect(analytics_allow_event('page', '/', 20), 'The first page event was rejected.');
$expect(!analytics_allow_event('page', '/', 20), 'A rapid duplicate page event was counted.');

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(
    'CREATE TABLE teacher_daily_visitors (id INTEGER);
     CREATE TABLE page_views_daily (id INTEGER);
     CREATE TABLE site_daily_visitors (id INTEGER);
     CREATE TABLE search_events (id INTEGER);
     CREATE TABLE match_requests (id INTEGER);'
);
foreach (['teacher_daily_visitors', 'page_views_daily', 'site_daily_visitors', 'search_events', 'match_requests'] as $table) {
    $db->exec("INSERT INTO {$table} (id) VALUES (1)");
}

$marker = BASE_PATH . '/storage/human-analytics-v1-20260831.done';
if (is_file($marker)) unlink($marker);
$repository = new Repository($db);
$repository->initializeHumanAnalytics();
foreach (['teacher_daily_visitors', 'page_views_daily', 'site_daily_visitors', 'search_events', 'match_requests'] as $table) {
    $expect((int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === 0, "{$table} was not reset.");
}
$db->exec('INSERT INTO page_views_daily (id) VALUES (2)');
$repository->initializeHumanAnalytics();
$expect((int) $db->query('SELECT COUNT(*) FROM page_views_daily')->fetchColumn() === 1, 'The one-time reset ran twice.');
if (is_file($marker)) unlink($marker);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "Analytics feature smoke test passed.\n";

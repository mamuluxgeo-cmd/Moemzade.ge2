<?php

declare(strict_types=1);

use Moemzade\Repository;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Translator.php';
require BASE_PATH . '/src/Repository.php';
require BASE_PATH . '/src/helpers.php';

$config = [
    'url' => 'https://moemzade.ge',
    'debug' => true,
    'media' => ['max_upload_bytes' => 10 * 1024 * 1024],
];
$translator = new Moemzade\Translator('ka');
$_SERVER['REQUEST_URI'] = '/admin/teachers';
$_SESSION = [];
$_GET = [];

/** @param array<string, mixed> $variables */
function render_admin_profile_template(string $template, array $variables): string
{
    global $config, $translator;
    extract($variables, EXTR_SKIP);
    ob_start();
    require BASE_PATH . '/views/' . $template . '.php';
    return (string) ob_get_clean();
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec(
    'CREATE TABLE teachers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name_ka TEXT NOT NULL DEFAULT \'\',
        name_en TEXT NOT NULL DEFAULT \'\',
        name_ru TEXT NOT NULL DEFAULT \'\',
        category TEXT NOT NULL DEFAULT \'\',
        region TEXT NOT NULL DEFAULT \'\',
        settlement TEXT NOT NULL DEFAULT \'\',
        price_from NUMERIC NULL,
        price_unit TEXT NOT NULL DEFAULT \'hour\',
        phone TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'draft\'
    );
    CREATE TABLE teacher_media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER NOT NULL,
        variant TEXT NOT NULL,
        storage_driver TEXT NOT NULL,
        storage_key TEXT NOT NULL,
        public_url TEXT NOT NULL,
        width INTEGER NOT NULL,
        height INTEGER NOT NULL,
        bytes INTEGER NOT NULL,
        mime_type TEXT NOT NULL,
        FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
    );
    CREATE TABLE comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER NOT NULL,
        body TEXT NOT NULL,
        FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
    );
    CREATE TABLE teacher_daily_visitors (
        view_date TEXT NOT NULL,
        teacher_id INTEGER NOT NULL,
        visitor_hash TEXT NOT NULL,
        view_count INTEGER NOT NULL DEFAULT 1,
        FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
    );'
);

$db->exec(
    "INSERT INTO teachers
        (slug, name_ka, category, region, settlement, price_from, price_unit, phone, status)
     VALUES
        ('nino-beridze', 'ნინო ბერიძე', 'ენები', 'თბილისი', 'ვაკე', 45, 'hour', '555123456', 'published');
     INSERT INTO teacher_media
        (teacher_id, variant, storage_driver, storage_key, public_url, width, height, bytes, mime_type)
     VALUES
        (1, 'profile', 'local', 'teachers/1/profile.webp', '/media/teachers/1/profile.webp', 600, 600, 2048, 'image/webp');
     INSERT INTO comments (teacher_id, body) VALUES (1, 'კარგი მასწავლებელია.');
     INSERT INTO teacher_daily_visitors (view_date, teacher_id, visitor_hash, view_count)
        VALUES ('2026-08-14', 1, 'visitor-hash', 3);"
);

$repository = new Repository($db);
$teacher = $db->query(
    "SELECT t.*, m.public_url AS photo_url
     FROM teachers t
     LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'profile'
     WHERE t.id = 1"
)->fetch();

$html = render_admin_profile_template('admin/teachers', [
    'teachers' => [$teacher],
    'filters' => ['q' => '', 'status' => '', 'sort' => 'newest'],
    'stats' => ['pending_teachers' => 0, 'published' => 1, 'teachers' => 1],
]);

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(str_contains($html, 'action="/admin/teachers/1/delete"'), 'სრული წაშლის admin action არ გამოჩნდა.');
$expect(str_contains($html, 'name="confirmation"'), 'პროფილის სახელით დადასტურების ველი არ გამოჩნდა.');
$expect(str_contains($html, 'სამუდამოდ წაშლა'), 'სამუდამო წაშლის ღილაკი არ გამოჩნდა.');
$expect(str_contains($html, 'name="action" value="reject"'), 'არქივში გადატანის action არ გამოჩნდა.');
$expect(str_contains($html, 'არქივში გადატანა'), 'არქივში გადატანის გასაგები ღილაკი არ გამოჩნდა.');
$expect(count($repository->teacherMedia(1)) === 1, 'ტესტის მედია ჩანაწერი ვერ მომზადდა.');

$repository->deleteTeacher(1);
foreach (['teachers', 'teacher_media', 'comments', 'teacher_daily_visitors'] as $table) {
    $count = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    $expect($count === 0, "პროფილის წაშლისას {$table} სრულად არ გასუფთავდა.");
}

$missingProfileRejected = false;
try {
    $repository->deleteTeacher(1);
} catch (Throwable) {
    $missingProfileRejected = true;
}
$expect($missingProfileRejected, 'უკვე წაშლილი პროფილის ხელახლა წაშლა შეცდომით არ დასრულდა.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Admin profile archive/delete checks passed.\n");

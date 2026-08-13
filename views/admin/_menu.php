<?php
$adminPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH) ?: '/admin';
$adminLinks = [
    ['/admin', t('admin.dashboard'), '⌂'],
    ['/admin/teachers', t('admin.teachers'), '◉'],
    ['/admin/teachers?status=draft', t('admin.applications'), '✦'],
    ['/admin/mentor-requests', t('admin.mentor_requests'), '▤'],
    ['/admin/mentor-requests?status=draft', t('mentor.pending_requests'), '◷'],
    ['/admin/categories', t('admin.categories'), '▦'],
    ['/admin/regions', t('admin.regions'), '⌖'],
    ['/admin/comments', t('admin.comments'), '◇'],
    ['/admin/security', t('admin.security'), '⌘'],
];
?>
<div class="admin-toolbar" aria-label="ადმინისტრაციის მენიუ">
    <a class="admin-toolbar-brand" href="/admin"><span>M</span><strong>მართვის პანელი</strong></a>
    <nav>
        <?php foreach ($adminLinks as [$url, $label, $icon]): ?>
            <?php $linkPath = parse_url($url, PHP_URL_PATH) ?: '/admin'; $active = $linkPath === '/admin' ? $adminPath === '/admin' : str_starts_with($adminPath, $linkPath); ?>
            <a class="<?= $active ? 'active' : '' ?>" href="<?= e($url) ?>" <?= $active ? 'aria-current="page"' : '' ?>><i aria-hidden="true"><?= e($icon) ?></i><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <form action="/admin/logout" method="post"><?= csrf_field() ?><button class="button button-muted" type="submit"><?= e(t('admin.logout')) ?></button></form>
</div>

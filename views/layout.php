<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Moemzade.ge') ?></title>
    <meta name="description" content="<?= e($metaDescription ?? t('home.subtitle')) ?>">
    <meta name="theme-color" content="#0f6e56">
    <link rel="icon" type="image/svg+xml" href="<?= asset('assets/images/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Georgian:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body class="<?= is_admin() ? 'admin-session' : '' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="nav-shell">
    <nav class="nav container" aria-label="Main navigation">
        <a class="brand" href="/"><img src="<?= asset('assets/images/logo.svg') ?>" alt="Moemzade.ge"></a>
        <button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="main-menu">☰</button>
        <div class="nav-menu" id="main-menu" data-menu>
            <a href="/"><?= e(t('nav.home')) ?></a>
            <a href="/teachers"><?= e(t('nav.teachers')) ?></a>
            <a href="/#match"><?= e(t('nav.match')) ?></a>
            <?php if (is_admin()): ?>
                <a href="/admin" class="admin-link"><?= e(t('admin.dashboard')) ?></a>
            <?php endif; ?>
            <div class="language-switcher" aria-label="Language">
                <?php $currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'; ?>
                <?php foreach (['ka' => 'ქარ', 'en' => 'EN', 'ru' => 'RU'] as $code => $label): ?>
                    <a class="<?= locale() === $code ? 'active' : '' ?>" lang="<?= e($code) ?>" href="<?= e(query_url($currentPath, ['lang' => $code])) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
</header>

<?php if ($success = flash('success')): ?>
    <div class="flash flash-success" role="status"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error = flash('error')): ?>
    <div class="flash flash-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<main id="main-content">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <img src="<?= asset('assets/images/logo.svg') ?>" alt="Moemzade.ge">
            <p><?= e(t('home.subtitle')) ?></p>
        </div>
        <div>
            <h2><?= e(t('nav.teachers')) ?></h2>
            <a href="/teachers"><?= e(t('search.title')) ?></a>
            <a href="/#match"><?= e(t('nav.match')) ?></a>
        </div>
        <div>
            <h2>Legal</h2>
            <a href="/privacy"><?= e(t('legal.privacy')) ?></a>
            <a href="/terms"><?= e(t('legal.terms')) ?></a>
            <a href="/cookies"><?= e(t('legal.cookies')) ?></a>
        </div>
        <div>
            <h2><?= e(t('nav.admin')) ?></h2>
            <a href="/admin/login"><?= e(t('admin.login')) ?></a>
            <a href="https://www.facebook.com/MoemzadeE/" target="_blank" rel="noopener noreferrer">Facebook</a>
        </div>
    </div>
    <div class="container footer-bottom"><span>© <?= date('Y') ?> Moemzade.ge</span><span>Made in Georgia</span></div>
</footer>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>


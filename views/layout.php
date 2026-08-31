<?php
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$canonicalUrl = $canonicalUrl ?? absolute_url($requestPath);
$socialTitle = (string) ($pageTitle ?? 'Moemzade.ge');
$socialDescription = (string) ($metaDescription ?? t('home.subtitle'));
$ogType = (string) ($ogType ?? 'website');
$usesDefaultOgImage = empty($ogImage);
$ogImage = $ogImage ?? absolute_url('/assets/images/social-preview.png?v=20260830');
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Moemzade.ge') ?></title>
    <meta name="description" content="<?= e($socialDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <?php if (!empty($robots)): ?><meta name="robots" content="<?= e($robots) ?>"><?php endif; ?>
    <meta property="og:site_name" content="Moemzade.ge">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:title" content="<?= e($socialTitle) ?>">
    <meta property="og:description" content="<?= e($socialDescription) ?>">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><meta property="og:image:secure_url" content="<?= e($ogImage) ?>"><?php if ($usesDefaultOgImage): ?><meta property="og:image:type" content="image/png"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><?php endif; ?><meta property="og:image:alt" content="<?= e($socialTitle) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= !empty($ogImage) ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($socialTitle) ?>">
    <meta name="twitter:description" content="<?= e($socialDescription) ?>">
    <?php if (!empty($ogImage)): ?><meta name="twitter:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <meta name="theme-color" content="#0f6e56">
    <link rel="icon" type="image/svg+xml" href="<?= asset('assets/images/favicon.svg') ?>">
    <link rel="preload" href="<?= asset('assets/fonts/bpg-mrgvlovani-caps-2010.ttf') ?>" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="<?= asset('assets/css/app.css?v=20260831-card-crop-v1') ?>">
    <?php if (!empty($structuredData)): ?><script type="application/ld+json"><?= str_replace('</', '<\/', (string) $structuredData) ?></script><?php endif; ?>
</head>
<body class="<?= trim((is_admin() ? 'admin-session ' : '') . (str_starts_with($requestPath, '/admin') && $requestPath !== '/admin/login' ? 'admin-area' : '')) ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="nav-shell">
    <nav class="nav container" aria-label="Main navigation">
        <a class="brand" href="/"><img src="<?= asset('assets/images/logo.svg') ?>" alt="Moemzade.ge"></a>
        <button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="main-menu">☰</button>
        <div class="nav-menu" id="main-menu" data-menu>
            <a href="/"><?= e(t('nav.home')) ?></a>
            <a href="/teachers"><?= e(t('nav.teachers')) ?></a>
            <a href="/mentor-requests"><?= e(t('nav.match')) ?></a>
            <a href="/register"><?= e(t('nav.register')) ?></a>
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
            <a href="/mentor-requests"><?= e(t('mentor.catalog_title')) ?></a>
            <a href="/mentor-requests/new"><?= e(t('mentor.add_request')) ?></a>
            <a href="/register"><?= e(t('nav.register')) ?></a>
            <a href="/faq"><?= e(t('faq.title')) ?></a>
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
            <a href="https://www.facebook.com/groups/moemzade.ge" target="_blank" rel="noopener noreferrer">👥 <?= e(t('community.group')) ?></a>
        </div>
    </div>
    <div class="container footer-bottom"><span>© <?= date('Y') ?> Moemzade.ge</span><span>Made in Georgia</span></div>
</footer>
<script src="<?= asset('assets/js/app.js?v=20260831-card-crop-v1') ?>" defer></script>
</body>
</html>

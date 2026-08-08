<section class="hero">
    <div class="hero-glow glow-one"></div>
    <div class="hero-glow glow-two"></div>
    <div class="container hero-grid">
        <div>
            <p class="eyebrow"><span></span><?= e(t('home.eyebrow')) ?></p>
            <h1><?= e(t('home.title')) ?></h1>
            <p class="hero-copy"><?= e(t('home.subtitle')) ?></p>
            <form class="quick-search" action="/teachers" method="get">
                <label>
                    <span><?= e(t('search.category')) ?></span>
                    <select name="category"><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['categories'] as $category): ?><option value="<?= e($category) ?>"><?= e($category) ?></option><?php endforeach; ?></select>
                </label>
                <label>
                    <span><?= e(t('search.region')) ?></span>
                    <select name="region"><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['regions'] as $region): ?><option value="<?= e($region) ?>"><?= e($region) ?></option><?php endforeach; ?></select>
                </label>
                <label>
                    <span><?= e(t('search.format')) ?></span>
                    <select name="format"><option value=""><?= e(t('search.any')) ?></option><option value="online"><?= e(t('search.online')) ?></option><option value="in_person"><?= e(t('search.in_person')) ?></option></select>
                </label>
                <button class="button button-primary" type="submit"><?= e(t('search.submit')) ?></button>
            </form>
            <div class="hero-stats">
                <div><strong><?= (int) $stats['teachers'] ?></strong><span><?= e(t('nav.teachers')) ?></span></div>
                <div><strong><?= (int) $stats['categories'] ?></strong><span><?= e(t('search.category')) ?></span></div>
                <div><strong><?= (int) $stats['regions'] ?></strong><span><?= e(t('search.region')) ?></span></div>
            </div>
        </div>
        <aside class="hero-card">
            <div class="hero-card-mark">✓</div>
            <h2><?= e(t('site.tagline')) ?></h2>
            <ol>
                <li><span>01</span><?= e(t('search.category')) ?></li>
                <li><span>02</span><?= e(t('search.region')) ?> / <?= e(t('search.format')) ?></li>
                <li><span>03</span><?= e(t('teacher.call')) ?></li>
            </ol>
        </aside>
    </div>
</section>

<section class="section" id="latest">
    <div class="container">
        <div class="section-heading"><div><span><?= e(t('nav.teachers')) ?></span><h2><?= e(t('home.latest')) ?></h2></div><a href="/teachers"><?= e(t('common.all')) ?> →</a></div>
        <?php if ($teachers): ?>
            <div class="teacher-grid">
                <?php foreach ($teachers as $teacher): require BASE_PATH . '/views/partials/teacher-card.php'; endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><?= e(t('home.empty')) ?></div>
        <?php endif; ?>
    </div>
</section>

<section class="section match-section" id="match">
    <div class="container match-grid">
        <div><span class="section-kicker"><?= e(t('nav.match')) ?></span><h2><?= e(t('match.title')) ?></h2><p><?= e(t('match.subtitle')) ?></p></div>
        <form class="match-form" action="/match" method="post">
            <?= csrf_field() ?>
            <label><span><?= e(t('search.category')) ?></span><select name="category"><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['categories'] as $category): ?><option value="<?= e($category) ?>"><?= e($category) ?></option><?php endforeach; ?></select></label>
            <label><span><?= e(t('search.region')) ?></span><select name="region"><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['regions'] as $region): ?><option value="<?= e($region) ?>"><?= e($region) ?></option><?php endforeach; ?></select></label>
            <label><span><?= e(t('search.format')) ?></span><select name="format"><option value=""><?= e(t('search.any')) ?></option><option value="online"><?= e(t('search.online')) ?></option><option value="in_person"><?= e(t('search.in_person')) ?></option></select></label>
            <label><span><?= e(t('search.language')) ?></span><select name="language"><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['languages'] as $language): ?><option value="<?= e($language) ?>"><?= e($language) ?></option><?php endforeach; ?></select></label>
            <button class="button button-light" type="submit"><?= e(t('match.submit')) ?></button>
        </form>
    </div>
</section>


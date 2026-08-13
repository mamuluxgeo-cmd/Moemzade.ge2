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

<?php if ($categories): ?>
<?php
$displayCategories = $categories;
usort($displayCategories, static fn (array $a, array $b): int => ((int) $b['total']) <=> ((int) $a['total']));
$popularLeft = 3;
?>
<section class="section category-section">
    <div class="container">
        <div class="section-heading category-heading"><div><span><?= e(t('home.categories_kicker')) ?></span><h2><?= e(t('home.categories_title')) ?></h2></div><a class="category-all-link" href="/teachers"><?= e(t('home.categories_all')) ?><span aria-hidden="true">→</span></a></div>
        <div class="category-grid">
            <?php foreach ($displayCategories as $item): ?>
                <?php
                $total = (int) $item['total'];
                $isPopular = $total > 0 && $popularLeft-- > 0;
                ?>
                <a class="category-card<?= $total === 0 ? ' category-card-empty' : '' ?>" href="/teachers?category=<?= rawurlencode($item['category']) ?>" aria-label="<?= e($item['category'] . ' — ' . t('home.category_count', ['count' => $total])) ?>">
                    <span class="category-icon" aria-hidden="true"><?= category_icon($item['category']) ?></span>
                    <?php if ($isPopular): ?><span class="category-popular"><?= e(t('home.category_popular')) ?></span><?php endif; ?>
                    <strong><?= e($item['category']) ?></strong>
                    <span class="category-card-footer"><small><?= e(t('home.category_count', ['count' => $total])) ?></small><i aria-hidden="true">→</i></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" id="latest">
    <div class="container">
        <div class="section-heading"><div><span><?= e(t('nav.teachers')) ?></span><h2><?= e(t('home.latest')) ?></h2></div><div class="section-heading-actions"><a class="button button-primary" href="/register">+ <?= e(t('mentor.add_request')) ?></a></div></div>
        <?php if ($teachers): ?>
            <div class="teacher-grid home-teacher-grid">
                <?php foreach ($teachers as $teacher): require BASE_PATH . '/views/partials/teacher-card.php'; endforeach; ?>
            </div>
            <div class="home-teachers-actions">
                <a class="button home-all-teachers-button" href="/teachers"><?= e(t('common.all')) ?> <span aria-hidden="true">→</span></a>
            </div>
        <?php else: ?>
            <div class="empty-state"><?= e(t('home.empty')) ?></div>
        <?php endif; ?>
    </div>
</section>

<section class="section mentor-home-section" id="mentor-requests">
    <div class="container">
        <div class="section-heading mentor-section-heading"><div><span><?= e(t('nav.match')) ?></span><h2><?= e(t('mentor.home_title')) ?></h2><p><?= e(t('mentor.home_subtitle')) ?></p></div><div class="section-heading-actions"><a href="/mentor-requests"><?= e(t('common.all')) ?> →</a><a class="button button-primary" href="/mentor-requests/new">+ <?= e(t('mentor.add_request')) ?></a></div></div>
        <?php if ($mentorRequests): ?>
            <div class="request-grid"><?php foreach ($mentorRequests as $request): require BASE_PATH . '/views/partials/mentor-request-card.php'; endforeach; ?></div>
        <?php else: ?>
            <div class="empty-state"><h2><?= e(t('mentor.empty')) ?></h2><p><?= e(t('mentor.empty_text')) ?></p><a class="button button-primary" href="/mentor-requests/new"><?= e(t('mentor.add_request')) ?></a></div>
        <?php endif; ?>
    </div>
</section>

<section class="section community-section">
    <div class="container community-card">
        <div><span class="section-kicker"><?= e(t('community.kicker')) ?></span><h2><?= e(t('community.title')) ?></h2><p><?= e(t('community.text')) ?></p></div>
        <a class="button button-primary" href="https://www.facebook.com/groups/moemzade.ge" target="_blank" rel="noopener noreferrer">👥 <?= e(t('community.join')) ?></a>
    </div>
</section>

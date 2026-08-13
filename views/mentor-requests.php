<header class="page-hero mentor-catalog-hero">
    <div class="container catalog-title-row">
        <div><span class="section-kicker"><?= e(t('nav.match')) ?></span><h1><?= e(t('mentor.catalog_title')) ?></h1><p><?= e(t('mentor.catalog_subtitle')) ?></p></div>
        <a class="button button-primary" href="/mentor-requests/new">+ <?= e(t('mentor.add_request')) ?></a>
    </div>
</header>

<section class="section listing-section">
    <div class="container">
        <form class="filter-form mentor-filter-form" action="/mentor-requests" method="get" data-location-form>
            <label class="wide"><span><?= e(t('mentor.keyword')) ?></span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('mentor.keyword_placeholder')) ?>"></label>
            <label><span><?= e(t('search.category')) ?></span><select name="category"><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['categories'] as $item): ?><option value="<?= e($item) ?>" <?= $filters['category'] === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
            <label><span><?= e(t('search.region')) ?></span><select name="region" data-region-select><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['regions'] as $item): ?><option value="<?= e($item) ?>" <?= $filters['region'] === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
            <label><span><?= e(t('teacher.settlement')) ?></span><select name="settlement" data-settlement-select><?= settlement_option_tags($options, $filters['region'], $filters['settlement'], t('search.any')) ?></select></label>
            <label><span><?= e(t('search.format')) ?></span><select name="format"><option value=""><?= e(t('search.any')) ?></option><option value="online" <?= $filters['format'] === 'online' ? 'selected' : '' ?>><?= e(t('search.online')) ?></option><option value="in_person" <?= $filters['format'] === 'in_person' ? 'selected' : '' ?>><?= e(t('search.in_person')) ?></option></select></label>
            <button class="button button-primary" type="submit"><?= e(t('search.submit')) ?></button>
        </form>

        <div class="catalog-result-heading"><strong><?= e(t('mentor.results', ['count' => $total])) ?></strong><span><?= e(t('mentor.teacher_hint')) ?></span></div>
        <?php if ($requests): ?>
            <div class="request-grid"><?php foreach ($requests as $request): require BASE_PATH . '/views/partials/mentor-request-card.php'; endforeach; ?></div>
        <?php else: ?>
            <div class="empty-state"><h2><?= e(t('mentor.empty')) ?></h2><p><?= e(t('mentor.empty_text')) ?></p><a class="button button-primary" href="/mentor-requests/new"><?= e(t('mentor.add_request')) ?></a></div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php for ($number = 1; $number <= $pages; $number++): ?>
                    <a class="<?= $number === $page ? 'active' : '' ?>" href="<?= e(query_url('/mentor-requests', ['page' => $number])) ?>"><?= $number ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

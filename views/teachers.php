<header class="page-hero teachers-page-hero">
    <div class="container"><span class="section-kicker"><?= e(t('nav.teachers')) ?></span><h1><?= e(t('search.title')) ?></h1><p><?= e(t('search.results', ['count' => $total])) ?></p></div>
</header>
<section class="section listing-section teachers-listing-section">
    <div class="container">
        <form class="filter-form" action="/teachers" method="get" data-location-form>
            <label class="wide"><span><?= e(t('search.keyword')) ?></span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('search.keyword')) ?>"></label>
            <label><span><?= e(t('search.category')) ?></span><select name="category"><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['categories'] as $item): ?><option value="<?= e($item) ?>" <?= $filters['category'] === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
            <label><span><?= e(t('search.region')) ?></span><select name="region" data-region-select><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['regions'] as $item): ?><option value="<?= e($item) ?>" <?= $filters['region'] === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
            <label><span><?= e(t('teacher.settlement')) ?></span><select name="settlement" data-settlement-select><?= settlement_option_tags($options, $filters['region'], $filters['settlement'], t('search.any')) ?></select></label>
            <label><span><?= e(t('search.format')) ?></span><select name="format"><option value=""><?= e(t('search.any')) ?></option><option value="online" <?= $filters['format'] === 'online' ? 'selected' : '' ?>><?= e(t('search.online')) ?></option><option value="in_person" <?= $filters['format'] === 'in_person' ? 'selected' : '' ?>><?= e(t('search.in_person')) ?></option></select></label>
            <label><span><?= e(t('search.language')) ?></span><select name="language"><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['languages'] as $item): ?><option value="<?= e($item) ?>" <?= $filters['language'] === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
            <button class="button button-primary" type="submit"><?= e(t('search.submit')) ?></button>
        </form>

        <?php if ($teachers): ?>
            <div class="teacher-grid teachers-page-grid"><?php foreach ($teachers as $teacher): require BASE_PATH . '/views/partials/teacher-card.php'; endforeach; ?></div>
        <?php else: ?>
            <div class="empty-state"><h2><?= e(t('search.empty')) ?></h2><p><?= e(t('match.subtitle')) ?></p></div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php for ($number = 1; $number <= $pages; $number++): ?>
                    <a class="<?= $number === $page ? 'active' : '' ?>" href="<?= e(query_url('/teachers', ['page' => $number])) ?>"><?= $number ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>

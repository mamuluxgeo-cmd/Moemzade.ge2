<header class="page-hero compact"><div class="container"><span class="section-kicker">Admin</span><h1><?= e(t('admin.dashboard')) ?></h1><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>
<section class="section admin-section"><div class="container">
    <div class="stat-grid">
        <article><strong><?= (int) $stats['teachers'] ?></strong><span><?= e(t('admin.teachers')) ?></span></article>
        <article><strong><?= (int) $stats['published'] ?></strong><span>Published</span></article>
        <article><strong><?= (int) $stats['pending_teachers'] ?></strong><span><?= e(t('admin.pending_applications')) ?></span></article>
        <article><strong><?= (int) $stats['mentor_requests'] ?></strong><span><?= e(t('admin.mentor_requests')) ?></span></article>
        <article><strong><?= (int) $stats['pending_mentor_requests'] ?></strong><span><?= e(t('mentor.pending_requests')) ?></span></article>
        <article><strong><?= (int) $stats['pending_comments'] ?></strong><span>Pending comments</span></article>
        <article><strong><?= (int) $stats['today_visitors'] ?></strong><span>Today unique</span></article>
        <article><strong><?= (int) $stats['page_views'] ?></strong><span>Page views</span></article>
        <article><strong><?= (int) $stats['failed_searches'] ?></strong><span>Failed searches</span></article>
    </div>
    <div class="admin-columns">
        <article class="admin-panel"><h2>Top teachers</h2><div class="table-wrap"><table><thead><tr><th>Teacher</th><th>Views</th><th>Unique/day</th></tr></thead><tbody><?php foreach ($topTeachers as $teacher): ?><tr><td><a href="/teacher/<?= rawurlencode($teacher['slug']) ?>"><?= e(localized($teacher, 'name')) ?></a></td><td><?= (int) $teacher['total_views'] ?></td><td><?= (int) $teacher['unique_views'] ?></td></tr><?php endforeach; ?></tbody></table></div></article>
        <article class="admin-panel"><h2>Unsuccessful searches</h2><div class="table-wrap"><table><thead><tr><th>Category / region</th><th>Format / language</th><th>Count</th></tr></thead><tbody><?php foreach ($failedSearches as $search): ?><tr><td><?= e(($search['category'] ?: '—') . ' / ' . ($search['region'] ?: '—')) ?></td><td><?= e(($search['teaching_format'] ?: '—') . ' / ' . ($search['language'] ?: '—')) ?></td><td><?= (int) $search['attempts'] ?></td></tr><?php endforeach; ?></tbody></table></div></article>
    </div>
</div></section>

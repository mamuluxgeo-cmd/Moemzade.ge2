<article class="teacher-card">
    <a href="/teacher/<?= rawurlencode((string) $teacher['slug']) ?>">
        <div class="teacher-photo">
            <?php if (!empty($teacher['photo_url'])): ?>
                <img src="<?= e($teacher['photo_url']) ?>" alt="<?= e(localized($teacher, 'name')) ?>" loading="lazy" width="720" height="720">
            <?php else: ?>
                <span aria-hidden="true"><?= e(mb_substr(localized($teacher, 'name'), 0, 1, 'UTF-8')) ?></span>
            <?php endif; ?>
        </div>
        <div class="teacher-card-body">
            <p class="teacher-category"><?= e($teacher['category']) ?></p>
            <h3><?= e(localized($teacher, 'name')) ?></h3>
            <p><?= e(localized($teacher, 'profession')) ?></p>
            <div class="teacher-meta"><span>📍 <?= e($teacher['settlement'] ?: $teacher['region']) ?></span><?php if ($teacher['format_online']): ?><span>◉ <?= e(t('search.online')) ?></span><?php endif; ?></div>
        </div>
    </a>
</article>


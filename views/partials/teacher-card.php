<?php $item = isset($cardTeacher) && is_array($cardTeacher) ? $cardTeacher : $teacher; ?>
<article class="teacher-card">
    <a href="/teacher/<?= rawurlencode((string) $item['slug']) ?>">
        <span class="teacher-card-clip" aria-hidden="true"></span>
        <div class="teacher-photo">
            <?php if (!empty($item['photo_url'])): ?>
                <?php $hasCardCrop = isset($item['card_photo_x'], $item['card_photo_y'], $item['card_photo_zoom']); ?>
                <?php if (!$hasCardCrop): ?><img class="teacher-photo-backdrop" src="<?= e($item['photo_url']) ?>" alt="" aria-hidden="true" loading="lazy" width="720" height="720"><?php endif; ?>
                <img class="teacher-photo-main<?= $hasCardCrop ? ' teacher-photo-cropped' : '' ?>" src="<?= e($item['photo_url']) ?>" alt="<?= e(localized($item, 'name')) ?>" loading="lazy" width="720" height="720"<?= $hasCardCrop ? ' style="--card-photo-x:' . e((string) $item['card_photo_x']) . '%;--card-photo-y:' . e((string) $item['card_photo_y']) . '%;--card-photo-zoom:' . e((string) $item['card_photo_zoom']) . '"' : '' ?>>
            <?php else: ?>
                <span aria-hidden="true"><?= e(mb_substr(localized($item, 'name'), 0, 1, 'UTF-8')) ?></span>
            <?php endif; ?>
        </div>
        <div class="teacher-card-body">
            <p class="teacher-category"><?= e($item['category']) ?></p>
            <div class="teacher-card-price"><?= e(teacher_price($item)) ?></div>
            <h3><?= e(localized($item, 'name')) ?></h3>
            <p><?= e(localized($item, 'profession')) ?></p>
            <div class="teacher-meta"><span>📍 <?= e($item['settlement'] ?: $item['region']) ?></span><?php if ($item['format_online']): ?><span>◉ <?= e(t('search.online')) ?></span><?php endif; ?></div>
            <span class="teacher-card-arrow" aria-hidden="true">→</span>
        </div>
    </a>
</article>
<?php unset($item, $cardTeacher); ?>

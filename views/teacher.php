<?php
$phoneHref = preg_replace('/[^0-9+]/', '', (string) $teacher['phone']);
$languages = array_filter(array_map('trim', explode(',', (string) $teacher['languages'])));
$formats = [];
if ($teacher['format_online']) $formats[] = t('search.online');
if ($teacher['format_in_person']) $formats[] = t('search.in_person');
?>
<header class="profile-hero">
    <div class="container">
        <a class="back-link" href="/teachers">← <?= e(t('common.back')) ?></a>
        <div class="profile-head">
            <div class="profile-image">
                <?php if ($teacher['photo_url']): ?><img src="<?= e($teacher['photo_url']) ?>" alt="<?= e(localized($teacher, 'name')) ?>" width="720" height="720"><?php else: ?><span><?= e(mb_substr(localized($teacher, 'name'), 0, 1, 'UTF-8')) ?></span><?php endif; ?>
            </div>
            <div><p class="teacher-category"><?= e($teacher['category']) ?></p><h1><?= e(localized($teacher, 'name')) ?></h1><p><?= e(localized($teacher, 'profession')) ?></p><div class="profile-badges"><?php foreach ($formats as $format): ?><span><?= e($format) ?></span><?php endforeach; ?></div></div>
        </div>
    </div>
</header>
<section class="section profile-section">
    <div class="container profile-grid">
        <aside class="profile-card info-list">
            <div><span><?= e(t('teacher.region')) ?></span><strong><?= e($teacher['region'] ?: '—') ?></strong></div>
            <div><span><?= e(t('teacher.settlement')) ?></span><strong><?= e($teacher['settlement'] ?: '—') ?></strong></div>
            <div><span><?= e(t('teacher.format')) ?></span><strong><?= e(implode(', ', $formats) ?: '—') ?></strong></div>
            <div><span><?= e(t('teacher.languages')) ?></span><strong><?= e(implode(', ', $languages) ?: '—') ?></strong></div>
            <div><span><?= e(t('teacher.price')) ?></span><strong><?= $teacher['price_from'] !== null ? e(rtrim(rtrim((string) $teacher['price_from'], '0'), '.') . ' ₾') : '—' ?></strong></div>
            <div><span><?= e(t('teacher.phone')) ?></span><strong><?= e($teacher['phone'] ?: '—') ?></strong></div>
            <?php if ($phoneHref): ?><a class="button button-primary full" href="tel:<?= e($phoneHref) ?>"><?= e(t('teacher.call')) ?> · <?= e($teacher['phone']) ?></a><?php endif; ?>
        </aside>
        <div class="profile-main">
            <article class="profile-card prose"><span class="section-kicker"><?= e(t('teacher.about')) ?></span><p><?= nl2br(e(localized($teacher, 'bio'))) ?></p></article>
            <div class="social-links">
                <?php if (filter_var($teacher['facebook_url'], FILTER_VALIDATE_URL)): ?><a href="<?= e($teacher['facebook_url']) ?>" target="_blank" rel="noopener noreferrer">Facebook ↗</a><?php endif; ?>
                <?php if (filter_var($teacher['instagram_url'], FILTER_VALIDATE_URL)): ?><a href="<?= e($teacher['instagram_url']) ?>" target="_blank" rel="noopener noreferrer">Instagram ↗</a><?php endif; ?>
            </div>
            <section class="profile-card comments" id="comments">
                <span class="section-kicker"><?= e(t('comments.title')) ?></span><h2><?= e(t('comments.title')) ?></h2>
                <?php if ($comments): ?><div class="comment-list"><?php foreach ($comments as $comment): ?><article><div><strong><?= e($comment['author_name']) ?></strong><span aria-label="<?= (int) $comment['rating'] ?> out of 5"><?= str_repeat('★', (int) $comment['rating']) ?></span></div><p><?= nl2br(e($comment['body'])) ?></p></article><?php endforeach; ?></div><?php endif; ?>
                <form class="comment-form" action="/teacher/<?= (int) $teacher['id'] ?>/comments" method="post">
                    <?= csrf_field() ?>
                    <label><span><?= e(t('comments.name')) ?></span><input name="author_name" minlength="2" maxlength="100" required></label>
                    <label><span><?= e(t('comments.rating')) ?></span><select name="rating" required><option value="">—</option><?php for ($star = 5; $star >= 1; $star--): ?><option value="<?= $star ?>"><?= str_repeat('★', $star) ?></option><?php endfor; ?></select></label>
                    <label class="wide"><span><?= e(t('comments.body')) ?></span><textarea name="body" minlength="10" maxlength="1500" rows="5" required></textarea></label>
                    <p class="form-note wide"><?= e(t('comments.note')) ?></p>
                    <button class="button button-primary" type="submit"><?= e(t('comments.submit')) ?></button>
                </form>
            </section>
        </div>
    </div>
</section>


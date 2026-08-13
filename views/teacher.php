<?php
$phoneHref = preg_replace('/[^0-9+]/', '', (string) $teacher['phone']);
$whatsappNumber = whatsapp_number((string) $teacher['phone']);
$languages = array_filter(array_map('trim', explode(',', (string) $teacher['languages'])));
$formats = [];
if ($teacher['format_online']) $formats[] = t('search.online');
if ($teacher['format_in_person']) $formats[] = t('search.in_person');
$shareUrl = absolute_url('/teacher/' . rawurlencode((string) $teacher['slug']));
$shareText = t('share.message', ['name' => localized($teacher, 'name'), 'profession' => localized($teacher, 'profession')]);
?>
<header class="profile-hero">
    <div class="container">
        <a class="back-link" href="/teachers">← <?= e(t('common.back')) ?></a>
        <div class="profile-head">
            <?php if ($teacher['photo_url']): ?><button class="profile-image profile-image-button" type="button" data-photo-open aria-label="<?= e(t('photo.enlarge')) ?>"><img src="<?= e($teacher['photo_url']) ?>" alt="<?= e(localized($teacher, 'name')) ?>" width="720" height="720"><span><?= e(t('photo.enlarge')) ?></span></button><?php else: ?><div class="profile-image"><span><?= e(mb_substr(localized($teacher, 'name'), 0, 1, 'UTF-8')) ?></span></div><?php endif; ?>
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
            <div><span><?= e(t('teacher.price')) ?></span><strong><?= e(teacher_price($teacher)) ?></strong></div>
            <div><span><?= e(t('teacher.phone')) ?></span><strong><?= e($teacher['phone'] ?: '—') ?></strong></div>
            <?php if ($phoneHref): ?><a class="button button-primary full" href="tel:<?= e($phoneHref) ?>"><?= e(t('teacher.call')) ?> · <?= e($teacher['phone']) ?></a><?php endif; ?>
            <?php if ($whatsappNumber): ?><a class="button button-whatsapp full" href="https://wa.me/<?= e($whatsappNumber) ?>" target="_blank" rel="noopener noreferrer">💬 <?= e(t('teacher.whatsapp')) ?></a><?php endif; ?>
        </aside>
        <div class="profile-main">
            <article class="profile-card prose"><span class="section-kicker"><?= e(t('teacher.about')) ?></span><p><?= nl2br(e(localized($teacher, 'bio'))) ?></p></article>
            <div class="social-links">
                <?php if (filter_var($teacher['facebook_url'], FILTER_VALIDATE_URL)): ?><a href="<?= e($teacher['facebook_url']) ?>" target="_blank" rel="noopener noreferrer">Facebook ↗</a><?php endif; ?>
                <?php if (filter_var($teacher['instagram_url'], FILTER_VALIDATE_URL)): ?><a href="<?= e($teacher['instagram_url']) ?>" target="_blank" rel="noopener noreferrer">Instagram ↗</a><?php endif; ?>
            </div>
            <section class="profile-card share-card">
                <div><span class="section-kicker"><?= e(t('share.kicker')) ?></span><h2><?= e(t('share.title')) ?></h2><p><?= e(t('share.subtitle')) ?></p></div>
                <div class="share-actions">
                    <a class="share-button facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
                    <button class="share-button" type="button" data-copy-link="<?= e($shareUrl) ?>"><?= e(t('share.copy')) ?></button>
                    <a class="share-button whatsapp" href="https://wa.me/?text=<?= rawurlencode($shareText . ' ' . $shareUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                    <a class="share-button" href="https://www.messenger.com/t/?link=<?= rawurlencode($shareUrl) ?>" target="_blank" rel="noopener noreferrer">Messenger</a>
                </div>
                <span class="copy-status" data-copy-status role="status"><?= e(t('share.copied')) ?></span>
            </section>
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

<?php if ($similarTeachers): ?>
<section class="section similar-section"><div class="container"><div class="section-heading"><div><span><?= e(t('similar.kicker')) ?></span><h2><?= e(t('similar.title')) ?></h2><p><?= e(t('similar.subtitle')) ?></p></div><a href="/teachers?category=<?= rawurlencode((string) $teacher['category']) ?>&region=<?= rawurlencode((string) $teacher['region']) ?>"><?= e(t('common.all')) ?> →</a></div><div class="teacher-grid similar-grid"><?php foreach ($similarTeachers as $similarTeacher): $cardTeacher = $similarTeacher; require BASE_PATH . '/views/partials/teacher-card.php'; endforeach; ?></div></div></section>
<?php endif; ?>

<?php if (!empty($teacher['photo_url'])): ?><div class="photo-modal" data-photo-modal hidden><div class="photo-modal-box" role="dialog" aria-modal="true" aria-label="<?= e(t('photo.enlarged')) ?>"><button type="button" data-photo-close aria-label="<?= e(t('photo.close')) ?>">×</button><img src="<?= e($teacher['photo_url']) ?>" alt="<?= e(localized($teacher, 'name')) ?>"></div></div><?php endif; ?>

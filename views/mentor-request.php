<?php
$formats = [];
if (!empty($request['format_online'])) $formats[] = t('search.online');
if (!empty($request['format_in_person'])) $formats[] = t('search.in_person');
$phoneHref = preg_replace('/[^0-9+]/', '', (string) $request['phone']);
$whatsappNumber = whatsapp_number((string) $request['phone']);
$requestUrl = absolute_url('/mentor-request/' . rawurlencode((string) $request['slug']));
$contactMessage = t('mentor.contact_message', ['subject' => (string) $request['subject']]) . ' ' . $requestUrl;
$shareMessage = t('mentor.share_message', ['subject' => (string) $request['subject']]);
?>
<header class="profile-hero request-profile-hero">
    <div class="container">
        <a class="back-link" href="/mentor-requests">← <?= e(t('common.back')) ?></a>
        <div class="profile-head request-profile-head">
            <div class="profile-image request-profile-icon"><span aria-hidden="true"><?= category_icon((string) $request['category']) ?></span></div>
            <div><p class="teacher-category"><?= e($request['category']) ?></p><h1><?= e($request['subject']) ?></h1><p><?= e($request['name']) ?> · <?= e(mentor_learner_group_label((string) $request['learner_group'])) ?></p><div class="profile-badges"><?php foreach ($formats as $format): ?><span><?= e($format) ?></span><?php endforeach; ?></div></div>
        </div>
    </div>
</header>

<section class="section profile-section">
    <div class="container profile-grid">
        <aside class="profile-card info-list">
            <div><span><?= e(t('teacher.region')) ?></span><strong><?= e($request['region'] ?: '—') ?></strong></div>
            <div><span><?= e(t('teacher.settlement')) ?></span><strong><?= e($request['settlement'] ?: '—') ?></strong></div>
            <div><span><?= e(t('mentor.preferred_format')) ?></span><strong><?= e(implode(', ', $formats) ?: '—') ?></strong></div>
            <div><span><?= e(t('mentor.current_level')) ?></span><strong><?= e($request['current_level'] ?: '—') ?></strong></div>
            <div><span><?= e(t('mentor.budget')) ?></span><strong><?= e(mentor_request_budget($request)) ?></strong></div>
            <div><span><?= e(t('mentor.availability')) ?></span><strong><?= e($request['availability'] ?: '—') ?></strong></div>
            <?php if ($request['desired_start']): ?><div><span><?= e(t('mentor.desired_start')) ?></span><strong><?= e($request['desired_start']) ?></strong></div><?php endif; ?>
            <div><span><?= e(t('mentor.contact_phone')) ?></span><strong><?= e($request['phone'] ?: '—') ?></strong></div>
            <?php if ($request['email']): ?><div><span><?= e(t('mentor.email')) ?></span><strong><?= e($request['email']) ?></strong></div><?php endif; ?>
            <?php if ($phoneHref): ?><a class="button button-primary full" href="tel:<?= e($phoneHref) ?>"><?= e(t('teacher.call')) ?> · <?= e($request['phone']) ?></a><?php endif; ?>
            <?php if ($whatsappNumber): ?><a class="button button-whatsapp full" href="https://wa.me/<?= e($whatsappNumber) ?>?text=<?= rawurlencode($contactMessage) ?>" target="_blank" rel="noopener noreferrer">💬 <?= e(t('mentor.offer')) ?></a><?php endif; ?>
            <?php if ($request['email']): ?><a class="button button-muted full" href="mailto:<?= e($request['email']) ?>?subject=<?= rawurlencode(t('mentor.email_subject', ['subject' => (string) $request['subject']])) ?>"><?= e(t('mentor.email_action')) ?></a><?php endif; ?>
        </aside>
        <div class="profile-main">
            <article class="profile-card prose"><span class="section-kicker"><?= e(t('mentor.learning_goal')) ?></span><p><?= nl2br(e($request['learning_goal'])) ?></p></article>
            <?php if ($request['details']): ?><article class="profile-card prose"><span class="section-kicker"><?= e(t('mentor.additional_details')) ?></span><p><?= nl2br(e($request['details'])) ?></p></article><?php endif; ?>
            <section class="profile-card share-card">
                <div><span class="section-kicker"><?= e(t('share.kicker')) ?></span><h2><?= e(t('mentor.share_title')) ?></h2><p><?= e(t('mentor.share_subtitle')) ?></p></div>
                <div class="share-actions">
                    <a class="share-button facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($requestUrl) ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
                    <button class="share-button" type="button" data-copy-link="<?= e($requestUrl) ?>"><?= e(t('share.copy')) ?></button>
                    <a class="share-button whatsapp" href="https://wa.me/?text=<?= rawurlencode($shareMessage . ' ' . $requestUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                </div>
                <span class="copy-status" data-copy-status role="status"><?= e(t('share.copied')) ?></span>
            </section>
        </div>
    </div>
</section>

<section class="section mentor-detail-cta"><div class="container community-card"><div><span class="section-kicker"><?= e(t('nav.match')) ?></span><h2><?= e(t('mentor.cta_title')) ?></h2><p><?= e(t('mentor.cta_text')) ?></p></div><a class="button button-light" href="/mentor-requests/new"><?= e(t('mentor.add_request')) ?></a></div></section>

<?php
$item = isset($cardRequest) && is_array($cardRequest) ? $cardRequest : $request;
$formats = [];
if (!empty($item['format_online'])) $formats[] = t('search.online');
if (!empty($item['format_in_person'])) $formats[] = t('search.in_person');
$phoneHref = preg_replace('/[^0-9+]/', '', (string) $item['phone']);
$whatsappNumber = whatsapp_number((string) $item['phone']);
$requestUrl = absolute_url('/mentor-request/' . rawurlencode((string) $item['slug']));
$message = t('mentor.contact_message', ['subject' => (string) $item['subject']]) . ' ' . $requestUrl;
?>
<article class="request-card">
    <a class="request-card-main" href="/mentor-request/<?= rawurlencode((string) $item['slug']) ?>">
        <div class="request-card-top">
            <span class="request-icon" aria-hidden="true"><?= category_icon((string) $item['category']) ?></span>
            <span class="request-budget"><?= e(mentor_request_budget($item)) ?></span>
        </div>
        <p class="teacher-category"><?= e($item['category']) ?></p>
        <h3><?= e($item['subject']) ?></h3>
        <p class="request-person"><?= e($item['name']) ?><?php if ($item['learner_group']): ?> · <?= e(mentor_learner_group_label((string) $item['learner_group'])) ?><?php endif; ?></p>
        <div class="request-meta">
            <span>📍 <?= e($item['settlement'] ?: $item['region']) ?></span>
            <?php foreach ($formats as $format): ?><span>◉ <?= e($format) ?></span><?php endforeach; ?>
        </div>
        <p class="request-availability"><strong><?= e(t('mentor.availability')) ?>:</strong> <?= e($item['availability']) ?></p>
    </a>
    <div class="request-card-actions">
        <a href="/mentor-request/<?= rawurlencode((string) $item['slug']) ?>"><?= e(t('mentor.details')) ?></a>
        <?php if ($phoneHref): ?><a href="tel:<?= e($phoneHref) ?>"><?= e(t('teacher.call')) ?></a><?php endif; ?>
        <?php if ($whatsappNumber): ?><a class="request-whatsapp" href="https://wa.me/<?= e($whatsappNumber) ?>?text=<?= rawurlencode($message) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('mentor.offer')) ?></a><?php endif; ?>
    </div>
</article>
<?php unset($item, $cardRequest, $formats, $phoneHref, $whatsappNumber, $requestUrl, $message); ?>

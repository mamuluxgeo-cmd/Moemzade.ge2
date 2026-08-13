<?php
$old = $old ?? [];
$errors = $errors ?? [];
$field = static fn (string $key): string => (string) ($old[$key] ?? '');
$checked = static fn (string $key): bool => !empty($old[$key]);
$error = static fn (string $key): string => (string) ($errors[$key] ?? '');
$categorySubcategories = $options['category_subcategories'] ?? [];
$categorizedSubcategories = [];
foreach ($categorySubcategories as $items) {
    foreach ($items as $item) {
        $categorizedSubcategories[(string) $item] = true;
    }
}
$uncategorizedSubcategories = array_values(array_filter(
    $options['subcategories'] ?? [],
    static fn (string $item): bool => !isset($categorizedSubcategories[$item])
));
?>
<header class="register-hero">
    <div class="container narrow">
        <span class="section-kicker light"><?= e(t('register.free')) ?></span>
        <h1><?= e(t('register.title')) ?></h1>
        <p><?= e(t('register.subtitle')) ?></p>
        <a class="register-group-pill" href="https://www.facebook.com/groups/moemzade.ge" target="_blank" rel="noopener noreferrer">👥 <?= e(t('community.join_short')) ?></a>
        <div class="steps-indicator" aria-label="<?= e(t('register.steps_label')) ?>">
            <span class="step-dot active" data-step-dot="1">1</span><i></i>
            <span class="step-dot" data-step-dot="2">2</span><i></i>
            <span class="step-dot" data-step-dot="3">3</span>
        </div>
    </div>
</header>

<section class="section register-section">
    <div class="container narrow">
        <?php if ($error('form')): ?><div class="form-error-summary" role="alert"><?= e($error('form')) ?></div><?php endif; ?>
        <form class="register-form" action="/register" method="post" enctype="multipart/form-data" data-registration-form data-taxonomy-form data-location-form novalidate>
            <?= csrf_field() ?>
            <div class="register-progress"><span data-register-progress></span></div>

            <section class="register-step active" data-step-panel="1">
                <div class="step-header"><span>01</span><div><h2><?= e(t('register.step1_title')) ?></h2><p><?= e(t('register.step1_text')) ?></p></div></div>
                <div class="register-fields">
                    <label class="wide"><span><?= e(t('register.name')) ?> *</span><input name="name_ka" value="<?= e($field('name_ka')) ?>" minlength="2" maxlength="190" autocomplete="name" required><?php if ($error('name_ka')): ?><small class="field-error"><?= e($error('name_ka')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('search.region')) ?> *</span><select name="region" data-region-select required><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['regions'] as $item): ?><option value="<?= e($item) ?>" <?= $field('region') === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select><?php if ($error('region')): ?><small class="field-error"><?= e($error('region')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('teacher.settlement')) ?> *</span><select name="settlement" data-settlement-select required><?= settlement_option_tags($options, $field('region'), $field('settlement'), t('search.any')) ?></select><?php if ($error('settlement')): ?><small class="field-error"><?= e($error('settlement')) ?></small><?php endif; ?></label>
                    <fieldset class="wide compact-fieldset"><legend><?= e(t('search.format')) ?> *</legend><label class="check-card"><input type="checkbox" name="format_in_person" value="1" <?= $checked('format_in_person') ? 'checked' : '' ?>><span><?= e(t('search.in_person')) ?></span></label><label class="check-card"><input type="checkbox" name="format_online" value="1" <?= $checked('format_online') ? 'checked' : '' ?>><span><?= e(t('search.online')) ?></span></label><?php if ($error('format')): ?><small class="field-error wide"><?= e($error('format')) ?></small><?php endif; ?></fieldset>
                    <label><span><?= e(t('teacher.price')) ?></span><input type="number" name="price_from" value="<?= e($field('price_from')) ?>" min="0" max="99999999.99" step="0.01" data-price-input><?php if ($error('price_from')): ?><small class="field-error"><?= e($error('price_from')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('price.type')) ?> *</span><select name="price_unit" data-price-unit><option value="hour" <?= $field('price_unit') === 'hour' || $field('price_unit') === '' ? 'selected' : '' ?>><?= e(t('price.hour')) ?></option><option value="month" <?= $field('price_unit') === 'month' ? 'selected' : '' ?>><?= e(t('price.month')) ?></option><option value="course" <?= $field('price_unit') === 'course' ? 'selected' : '' ?>><?= e(t('price.course')) ?></option><option value="negotiable" <?= $field('price_unit') === 'negotiable' ? 'selected' : '' ?>><?= e(t('price.negotiable')) ?></option></select></label>
                </div>
                <div class="step-actions end"><button class="button button-primary" type="button" data-next-step="2"><?= e(t('register.next')) ?> →</button></div>
            </section>

            <section class="register-step" data-step-panel="2" hidden>
                <div class="step-header"><span>02</span><div><h2><?= e(t('register.step2_title')) ?></h2><p><?= e(t('register.step2_text')) ?></p></div></div>
                <div class="register-fields">
                    <label><span><?= e(t('search.category')) ?> *</span><select name="category" data-category-select required><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['categories'] as $item): ?><option value="<?= e($item) ?>" <?= $field('category') === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select><?php if ($error('category')): ?><small class="field-error"><?= e($error('category')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('register.profession')) ?> *</span><input name="profession_ka" value="<?= e($field('profession_ka')) ?>" maxlength="190" placeholder="<?= e(t('register.profession_placeholder')) ?>" list="profession-options" data-profession-input required><datalist id="profession-options" data-profession-options><?php foreach ($categorySubcategories as $category => $items): ?><?php foreach ($items as $item): ?><option value="<?= e($item) ?>" data-category="<?= e($category) ?>"><?php endforeach; ?><?php endforeach; ?><?php foreach ($uncategorizedSubcategories as $item): ?><option value="<?= e($item) ?>" data-category=""><?php endforeach; ?></datalist><?php if ($error('profession_ka')): ?><small class="field-error"><?= e($error('profession_ka')) ?></small><?php endif; ?></label>
                    <label class="wide"><span><?= e(t('teacher.about')) ?> *</span><textarea name="bio_ka" rows="7" minlength="30" maxlength="8000" placeholder="<?= e(t('register.bio_placeholder')) ?>" required><?= e($field('bio_ka')) ?></textarea><small><?= e(t('register.bio_hint')) ?></small><?php if ($error('bio_ka')): ?><small class="field-error"><?= e($error('bio_ka')) ?></small><?php endif; ?></label>
                    <label class="wide"><span><?= e(t('teacher.languages')) ?></span><input name="languages" value="<?= e($field('languages')) ?>" maxlength="255" placeholder="<?= e(t('register.languages_placeholder')) ?>"></label>
                    <label class="wide photo-field"><span><?= e(t('register.photo')) ?></span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input><span class="photo-picker"><span class="photo-placeholder" data-photo-placeholder>📷 <b><?= e(t('register.photo_choose')) ?></b><small><?= e(t('register.photo_help', ['size' => (int) ($config['media']['max_upload_bytes'] / 1024 / 1024)])) ?></small></span><img data-photo-preview alt="<?= e(t('register.photo_preview')) ?>" hidden></span></label>
                </div>
                <div class="step-actions"><button class="button button-muted" type="button" data-prev-step="1">← <?= e(t('common.back')) ?></button><button class="button button-primary" type="button" data-next-step="3"><?= e(t('register.next')) ?> →</button></div>
            </section>

            <section class="register-step" data-step-panel="3" hidden>
                <div class="step-header"><span>03</span><div><h2><?= e(t('register.step3_title')) ?></h2><p><?= e(t('register.step3_text')) ?></p></div></div>
                <div class="register-fields">
                    <label class="wide"><span><?= e(t('teacher.phone')) ?> *</span><input type="tel" name="phone" value="<?= e($field('phone')) ?>" maxlength="50" autocomplete="tel" placeholder="5XX XXX XXX" required><?php if ($error('phone')): ?><small class="field-error"><?= e($error('phone')) ?></small><?php endif; ?></label>
                    <div class="wide info-box"><strong><?= e(t('register.review_title')) ?></strong><p><?= e(t('register.review_text')) ?></p></div>
                    <label class="wide consent-check"><input type="checkbox" name="consent" value="1" <?= $checked('consent') ? 'checked' : '' ?> required><span><?= e(t('register.consent')) ?> <a href="/terms" target="_blank"><?= e(t('legal.terms')) ?></a> / <a href="/privacy" target="_blank"><?= e(t('legal.privacy')) ?></a>.</span><?php if ($error('consent')): ?><small class="field-error"><?= e($error('consent')) ?></small><?php endif; ?></label>
                </div>
                <div class="step-actions"><button class="button button-muted" type="button" data-prev-step="2">← <?= e(t('common.back')) ?></button><button class="button button-primary" type="submit" data-register-submit><?= e(t('register.submit')) ?> ✓</button></div>
            </section>
        </form>
    </div>
</section>

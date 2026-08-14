<?php
$old = $old ?? [];
$errors = $errors ?? [];
$field = static fn (string $key): string => (string) ($old[$key] ?? '');
$checked = static fn (string $key): bool => !empty($old[$key]);
$error = static fn (string $key): string => (string) ($errors[$key] ?? '');
$categorySubcategories = $options['category_subcategories'] ?? [];
$categorizedSubcategories = [];
foreach ($categorySubcategories as $items) {
    foreach ($items as $item) $categorizedSubcategories[(string) $item] = true;
}
$uncategorizedSubcategories = array_values(array_filter(
    $options['subcategories'] ?? [],
    static fn (string $item): bool => !isset($categorizedSubcategories[$item])
));
?>
<header class="register-hero mentor-form-hero">
    <div class="container narrow">
        <span class="section-kicker light"><?= e(t('nav.match')) ?></span>
        <h1><?= e(t('mentor.form_title')) ?></h1>
        <p><?= e(t('mentor.form_subtitle')) ?></p>
        <div class="steps-indicator" aria-label="<?= e(t('mentor.steps_label')) ?>">
            <span class="step-dot active" data-step-dot="1">1</span><i></i>
            <span class="step-dot" data-step-dot="2">2</span><i></i>
            <span class="step-dot" data-step-dot="3">3</span>
        </div>
    </div>
</header>

<section class="section register-section">
    <div class="container narrow">
        <?php if ($error('form')): ?><div class="form-error-summary" role="alert"><?= e($error('form')) ?></div><?php endif; ?>
        <form class="register-form" action="/mentor-requests" method="post" data-registration-form data-taxonomy-form data-location-form novalidate>
            <?= csrf_field() ?>
            <div class="register-progress"><span data-register-progress></span></div>

            <section class="register-step active" data-step-panel="1">
                <div class="step-header"><span>01</span><div><h2><?= e(t('mentor.step1_title')) ?></h2><p><?= e(t('mentor.step1_text')) ?></p></div></div>
                <div class="register-fields">
                    <label class="wide"><span><?= e(t('mentor.contact_name')) ?> *</span><input name="name" value="<?= e($field('name')) ?>" minlength="2" maxlength="190" autocomplete="name" required><small><?= e(t('mentor.guardian_hint')) ?></small><?php if ($error('name')): ?><small class="field-error"><?= e($error('name')) ?></small><?php endif; ?></label>
                    <label class="wide"><span><?= e(t('mentor.learner_group')) ?> *</span><select name="learner_group" required><option value=""><?= e(t('search.any')) ?></option><?php foreach (['child', 'school_student', 'university_student', 'adult'] as $group): ?><option value="<?= e($group) ?>" <?= $field('learner_group') === $group ? 'selected' : '' ?>><?= e(mentor_learner_group_label($group)) ?></option><?php endforeach; ?></select><?php if ($error('learner_group')): ?><small class="field-error"><?= e($error('learner_group')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('search.region')) ?> *</span><select name="region" data-region-select required><option value=""><?= e(t('search.any')) ?></option><?php foreach ($options['regions'] as $item): ?><option value="<?= e($item) ?>" <?= $field('region') === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select><?php if ($error('region')): ?><small class="field-error"><?= e($error('region')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('teacher.settlement')) ?> *</span><select name="settlement" data-settlement-select required><?= settlement_option_tags($options, $field('region'), $field('settlement'), t('search.any')) ?></select><?php if ($error('settlement')): ?><small class="field-error"><?= e($error('settlement')) ?></small><?php endif; ?></label>
                    <fieldset class="wide compact-fieldset"><legend><?= e(t('mentor.preferred_format')) ?> *</legend><label class="check-card"><input type="checkbox" name="format_in_person" value="1" <?= $checked('format_in_person') ? 'checked' : '' ?>><span><?= e(t('search.in_person')) ?></span></label><label class="check-card"><input type="checkbox" name="format_online" value="1" <?= $checked('format_online') ? 'checked' : '' ?>><span><?= e(t('search.online')) ?></span></label><?php if ($error('format')): ?><small class="field-error wide"><?= e($error('format')) ?></small><?php endif; ?></fieldset>
                </div>
                <div class="step-actions end"><button class="button button-primary" type="button" data-next-step="2"><?= e(t('register.next')) ?> →</button></div>
            </section>

            <section class="register-step" data-step-panel="2" hidden>
                <div class="step-header"><span>02</span><div><h2><?= e(t('mentor.step2_title')) ?></h2><p><?= e(t('mentor.step2_text')) ?></p></div></div>
                <div class="register-fields">
                    <label><span><?= e(t('search.category')) ?> *</span><select name="category" data-category-select required><?= category_option_tags($options, $field('category'), t('search.any')) ?></select><?php if ($error('category')): ?><small class="field-error"><?= e($error('category')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('mentor.subject')) ?> *</span><input name="subject" value="<?= e($field('subject')) ?>" maxlength="190" placeholder="<?= e(t('mentor.subject_placeholder')) ?>" list="mentor-subject-options" data-profession-input required><datalist id="mentor-subject-options" data-profession-options><?php foreach ($categorySubcategories as $category => $items): ?><?php foreach ($items as $item): ?><option value="<?= e($item) ?>" data-category="<?= e($category) ?>"><?php endforeach; ?><?php endforeach; ?><?php foreach ($uncategorizedSubcategories as $item): ?><option value="<?= e($item) ?>" data-category=""><?php endforeach; ?></datalist><?php if ($error('subject')): ?><small class="field-error"><?= e($error('subject')) ?></small><?php endif; ?></label>
                    <label class="wide"><span><?= e(t('mentor.current_level')) ?> *</span><input name="current_level" value="<?= e($field('current_level')) ?>" maxlength="190" placeholder="<?= e(t('mentor.level_placeholder')) ?>" required><?php if ($error('current_level')): ?><small class="field-error"><?= e($error('current_level')) ?></small><?php endif; ?></label>
                    <label class="wide"><span><?= e(t('mentor.learning_goal')) ?> *</span><textarea name="learning_goal" rows="5" minlength="10" maxlength="4000" placeholder="<?= e(t('mentor.goal_placeholder')) ?>" required><?= e($field('learning_goal')) ?></textarea><?php if ($error('learning_goal')): ?><small class="field-error"><?= e($error('learning_goal')) ?></small><?php endif; ?></label>
                    <label class="wide"><span><?= e(t('mentor.availability')) ?> *</span><textarea name="availability" rows="3" minlength="3" maxlength="1000" placeholder="<?= e(t('mentor.availability_placeholder')) ?>" required><?= e($field('availability')) ?></textarea><?php if ($error('availability')): ?><small class="field-error"><?= e($error('availability')) ?></small><?php endif; ?></label>
                    <label class="wide"><span><?= e(t('mentor.desired_start')) ?></span><input name="desired_start" value="<?= e($field('desired_start')) ?>" maxlength="190" placeholder="<?= e(t('mentor.start_placeholder')) ?>"></label>
                    <label><span><?= e(t('mentor.budget')) ?></span><input type="number" name="budget_from" value="<?= e($field('budget_from')) ?>" min="0" max="99999999.99" step="0.01" data-price-input><?php if ($error('budget_from')): ?><small class="field-error"><?= e($error('budget_from')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('price.type')) ?></span><select name="budget_unit" data-price-unit><option value="negotiable" <?= $field('budget_unit') === 'negotiable' || $field('budget_unit') === '' ? 'selected' : '' ?>><?= e(t('price.negotiable')) ?></option><option value="hour" <?= $field('budget_unit') === 'hour' ? 'selected' : '' ?>><?= e(t('price.hour')) ?></option><option value="lesson" <?= $field('budget_unit') === 'lesson' ? 'selected' : '' ?>><?= e(t('price.lesson')) ?></option><option value="month" <?= $field('budget_unit') === 'month' ? 'selected' : '' ?>><?= e(t('price.month')) ?></option><option value="course" <?= $field('budget_unit') === 'course' ? 'selected' : '' ?>><?= e(t('price.course')) ?></option></select></label>
                </div>
                <div class="step-actions"><button class="button button-muted" type="button" data-prev-step="1">← <?= e(t('common.back')) ?></button><button class="button button-primary" type="button" data-next-step="3"><?= e(t('register.next')) ?> →</button></div>
            </section>

            <section class="register-step" data-step-panel="3" hidden>
                <div class="step-header"><span>03</span><div><h2><?= e(t('mentor.step3_title')) ?></h2><p><?= e(t('mentor.step3_text')) ?></p></div></div>
                <div class="register-fields">
                    <label><span><?= e(t('mentor.contact_phone')) ?> *</span><input type="tel" name="phone" value="<?= e($field('phone')) ?>" maxlength="50" autocomplete="tel" placeholder="5XX XXX XXX" required><?php if ($error('phone')): ?><small class="field-error"><?= e($error('phone')) ?></small><?php endif; ?></label>
                    <label><span><?= e(t('mentor.email')) ?></span><input type="email" name="email" value="<?= e($field('email')) ?>" maxlength="190" autocomplete="email" placeholder="name@example.com"><?php if ($error('email')): ?><small class="field-error"><?= e($error('email')) ?></small><?php endif; ?></label>
                    <label class="wide"><span><?= e(t('mentor.additional_details')) ?></span><textarea name="details" rows="5" maxlength="4000" placeholder="<?= e(t('mentor.details_placeholder')) ?>"><?= e($field('details')) ?></textarea></label>
                    <div class="wide info-box"><strong><?= e(t('mentor.review_title')) ?></strong><p><?= e(t('mentor.review_text')) ?></p><p><?= e(t('mentor.public_contact_note')) ?></p></div>
                    <label class="wide consent-check"><input type="checkbox" name="consent" value="1" <?= $checked('consent') ? 'checked' : '' ?> required><span><?= e(t('mentor.consent')) ?> <a href="/terms" target="_blank"><?= e(t('legal.terms')) ?></a> / <a href="/privacy" target="_blank"><?= e(t('legal.privacy')) ?></a>.</span><?php if ($error('consent')): ?><small class="field-error"><?= e($error('consent')) ?></small><?php endif; ?></label>
                </div>
                <div class="step-actions"><button class="button button-muted" type="button" data-prev-step="2">← <?= e(t('common.back')) ?></button><button class="button button-primary" type="submit" data-register-submit><?= e(t('mentor.submit')) ?> ✓</button></div>
            </section>
        </form>
    </div>
</section>

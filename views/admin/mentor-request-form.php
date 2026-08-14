<?php
$row = $request ?? [];
$value = static fn (string $key, string $default = ''): string => (string) ($row[$key] ?? $default);
$isChecked = static fn (string $key): bool => !empty($row[$key]);
$categorySubcategories = $options['category_subcategories'] ?? [];
?>
<header class="page-hero compact"><div class="container"><div class="admin-title-row"><div><span class="section-kicker">Admin</span><h1><?= e($request ? t('mentor.admin_edit') : t('mentor.admin_add')) ?></h1></div><a class="button button-muted" href="/admin/mentor-requests">← <?= e(t('common.back')) ?></a></div><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>
<section class="section admin-section"><div class="container"><form class="admin-panel teacher-form" action="/admin/mentor-requests/save" method="post" data-taxonomy-form data-location-form>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
    <fieldset><legend><?= e(t('mentor.step1_title')) ?></legend>
        <label class="wide"><span><?= e(t('mentor.contact_name')) ?></span><input name="name" value="<?= e($value('name')) ?>" maxlength="190" required></label>
        <label><span><?= e(t('mentor.learner_group')) ?></span><select name="learner_group" required><option value="">—</option><?php foreach (['child', 'school_student', 'university_student', 'adult'] as $group): ?><option value="<?= e($group) ?>" <?= $value('learner_group') === $group ? 'selected' : '' ?>><?= e(mentor_learner_group_label($group)) ?></option><?php endforeach; ?></select></label>
        <label><span><?= e(t('search.category')) ?></span><select name="category" data-category-select required><?= category_option_tags($options, $value('category')) ?></select></label>
        <label><span><?= e(t('mentor.subject')) ?></span><input name="subject" value="<?= e($value('subject')) ?>" maxlength="190" list="admin-mentor-subjects" required><datalist id="admin-mentor-subjects" data-profession-options><?php foreach ($categorySubcategories as $category => $items): ?><?php foreach ($items as $item): ?><option value="<?= e($item) ?>" data-category="<?= e($category) ?>"><?php endforeach; ?><?php endforeach; ?></datalist></label>
        <label><span><?= e(t('mentor.current_level')) ?></span><input name="current_level" value="<?= e($value('current_level')) ?>" maxlength="190"></label>
        <label><span><?= e(t('search.region')) ?></span><select name="region" data-region-select><option value="">—</option><?php foreach ($options['regions'] as $item): ?><option value="<?= e($item) ?>" <?= $value('region') === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
        <label><span><?= e(t('teacher.settlement')) ?></span><select name="settlement" data-settlement-select><?= settlement_option_tags($options, $value('region'), $value('settlement'), '—') ?></select></label>
        <label class="check"><input type="checkbox" name="format_in_person" value="1" <?= $isChecked('format_in_person') ? 'checked' : '' ?>> <?= e(t('search.in_person')) ?></label>
        <label class="check"><input type="checkbox" name="format_online" value="1" <?= $isChecked('format_online') ? 'checked' : '' ?>> <?= e(t('search.online')) ?></label>
    </fieldset>
    <fieldset><legend><?= e(t('mentor.step2_title')) ?></legend>
        <label class="wide"><span><?= e(t('mentor.learning_goal')) ?></span><textarea name="learning_goal" rows="5" maxlength="4000"><?= e($value('learning_goal')) ?></textarea></label>
        <label class="wide"><span><?= e(t('mentor.availability')) ?></span><textarea name="availability" rows="3" maxlength="1000"><?= e($value('availability')) ?></textarea></label>
        <label class="wide"><span><?= e(t('mentor.desired_start')) ?></span><input name="desired_start" value="<?= e($value('desired_start')) ?>" maxlength="190"></label>
        <label><span><?= e(t('mentor.budget')) ?></span><input type="number" name="budget_from" value="<?= e($value('budget_from')) ?>" min="0" max="99999999.99" step="0.01"></label>
        <label><span><?= e(t('price.type')) ?></span><select name="budget_unit"><?php foreach (['negotiable', 'hour', 'lesson', 'month', 'course'] as $unit): ?><option value="<?= e($unit) ?>" <?= $value('budget_unit', 'negotiable') === $unit ? 'selected' : '' ?>><?= e(t('price.' . $unit)) ?></option><?php endforeach; ?></select></label>
        <label class="wide"><span><?= e(t('mentor.additional_details')) ?></span><textarea name="details" rows="5" maxlength="4000"><?= e($value('details')) ?></textarea></label>
    </fieldset>
    <fieldset><legend><?= e(t('mentor.step3_title')) ?></legend>
        <label><span><?= e(t('mentor.contact_phone')) ?></span><input name="phone" value="<?= e($value('phone')) ?>" maxlength="50" required></label>
        <label><span><?= e(t('mentor.email')) ?></span><input type="email" name="email" value="<?= e($value('email')) ?>" maxlength="190"></label>
        <label class="wide"><span>Slug</span><input name="slug" value="<?= e($value('slug')) ?>" maxlength="190"></label>
        <label class="wide"><span>Status</span><select name="status"><option value="draft" <?= $value('status', 'draft') === 'draft' ? 'selected' : '' ?>><?= e(t('status.pending')) ?></option><option value="published" <?= $value('status') === 'published' ? 'selected' : '' ?>><?= e(t('status.published')) ?></option><option value="archived" <?= $value('status') === 'archived' ? 'selected' : '' ?>><?= e(t('status.rejected')) ?></option></select></label>
    </fieldset>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t('common.save')) ?></button><a class="button button-muted" href="/admin/mentor-requests"><?= e(t('common.cancel')) ?></a></div>
</form></div></section>

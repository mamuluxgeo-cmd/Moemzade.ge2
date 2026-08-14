<?php
$teacher = $teacher ?? [];
$options = $options ?? ['categories' => [], 'regions' => [], 'settlements' => [], 'region_settlements' => [], 'subcategories' => [], 'category_subcategories' => []];
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
$currentProfession = trim((string) ($teacher['profession_ka'] ?? ''));
if ($currentProfession !== '' && !isset($categorizedSubcategories[$currentProfession]) && !in_array($currentProfession, $uncategorizedSubcategories, true)) {
    $uncategorizedSubcategories[] = $currentProfession;
}
?>
<header class="page-hero compact"><div class="container"><span class="section-kicker">Admin</span><h1><?= $teacher ? e(localized($teacher, 'name')) : e(t('admin.add_teacher')) ?></h1><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>
<section class="section admin-section"><div class="container"><form class="admin-panel teacher-form" action="/admin/teachers/save" method="post" enctype="multipart/form-data" data-taxonomy-form data-location-form>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($teacher['id'] ?? 0) ?>">
    <fieldset><legend>ქართული</legend><label><span>სახელი</span><input name="name_ka" value="<?= e($teacher['name_ka'] ?? '') ?>" required></label><label><span>პროფესია / საგანი</span><input name="profession_ka" value="<?= e($teacher['profession_ka'] ?? '') ?>" list="admin-profession-options" data-profession-input><datalist id="admin-profession-options" data-profession-options><?php foreach ($categorySubcategories as $category => $items): ?><?php foreach ($items as $item): ?><option value="<?= e($item) ?>" data-category="<?= e($category) ?>"><?php endforeach; ?><?php endforeach; ?><?php foreach ($uncategorizedSubcategories as $item): ?><option value="<?= e($item) ?>" data-category=""><?php endforeach; ?></datalist></label><label class="wide"><span>აღწერა</span><textarea name="bio_ka" rows="5"><?= e($teacher['bio_ka'] ?? '') ?></textarea></label></fieldset>
    <fieldset><legend>English</legend><label><span>Name</span><input name="name_en" value="<?= e($teacher['name_en'] ?? '') ?>"></label><label><span>Profession</span><input name="profession_en" value="<?= e($teacher['profession_en'] ?? '') ?>"></label><label class="wide"><span>About</span><textarea name="bio_en" rows="5"><?= e($teacher['bio_en'] ?? '') ?></textarea></label></fieldset>
    <fieldset><legend>Русский</legend><label><span>Имя</span><input name="name_ru" value="<?= e($teacher['name_ru'] ?? '') ?>"></label><label><span>Профессия</span><input name="profession_ru" value="<?= e($teacher['profession_ru'] ?? '') ?>"></label><label class="wide"><span>Описание</span><textarea name="bio_ru" rows="5"><?= e($teacher['bio_ru'] ?? '') ?></textarea></label></fieldset>
    <fieldset><legend>კატალოგი</legend>
        <label><span>Slug (optional)</span><input name="slug" value="<?= e($teacher['slug'] ?? '') ?>"></label>
        <label><span><?= e(t('search.category')) ?></span><select name="category" data-category-select required><?= category_option_tags($options, (string) ($teacher['category'] ?? ''), t('search.any')) ?></select></label>
        <label><span><?= e(t('search.region')) ?></span><select name="region" data-region-select><option value=""><?= e(t('search.any')) ?></option><?php if (!empty($teacher['region']) && !in_array((string) $teacher['region'], $options['regions'], true)): ?><option value="<?= e($teacher['region']) ?>" selected><?= e($teacher['region']) ?></option><?php endif; ?><?php foreach ($options['regions'] as $item): ?><option value="<?= e($item) ?>" <?= ($teacher['region'] ?? '') === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></label>
        <label><span><?= e(t('teacher.settlement')) ?></span><select name="settlement" data-settlement-select><?= settlement_option_tags($options, (string) ($teacher['region'] ?? ''), (string) ($teacher['settlement'] ?? ''), t('search.any')) ?></select></label>
        <label class="wide"><span><?= e(t('teacher.languages')) ?> (comma separated)</span><input name="languages" value="<?= e($teacher['languages'] ?? '') ?>"></label>
        <label class="check"><input type="checkbox" name="format_online" value="1" <?= !empty($teacher['format_online']) ? 'checked' : '' ?>><span><?= e(t('search.online')) ?></span></label>
        <label class="check"><input type="checkbox" name="format_in_person" value="1" <?= !isset($teacher['format_in_person']) || !empty($teacher['format_in_person']) ? 'checked' : '' ?>><span><?= e(t('search.in_person')) ?></span></label>
    </fieldset>
    <fieldset><legend>კონტაქტი და ფასი</legend>
        <label><span><?= e(t('teacher.phone')) ?></span><input type="tel" name="phone" value="<?= e($teacher['phone'] ?? '') ?>"></label>
        <label><span><?= e(t('teacher.price')) ?> (₾)</span><input type="number" min="0" step="0.01" name="price_from" value="<?= e($teacher['price_from'] ?? '') ?>"></label>
        <label><span><?= e(t('price.type')) ?></span><select name="price_unit"><option value="hour" <?= ($teacher['price_unit'] ?? 'hour') === 'hour' ? 'selected' : '' ?>><?= e(t('price.hour')) ?></option><option value="month" <?= ($teacher['price_unit'] ?? '') === 'month' ? 'selected' : '' ?>><?= e(t('price.month')) ?></option><option value="course" <?= ($teacher['price_unit'] ?? '') === 'course' ? 'selected' : '' ?>><?= e(t('price.course')) ?></option><option value="negotiable" <?= ($teacher['price_unit'] ?? '') === 'negotiable' ? 'selected' : '' ?>><?= e(t('price.negotiable')) ?></option><option value="lesson" <?= ($teacher['price_unit'] ?? '') === 'lesson' ? 'selected' : '' ?>><?= e(t('price.lesson')) ?></option></select></label>
        <label><span>Facebook URL</span><input type="url" name="facebook_url" value="<?= e($teacher['facebook_url'] ?? '') ?>"></label>
        <label><span>Instagram URL</span><input type="url" name="instagram_url" value="<?= e($teacher['instagram_url'] ?? '') ?>"></label>
    </fieldset>
    <fieldset><legend>მედია და გამოქვეყნება</legend>
        <label class="wide"><span>Photo (JPEG/PNG/WebP, max <?= (int) ($config['media']['max_upload_bytes'] / 1024 / 1024) ?> MB)</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small>იქმნება 1600px, 720px და 360px WebP ვერსიები; EXIF/GPS მონაცემები იშლება.</small></label>
        <label><span>Status</span><select name="status"><option value="draft" <?= ($teacher['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>><?= e(t('status.pending')) ?></option><option value="published" <?= ($teacher['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= e(t('status.published')) ?></option><option value="archived" <?= ($teacher['status'] ?? '') === 'archived' ? 'selected' : '' ?>><?= e(t('status.rejected')) ?></option></select></label>
    </fieldset>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t('common.save')) ?></button><a class="button button-muted" href="/admin/teachers"><?= e(t('common.cancel')) ?></a></div>
</form></div></section>

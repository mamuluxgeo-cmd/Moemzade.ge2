<?php $teacher = $teacher ?? []; ?>
<header class="page-hero compact"><div class="container"><span class="section-kicker">Admin</span><h1><?= $teacher ? e(localized($teacher, 'name')) : e(t('admin.add_teacher')) ?></h1><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>
<section class="section admin-section"><div class="container"><form class="admin-panel teacher-form" action="/admin/teachers/save" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($teacher['id'] ?? 0) ?>">
    <fieldset><legend>ქართული</legend><label><span>სახელი</span><input name="name_ka" value="<?= e($teacher['name_ka'] ?? '') ?>" required></label><label><span>პროფესია / საგანი</span><input name="profession_ka" value="<?= e($teacher['profession_ka'] ?? '') ?>"></label><label class="wide"><span>აღწერა</span><textarea name="bio_ka" rows="5"><?= e($teacher['bio_ka'] ?? '') ?></textarea></label></fieldset>
    <fieldset><legend>English</legend><label><span>Name</span><input name="name_en" value="<?= e($teacher['name_en'] ?? '') ?>"></label><label><span>Profession</span><input name="profession_en" value="<?= e($teacher['profession_en'] ?? '') ?>"></label><label class="wide"><span>About</span><textarea name="bio_en" rows="5"><?= e($teacher['bio_en'] ?? '') ?></textarea></label></fieldset>
    <fieldset><legend>Русский</legend><label><span>Имя</span><input name="name_ru" value="<?= e($teacher['name_ru'] ?? '') ?>"></label><label><span>Профессия</span><input name="profession_ru" value="<?= e($teacher['profession_ru'] ?? '') ?>"></label><label class="wide"><span>Описание</span><textarea name="bio_ru" rows="5"><?= e($teacher['bio_ru'] ?? '') ?></textarea></label></fieldset>
    <fieldset><legend>კატალოგი</legend>
        <label><span>Slug (optional)</span><input name="slug" value="<?= e($teacher['slug'] ?? '') ?>"></label>
        <label><span><?= e(t('search.category')) ?></span><input name="category" value="<?= e($teacher['category'] ?? '') ?>" required></label>
        <label><span><?= e(t('search.region')) ?></span><input name="region" value="<?= e($teacher['region'] ?? '') ?>"></label>
        <label><span><?= e(t('teacher.settlement')) ?></span><input name="settlement" value="<?= e($teacher['settlement'] ?? '') ?>"></label>
        <label class="wide"><span><?= e(t('teacher.languages')) ?> (comma separated)</span><input name="languages" value="<?= e($teacher['languages'] ?? '') ?>"></label>
        <label class="check"><input type="checkbox" name="format_online" value="1" <?= !empty($teacher['format_online']) ? 'checked' : '' ?>><span><?= e(t('search.online')) ?></span></label>
        <label class="check"><input type="checkbox" name="format_in_person" value="1" <?= !isset($teacher['format_in_person']) || !empty($teacher['format_in_person']) ? 'checked' : '' ?>><span><?= e(t('search.in_person')) ?></span></label>
    </fieldset>
    <fieldset><legend>კონტაქტი და ფასი</legend>
        <label><span><?= e(t('teacher.phone')) ?></span><input type="tel" name="phone" value="<?= e($teacher['phone'] ?? '') ?>"></label>
        <label><span><?= e(t('teacher.price')) ?> (₾)</span><input type="number" min="0" step="0.01" name="price_from" value="<?= e($teacher['price_from'] ?? '') ?>"></label>
        <label><span>Price unit</span><select name="price_unit"><option value="hour" <?= ($teacher['price_unit'] ?? 'hour') === 'hour' ? 'selected' : '' ?>>hour</option><option value="lesson" <?= ($teacher['price_unit'] ?? '') === 'lesson' ? 'selected' : '' ?>>lesson</option><option value="course" <?= ($teacher['price_unit'] ?? '') === 'course' ? 'selected' : '' ?>>course</option></select></label>
        <label><span>Facebook URL</span><input type="url" name="facebook_url" value="<?= e($teacher['facebook_url'] ?? '') ?>"></label>
        <label><span>Instagram URL</span><input type="url" name="instagram_url" value="<?= e($teacher['instagram_url'] ?? '') ?>"></label>
    </fieldset>
    <fieldset><legend>მედია და გამოქვეყნება</legend>
        <label class="wide"><span>Photo (JPEG/PNG/WebP, max <?= (int) ($config['media']['max_upload_bytes'] / 1024 / 1024) ?> MB)</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small>იქმნება 1600px, 720px და 360px WebP ვერსიები; EXIF/GPS მონაცემები იშლება.</small></label>
        <label><span>Status</span><select name="status"><option value="draft" <?= ($teacher['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>draft</option><option value="published" <?= ($teacher['status'] ?? '') === 'published' ? 'selected' : '' ?>>published</option><option value="archived" <?= ($teacher['status'] ?? '') === 'archived' ? 'selected' : '' ?>>archived</option></select></label>
    </fieldset>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t('common.save')) ?></button><a class="button button-muted" href="/admin/teachers"><?= e(t('common.cancel')) ?></a></div>
</form></div></section>


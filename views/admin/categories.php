<?php
$rootCategories = [];
$childrenByParent = [];
foreach ($categories as $category) {
    $parentId = isset($category['parent_id']) ? (int) $category['parent_id'] : 0;
    if ($parentId > 0) {
        $childrenByParent[$parentId][] = $category;
    } else {
        $rootCategories[] = $category;
    }
}

$renderCategory = static function (array $category, bool $isChild = false) use (&$renderCategory, $childrenByParent, $rootCategories): void {
    $id = (int) $category['id'];
    $children = $childrenByParent[$id] ?? [];
    $hasChildren = $children !== [];
    $teacherCount = (int) ($category['teacher_count'] ?? 0);
    $requestCount = (int) ($category['request_count'] ?? 0);
    $childCount = (int) ($category['child_count'] ?? count($children));
    $deleteBlocked = $teacherCount + $requestCount + $childCount > 0;
    ?>
    <article class="taxonomy-tree-item<?= $isChild ? ' is-child' : '' ?>" data-category-item data-category-id="<?= $id ?>">
        <div class="taxonomy-tree-row" data-category-row>
            <button class="taxonomy-drag-handle" type="button" draggable="true" data-drag-handle aria-label="<?= e($category['name']) ?> — გადაადგილება" title="გადაათრიეთ გადასაადგილებლად">⋮⋮</button>
            <button class="taxonomy-collapse" type="button" data-category-collapse aria-expanded="true" aria-label="ქვესფეროების გაშლა ან დაკეცვა" <?= $hasChildren ? '' : 'disabled' ?>>⌄</button>
            <span class="taxonomy-tree-image"><?php if (!empty($category['image_url'])): ?><img src="<?= e($category['image_url']) ?>" alt=""><?php else: ?><span aria-hidden="true">▦</span><?php endif; ?></span>
            <span class="taxonomy-tree-copy"><strong><?= e($category['name']) ?></strong><small><?= $isChild ? 'ქვესფერო' : 'მთავარი სფერო' ?> · <?= $teacherCount ?> მასწავლებელი · <?= $requestCount ?> განცხადება<?php if ($childCount > 0): ?> · <?= $childCount ?> ქვესფერო<?php endif; ?></small></span>
            <span class="taxonomy-move-actions" aria-label="გადაადგილების ღილაკები">
                <button type="button" data-move-up title="ზემოთ">↑</button>
                <button type="button" data-move-down title="ქვემოთ">↓</button>
                <button type="button" data-promote title="მთავარ დონეზე გადატანა" <?= $isChild ? '' : 'hidden' ?>>←</button>
                <button type="button" data-demote title="წინა სფეროში ჩაშლა" <?= $isChild ? 'hidden' : '' ?> <?= $hasChildren ? 'disabled' : '' ?>>→</button>
            </span>
            <button class="button button-muted taxonomy-edit-toggle" type="button" data-category-edit aria-expanded="false">რედაქტირება</button>
        </div>

        <div class="taxonomy-edit-panel" data-category-editor hidden>
            <form action="/admin/categories/<?= $id ?>" method="post" class="taxonomy-row-form">
                <?= csrf_field() ?>
                <label class="taxonomy-name-field"><span>სახელი</span><input name="name" value="<?= e($category['name']) ?>" maxlength="120" required></label>
                <label><span>მშობელი სფერო</span>
                    <?php if ($hasChildren): ?>
                        <select disabled><option>მთავარი დონე</option></select><input type="hidden" name="parent_id" value=""><small>ჩაშლამდე ჯერ ქვესფეროები გადაიტანეთ.</small>
                    <?php else: ?>
                        <select name="parent_id" data-parent-select><option value="">მთავარი დონე</option><?php foreach ($rootCategories as $root): ?><?php if ((int) $root['id'] !== $id): ?><option value="<?= (int) $root['id'] ?>" <?= (int) ($category['parent_id'] ?? 0) === (int) $root['id'] ? 'selected' : '' ?>><?= e($root['name']) ?></option><?php endif; ?><?php endforeach; ?></select>
                    <?php endif; ?>
                </label>
                <label><span>რიგითობა</span><input type="number" name="sort_order" data-sort-order value="<?= (int) $category['sort_order'] ?>" min="0" max="1000000" required></label>
                <button class="button button-primary" type="submit">ცვლილების შენახვა</button>
            </form>

            <div class="category-image-admin">
                <div class="category-image-preview">
                    <?php if (!empty($category['image_url'])): ?><img src="<?= e($category['image_url']) ?>" alt="<?= e($category['name']) ?>"><?php else: ?><span>ფოტო არ არის</span><?php endif; ?>
                </div>
                <form action="/admin/categories/<?= $id ?>/image" method="post" enctype="multipart/form-data" class="category-image-form">
                    <?= csrf_field() ?>
                    <label><span>კატეგორიის ფოტო</span><input type="file" name="category_image" accept="image/jpeg,image/png,image/webp" required><small>800 × 600 px (4:3), JPG/PNG/WebP, მაქს. 2 MB.</small></label>
                    <button class="button button-muted" type="submit"><?= !empty($category['image_url']) ? 'ფოტოს შეცვლა' : 'ფოტოს ატვირთვა' ?></button>
                </form>
                <?php if (!empty($category['image_url'])): ?><form action="/admin/categories/<?= $id ?>/image" method="post" onsubmit="return confirm('წავშალოთ კატეგორიის ფოტო?')"><?= csrf_field() ?><input type="hidden" name="remove_image" value="1"><button class="button category-image-remove" type="submit">ფოტოს წაშლა</button></form><?php endif; ?>
            </div>

            <form action="/admin/categories/<?= $id ?>/delete" method="post" class="taxonomy-delete-form" onsubmit="return confirm('არჩეული სფეროს წაშლა ნამდვილად გსურთ?')">
                <?= csrf_field() ?>
                <p><?php if ($deleteBlocked): ?>წაშლამდე გადაიტანეთ <?= $teacherCount ?> მასწავლებელი, <?= $requestCount ?> განცხადება და <?= $childCount ?> ქვესფერო.<?php else: ?>სფერო ცარიელია და მისი უსაფრთხოდ წაშლა შესაძლებელია.<?php endif; ?></p>
                <button class="button taxonomy-delete-button" type="submit" <?= $deleteBlocked ? 'disabled' : '' ?>>სფეროს წაშლა</button>
            </form>
        </div>

        <div class="taxonomy-tree-children" data-category-children data-category-container data-parent-id="<?= $id ?>">
            <?php foreach ($children as $child): ?><?php $renderCategory($child, true); ?><?php endforeach; ?>
        </div>
    </article>
    <?php
};
?>

<header class="page-hero compact"><div class="container"><span class="section-kicker">Admin</span><h1><?= e(t('admin.categories')) ?></h1><p>სფეროების რიგითობა და ჩაშლა ავტომატურად აისახება რეგისტრაციაში, ძებნასა და განცხადების ფორმებში.</p><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>

<section class="section admin-section"><div class="container taxonomy-admin-grid">
    <aside class="admin-panel taxonomy-create-card">
        <h2>ახალი სფერო</h2>
        <form action="/admin/categories" method="post" class="taxonomy-create-form">
            <?= csrf_field() ?>
            <label><span>სფეროს სახელი</span><input name="name" maxlength="120" placeholder="მაგ. ბიზნესი და ფინანსები" required></label>
            <label><span>მშობელი სფერო</span><select name="parent_id"><option value="">მთავარი დონე</option><?php foreach ($rootCategories as $root): ?><option value="<?= (int) $root['id'] ?>"><?= e($root['name']) ?></option><?php endforeach; ?></select><small>აირჩიეთ მხოლოდ მაშინ, თუ ქვესფეროს ამატებთ.</small></label>
            <label><span>რიგითობა</span><input type="number" name="sort_order" value="100" min="0" max="1000000" required></label>
            <button class="button button-primary" type="submit">+ სფეროს დამატება</button>
        </form>
    </aside>

    <div class="admin-panel taxonomy-tree-card">
        <div class="taxonomy-tree-toolbar">
            <div><h2>სფეროების სტრუქტურა</h2><p>გადაათრიეთ ან გამოიყენეთ ისრები. დაშვებულია ორი დონე.</p></div>
            <div><span data-category-order-status>ცვლილებები არ არის</span><button class="button button-primary" type="submit" form="category-order-form" data-category-save disabled>განლაგების შენახვა</button></div>
        </div>
        <form id="category-order-form" action="/admin/categories/reorder" method="post" data-category-order-form><?= csrf_field() ?><input type="hidden" name="structure" data-category-structure></form>
        <div class="taxonomy-tree" data-category-tree>
            <div class="taxonomy-tree-list" data-category-container data-parent-id="">
                <?php foreach ($rootCategories as $category): ?><?php $renderCategory($category); ?><?php endforeach; ?>
                <div class="taxonomy-root-dropzone" data-root-dropzone>აქ ჩამოაგდეთ მთავარ დონეზე გადასატანად</div>
            </div>
        </div>
        <?php if (!$categories): ?><div class="empty-state">ჯერ არცერთი სფერო არ არის დამატებული.</div><?php endif; ?>
        <noscript><p class="form-error-summary">გადაადგილებისთვის ჩართეთ JavaScript; სახელისა და მშობელი სფეროს შეცვლა რედაქტირების ფორმით მაინც შეგიძლიათ.</p></noscript>
    </div>
</div></section>

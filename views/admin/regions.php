<?php
$settlementsByRegion = [];
foreach ($settlements as $settlement) {
    $settlementsByRegion[(int) $settlement['region_id']][] = $settlement;
}
?>
<header class="page-hero compact"><div class="container"><span class="section-kicker">Admin</span><h1><?= e(t('admin.regions')) ?></h1><p>რეგიონები და მათზე მიბმული ქალაქები/უბნები ავტომატურად გამოიყენება რეგისტრაციაში, განცხადებებსა და ყველა შესაბამის ფილტრში.</p><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>

<section class="section admin-section"><div class="container">
    <div class="admin-panel taxonomy-region-create">
        <div><h2>ახალი რეგიონი</h2><p>რეგიონის დამატების შემდეგ ქვემოთ დაუმატეთ ქალაქები ან უბნები.</p></div>
        <form action="/admin/regions" method="post" class="taxonomy-create-inline">
            <?= csrf_field() ?>
            <label><span>რეგიონის სახელი</span><input name="name" maxlength="120" required></label>
            <label><span>რიგითობა</span><input type="number" name="sort_order" value="100" min="0" max="1000000" required></label>
            <button class="button button-primary" type="submit">+ რეგიონის დამატება</button>
        </form>
    </div>

    <div class="taxonomy-region-list">
        <?php foreach ($regions as $region): ?>
            <?php $regionSettlements = $settlementsByRegion[(int) $region['id']] ?? []; ?>
            <article class="admin-panel taxonomy-region-card">
                <form action="/admin/regions/<?= (int) $region['id'] ?>" method="post" class="taxonomy-region-form">
                    <?= csrf_field() ?>
                    <label class="taxonomy-name-field"><span>რეგიონი</span><input name="name" value="<?= e($region['name']) ?>" maxlength="120" required></label>
                    <label><span>რიგითობა</span><input type="number" name="sort_order" value="<?= (int) $region['sort_order'] ?>" min="0" max="1000000" required></label>
                    <div class="taxonomy-usage"><strong><?= count($regionSettlements) ?></strong><span>ქალაქი/უბანი</span></div>
                    <div class="taxonomy-usage"><strong><?= (int) $region['teacher_count'] + (int) $region['request_count'] ?></strong><span>ჩანაწერი</span></div>
                    <button class="button button-muted" type="submit">რეგიონის შენახვა</button>
                </form>

                <div class="taxonomy-settlement-list">
                    <?php foreach ($regionSettlements as $settlement): ?>
                        <form action="/admin/settlements/<?= (int) $settlement['id'] ?>" method="post" class="taxonomy-settlement-form">
                            <?= csrf_field() ?>
                            <label class="taxonomy-name-field"><span>ქალაქი / უბანი</span><input name="name" value="<?= e($settlement['name']) ?>" maxlength="140" required></label>
                            <label><span>რეგიონი</span><select name="region_id" required><?php foreach ($regions as $regionOption): ?><option value="<?= (int) $regionOption['id'] ?>" <?= (int) $regionOption['id'] === (int) $settlement['region_id'] ? 'selected' : '' ?>><?= e($regionOption['name']) ?></option><?php endforeach; ?></select></label>
                            <label><span>რიგითობა</span><input type="number" name="sort_order" value="<?= (int) $settlement['sort_order'] ?>" min="0" max="1000000" required></label>
                            <small><?= (int) $settlement['teacher_count'] + (int) $settlement['request_count'] ?> დაკავშირებული ჩანაწერი</small>
                            <button class="button button-muted" type="submit">შენახვა</button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <form action="/admin/settlements" method="post" class="taxonomy-add-settlement">
                    <?= csrf_field() ?><input type="hidden" name="region_id" value="<?= (int) $region['id'] ?>">
                    <label><span>ახალი ქალაქი ან უბანი</span><input name="name" maxlength="140" placeholder="მაგ. ბათუმი" required></label>
                    <label><span>რიგითობა</span><input type="number" name="sort_order" value="100" min="0" max="1000000" required></label>
                    <button class="button button-primary" type="submit">+ დამატება</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</div></section>

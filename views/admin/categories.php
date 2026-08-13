<header class="page-hero compact"><div class="container"><span class="section-kicker">Admin</span><h1><?= e(t('admin.categories')) ?></h1><p>აქ დამატებული სფერო ავტომატურად გამოჩნდება მთავარ გვერდზე, ძებნაში, რეგისტრაციასა და განცხადების ფორმებში.</p><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>

<section class="section admin-section"><div class="container taxonomy-admin-grid">
    <aside class="admin-panel taxonomy-create-card">
        <h2>ახალი სფერო</h2>
        <form action="/admin/categories" method="post" class="taxonomy-create-form">
            <?= csrf_field() ?>
            <label><span>სფეროს სახელი</span><input name="name" maxlength="120" placeholder="მაგ. ბიზნესი და ფინანსები" required></label>
            <label><span>რიგითობა</span><input type="number" name="sort_order" value="100" min="0" max="1000000" required><small>ნაკლები რიცხვი სიაში უფრო წინ გამოჩნდება.</small></label>
            <button class="button button-primary" type="submit">+ სფეროს დამატება</button>
        </form>
    </aside>

    <div class="taxonomy-list">
        <?php foreach ($categories as $category): ?>
            <article class="admin-panel taxonomy-row-card">
                <form action="/admin/categories/<?= (int) $category['id'] ?>" method="post" class="taxonomy-row-form">
                    <?= csrf_field() ?>
                    <label class="taxonomy-name-field"><span>სფერო</span><input name="name" value="<?= e($category['name']) ?>" maxlength="120" required></label>
                    <label><span>რიგითობა</span><input type="number" name="sort_order" value="<?= (int) $category['sort_order'] ?>" min="0" max="1000000" required></label>
                    <div class="taxonomy-usage"><strong><?= (int) $category['teacher_count'] ?></strong><span>მასწავლებელი</span></div>
                    <div class="taxonomy-usage"><strong><?= (int) $category['request_count'] ?></strong><span>განცხადება</span></div>
                    <button class="button button-muted" type="submit">შენახვა</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</div></section>

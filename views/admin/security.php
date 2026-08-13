<header class="page-hero compact"><div class="container"><span class="section-kicker">Admin</span><h1><?= e(t('admin.security')) ?></h1><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>
<section class="section admin-section"><div class="container narrow"><form class="admin-panel security-form" action="/admin/security" method="post">
    <?= csrf_field() ?>
    <h2><?= e(t('admin.change_password')) ?></h2>
    <p class="form-note"><?= e(t('admin.signed_in_as')) ?>: <strong><?= e($adminEmail) ?></strong></p>
    <label><span><?= e(t('admin.current_password')) ?></span><input type="password" name="current_password" autocomplete="current-password" required></label>
    <label><span><?= e(t('admin.new_password')) ?></span><input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="1024" required><small><?= e(t('admin.password_help')) ?></small></label>
    <label><span><?= e(t('admin.confirm_password')) ?></span><input type="password" name="new_password_confirmation" autocomplete="new-password" minlength="12" maxlength="1024" required></label>
    <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t('admin.change_password')) ?></button><a class="button button-muted" href="/admin"><?= e(t('common.cancel')) ?></a></div>
</form></div></section>

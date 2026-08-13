<section class="section admin-login"><div class="container narrow"><form class="admin-card" action="/admin/login" method="post">
    <?= csrf_field() ?>
    <img src="<?= asset('assets/images/logo.svg') ?>" alt="Moemzade.ge">
    <h1><?= e(t('admin.login')) ?></h1>
    <label><span><?= e(t('admin.username')) ?></span><input type="text" name="email" autocomplete="username" required></label>
    <label><span><?= e(t('admin.password')) ?></span><input type="password" name="password" autocomplete="current-password" required></label>
    <button class="button button-primary full" type="submit"><?= e(t('admin.sign_in')) ?></button>
</form></div></section>

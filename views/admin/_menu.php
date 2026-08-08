<div class="admin-toolbar">
    <nav><a href="/admin"><?= e(t('admin.dashboard')) ?></a><a href="/admin/teachers"><?= e(t('admin.teachers')) ?></a><a href="/admin/comments"><?= e(t('admin.comments')) ?></a></nav>
    <form action="/admin/logout" method="post"><?= csrf_field() ?><button class="button button-muted" type="submit"><?= e(t('admin.logout')) ?></button></form>
</div>


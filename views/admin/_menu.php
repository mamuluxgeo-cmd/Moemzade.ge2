<div class="admin-toolbar">
    <nav><a href="/admin"><?= e(t('admin.dashboard')) ?></a><a href="/admin/teachers"><?= e(t('admin.teachers')) ?></a><a href="/admin/teachers?status=draft"><?= e(t('admin.applications')) ?></a><a href="/admin/mentor-requests"><?= e(t('admin.mentor_requests')) ?></a><a href="/admin/mentor-requests?status=draft"><?= e(t('mentor.pending_requests')) ?></a><a href="/admin/categories"><?= e(t('admin.categories')) ?></a><a href="/admin/regions"><?= e(t('admin.regions')) ?></a><a href="/admin/comments"><?= e(t('admin.comments')) ?></a><a href="/admin/security"><?= e(t('admin.security')) ?></a></nav>
    <form action="/admin/logout" method="post"><?= csrf_field() ?><button class="button button-muted" type="submit"><?= e(t('admin.logout')) ?></button></form>
</div>

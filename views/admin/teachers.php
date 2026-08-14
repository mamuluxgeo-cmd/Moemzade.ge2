<header class="page-hero compact"><div class="container"><div class="admin-title-row"><div><span class="section-kicker">Admin</span><h1><?= e(t('admin.teachers')) ?></h1></div><div class="admin-title-actions"><a class="button button-muted" href="<?= e(query_url('/admin/teachers/export.csv', ['page' => null])) ?>">↓ CSV</a><a class="button button-primary" href="/admin/teachers/new">+ <?= e(t('admin.add_teacher')) ?></a></div></div><?php require BASE_PATH . '/views/admin/_menu.php'; ?></div></header>
<section class="section admin-section"><div class="container">
    <div class="admin-mini-stats"><a href="/admin/teachers?status=draft"><strong><?= (int) $stats['pending_teachers'] ?></strong><span><?= e(t('admin.pending_applications')) ?></span></a><a href="/admin/teachers?status=published"><strong><?= (int) $stats['published'] ?></strong><span><?= e(t('status.published')) ?></span></a><a href="/admin/teachers"><strong><?= (int) $stats['teachers'] ?></strong><span><?= e(t('common.all')) ?></span></a></div>
    <form class="admin-filter-form" action="/admin/teachers" method="get">
        <label class="wide"><span><?= e(t('search.keyword')) ?></span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('admin.search_placeholder')) ?>"></label>
        <label><span>Status</span><select name="status"><option value=""><?= e(t('common.all')) ?></option><option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>><?= e(t('status.pending')) ?></option><option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>><?= e(t('status.published')) ?></option><option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>><?= e(t('status.archived')) ?></option></select></label>
        <label><span><?= e(t('admin.sort')) ?></span><select name="sort"><option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>><?= e(t('admin.newest')) ?></option><option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>><?= e(t('admin.oldest')) ?></option><option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>><?= e(t('admin.by_name')) ?></option><option value="category" <?= $filters['sort'] === 'category' ? 'selected' : '' ?>><?= e(t('search.category')) ?></option></select></label>
        <button class="button button-primary" type="submit"><?= e(t('search.submit')) ?></button>
    </form>
    <div class="admin-panel table-wrap"><table><thead><tr><th>ID</th><th><?= e(t('admin.teachers')) ?></th><th><?= e(t('search.category')) ?></th><th><?= e(t('search.region')) ?></th><th><?= e(t('teacher.price')) ?></th><th>Status</th><th><?= e(t('admin.actions')) ?></th></tr></thead><tbody>
    <?php foreach ($teachers as $teacher): ?>
        <?php $teacherName = localized($teacher, 'name'); ?>
        <tr class="<?= $teacher['status'] === 'draft' ? 'pending-row' : '' ?>">
            <td><?= (int) $teacher['id'] ?></td>
            <td><div class="admin-teacher"><?php if ($teacher['photo_url']): ?><img src="<?= e($teacher['photo_url']) ?>" alt=""><?php endif; ?><div><strong><?= e($teacherName) ?></strong><small><?= e($teacher['phone']) ?></small></div></div></td>
            <td><?= e($teacher['category']) ?></td>
            <td><?= e(($teacher['settlement'] ?: '—') . ' / ' . ($teacher['region'] ?: '—')) ?></td>
            <td><?= e(teacher_price($teacher)) ?></td>
            <td><span class="status status-<?= e($teacher['status']) ?>"><?= e(teacher_status_label((string) $teacher['status'])) ?></span></td>
            <td><div class="admin-row-actions">
                <a href="/admin/teachers/<?= (int) $teacher['id'] ?>/edit"><?= e(t('admin.edit')) ?></a>
                <?php if ($teacher['status'] !== 'published'): ?><form action="/admin/teachers/<?= (int) $teacher['id'] ?>/status" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button class="action-approve" type="submit"><?= e(t('admin.approve')) ?></button></form><?php endif; ?>
                <?php if ($teacher['status'] !== 'archived'): ?><form action="/admin/teachers/<?= (int) $teacher['id'] ?>/status" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><button class="action-reject" type="submit"><?= e(t('admin.archive')) ?></button></form><?php endif; ?>
                <details class="admin-danger-menu">
                    <summary>წაშლა</summary>
                    <form action="/admin/teachers/<?= (int) $teacher['id'] ?>/delete" method="post" onsubmit="return confirm('პროფილის სრულად წაშლა ნამდვილად გსურთ? ამ მოქმედების გაუქმება შეუძლებელია.')">
                        <?= csrf_field() ?>
                        <strong>სრული წაშლა</strong>
                        <small>წაიშლება განცხადება, ფოტოები, კომენტარები და ნახვების ისტორია. დასადასტურებლად ჩაწერეთ:</small>
                        <code><?= e($teacherName) ?></code>
                        <input type="text" name="confirmation" aria-label="პროფილის სახელის დადასტურება" autocomplete="off" required>
                        <button class="taxonomy-delete-button" type="submit">სამუდამოდ წაშლა</button>
                    </form>
                </details>
            </div></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$teachers): ?><tr><td colspan="7"><div class="empty-state"><?= e(t('admin.no_results')) ?></div></td></tr><?php endif; ?>
    </tbody></table></div>
</div></section>

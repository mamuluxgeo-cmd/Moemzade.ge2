<?php

declare(strict_types=1);

use Moemzade\Media\MediaManager;

require __DIR__ . '/bootstrap.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = '/' . trim(rawurldecode($path), '/');
$path = $path === '/' ? '/' : rtrim($path, '/');

try {
    if ($method === 'GET' && !str_starts_with($path, '/admin')) {
        $repository->trackPageView(visitor_hash(), $path);
    }

    if ($method === 'GET' && $path === '/') {
        view('home', [
            'pageTitle' => 'Moemzade.ge — ' . t('site.tagline'),
            'metaDescription' => t('home.subtitle'),
            'stats' => $repository->homeStats(),
            'teachers' => $repository->latestTeachers(),
            'options' => $repository->filterOptions(),
        ]);
    }

    if ($method === 'GET' && $path === '/teachers') {
        $filters = request_filters($_GET);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 24;
        $total = $repository->countTeachers($filters);
        $teachers = $repository->searchTeachers($filters, $perPage, ($page - 1) * $perPage);
        if (array_filter($filters, static fn (string $value): bool => $value !== '')) {
            $repository->logSearch(visitor_hash(), $filters, $total);
        }
        view('teachers', [
            'pageTitle' => t('search.title') . ' — Moemzade.ge',
            'metaDescription' => t('home.subtitle'),
            'filters' => $filters,
            'teachers' => $teachers,
            'options' => $repository->filterOptions(),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    if ($method === 'POST' && $path === '/match') {
        verify_csrf();
        $filters = request_filters($_POST);
        $total = $repository->countTeachers($filters);
        $visitor = visitor_hash();
        $repository->logMatchRequest($visitor, $filters, $total);
        $repository->logSearch($visitor, $filters, $total, 'matching');
        $query = array_filter($filters, static fn (string $value): bool => $value !== '');
        $query['matched'] = '1';
        redirect('/teachers?' . http_build_query($query));
    }

    if ($method === 'GET' && preg_match('#^/teacher/([^/]+)$#u', $path, $matches)) {
        $teacher = $repository->findTeacherBySlug($matches[1]);
        if ($teacher === null) {
            view('error', ['pageTitle' => '404', 'message' => t('search.empty')], 404);
        }
        $repository->trackTeacherView((int) $teacher['id'], visitor_hash());
        view('teacher', [
            'pageTitle' => localized($teacher, 'name') . ' — Moemzade.ge',
            'metaDescription' => mb_substr(localized($teacher, 'bio'), 0, 160, 'UTF-8'),
            'teacher' => $teacher,
            'comments' => $repository->approvedComments((int) $teacher['id']),
        ]);
    }

    if ($method === 'POST' && preg_match('#^/teacher/(\d+)/comments$#', $path, $matches)) {
        verify_csrf();
        $teacher = $repository->findTeacherById((int) $matches[1]);
        if ($teacher === null || $teacher['status'] !== 'published') {
            view('error', ['pageTitle' => '404', 'message' => t('search.empty')], 404);
        }

        $name = mb_substr(trim((string) ($_POST['author_name'] ?? '')), 0, 100, 'UTF-8');
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 1500, 'UTF-8');
        $rating = (int) ($_POST['rating'] ?? 0);
        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($body, 'UTF-8') < 10 || $rating < 1 || $rating > 5) {
            flash('error', 'გთხოვთ, სწორად შეავსოთ სახელი, შეფასება და კომენტარი.');
            redirect('/teacher/' . rawurlencode((string) $teacher['slug']) . '#comments');
        }

        $repository->upsertComment((int) $teacher['id'], visitor_hash(), $name, $rating, $body);
        flash('success', t('comments.pending'));
        redirect('/teacher/' . rawurlencode((string) $teacher['slug']) . '#comments');
    }

    if ($method === 'GET' && in_array($path, ['/privacy', '/terms', '/cookies'], true)) {
        $kind = ltrim($path, '/');
        view('legal', [
            'pageTitle' => t('legal.' . $kind) . ' — Moemzade.ge',
            'kind' => $kind,
        ]);
    }

    if ($path === '/admin/login' && $method === 'GET') {
        if (is_admin()) {
            redirect('/admin');
        }
        view('admin/login', ['pageTitle' => t('admin.login') . ' — Moemzade.ge']);
    }

    if ($path === '/admin/login' && $method === 'POST') {
        verify_csrf();
        if (admin_attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            redirect('/admin');
        }
        flash('error', t('admin.invalid'));
        redirect('/admin/login');
    }

    if ($path === '/admin/logout' && $method === 'POST') {
        verify_csrf();
        $_SESSION = [];
        session_regenerate_id(true);
        redirect('/admin/login');
    }

    if ($path === '/admin' && $method === 'GET') {
        require_admin();
        view('admin/dashboard', [
            'pageTitle' => t('admin.dashboard') . ' — Moemzade.ge',
            'stats' => $repository->adminStats(),
            'topTeachers' => $repository->topTeachers(),
            'failedSearches' => $repository->unsuccessfulSearches(),
        ]);
    }

    if ($path === '/admin/teachers' && $method === 'GET') {
        require_admin();
        view('admin/teachers', [
            'pageTitle' => t('admin.teachers') . ' — Moemzade.ge',
            'teachers' => $repository->adminTeachers(),
        ]);
    }

    if ($path === '/admin/teachers/new' && $method === 'GET') {
        require_admin();
        view('admin/teacher-form', [
            'pageTitle' => t('admin.add_teacher') . ' — Moemzade.ge',
            'teacher' => null,
        ]);
    }

    if ($method === 'GET' && preg_match('#^/admin/teachers/(\d+)/edit$#', $path, $matches)) {
        require_admin();
        $teacher = $repository->findTeacherById((int) $matches[1]);
        if ($teacher === null) {
            view('error', ['pageTitle' => '404', 'message' => 'Teacher not found.'], 404);
        }
        view('admin/teacher-form', [
            'pageTitle' => localized($teacher, 'name') . ' — ' . t('admin.teachers'),
            'teacher' => $teacher,
        ]);
    }

    if ($path === '/admin/teachers/save' && $method === 'POST') {
        require_admin();
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $teacherId = $repository->saveTeacher($_POST, $id > 0 ? $id : null);
            if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $manager = new MediaManager($config['media']);
                $oldMedia = $repository->teacherMedia($teacherId);
                $variants = $manager->storeTeacherPhoto($teacherId, $_FILES['photo']);
                try {
                    $repository->replaceTeacherMedia($teacherId, $variants);
                } catch (\Throwable $mediaDatabaseException) {
                    try {
                        $manager->deleteStored($variants);
                    } catch (\Throwable $cleanupException) {
                        error_log($cleanupException->__toString());
                    }
                    throw $mediaDatabaseException;
                }
                try {
                    $manager->deleteStored($oldMedia);
                } catch (\Throwable $cleanupException) {
                    error_log($cleanupException->__toString());
                }
            }
            flash('success', 'მასწავლებლის მონაცემები შენახულია.');
            redirect('/admin/teachers');
        } catch (\Throwable $exception) {
            flash('error', $config['debug'] ? $exception->getMessage() : 'მონაცემების შენახვა ვერ მოხერხდა.');
            redirect($id > 0 ? "/admin/teachers/{$id}/edit" : '/admin/teachers/new');
        }
    }

    if ($path === '/admin/comments' && $method === 'GET') {
        require_admin();
        $status = (string) ($_GET['status'] ?? 'pending');
        view('admin/comments', [
            'pageTitle' => t('admin.comments') . ' — Moemzade.ge',
            'comments' => $repository->adminComments($status),
            'status' => $status,
        ]);
    }

    if ($method === 'POST' && preg_match('#^/admin/comments/(\d+)$#', $path, $matches)) {
        require_admin();
        verify_csrf();
        $repository->moderateComment((int) $matches[1], (string) ($_POST['status'] ?? ''));
        flash('success', 'კომენტარის სტატუსი განახლებულია.');
        redirect('/admin/comments');
    }

    view('error', ['pageTitle' => '404 — Moemzade.ge', 'message' => 'გვერდი ვერ მოიძებნა.'], 404);
} catch (\Throwable $exception) {
    if ($config['debug']) {
        throw $exception;
    }
    error_log($exception->__toString());
    view('error', ['pageTitle' => 'შეცდომა — Moemzade.ge', 'message' => 'დროებითი შეცდომა დაფიქსირდა. გთხოვთ, სცადოთ მოგვიანებით.'], 500);
}

/** @param array<string, mixed> $source
 *  @return array{q:string,category:string,region:string,format:string,language:string}
 */
function request_filters(array $source): array
{
    $format = (string) ($source['format'] ?? '');
    return [
        'q' => mb_substr(trim((string) ($source['q'] ?? '')), 0, 100, 'UTF-8'),
        'category' => mb_substr(trim((string) ($source['category'] ?? '')), 0, 120, 'UTF-8'),
        'region' => mb_substr(trim((string) ($source['region'] ?? '')), 0, 120, 'UTF-8'),
        'format' => in_array($format, ['online', 'in_person'], true) ? $format : '',
        'language' => mb_substr(trim((string) ($source['language'] ?? '')), 0, 60, 'UTF-8'),
    ];
}

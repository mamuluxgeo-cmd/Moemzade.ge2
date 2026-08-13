<?php

declare(strict_types=1);

use Moemzade\Media\MediaManager;

require __DIR__ . '/bootstrap.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = '/' . trim(rawurldecode($path), '/');
$path = $path === '/' ? '/' : rtrim($path, '/');

try {
    if ($method === 'GET' && $path === '/robots.txt') {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "User-agent: *\nAllow: /\n\nUser-agent: facebookexternalhit\nAllow: /\n\nUser-agent: Twitterbot\nAllow: /\n\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
        exit;
    }

    if ($method === 'GET' && $path === '/sitemap.xml') {
        header('Content-Type: application/xml; charset=UTF-8');
        $today = date('Y-m-d');
        $staticPaths = ['/', '/teachers', '/mentor-requests', '/register', '/faq', '/privacy', '/terms', '/cookies'];
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($staticPaths as $staticPath) {
            echo '  <url><loc>' . xml_e(absolute_url($staticPath)) . '</loc><lastmod>' . $today . "</lastmod></url>\n";
        }
        foreach ($repository->sitemapTeachers() as $item) {
            echo '  <url><loc>' . xml_e(absolute_url('/teacher/' . rawurlencode((string) $item['slug']))) . '</loc><lastmod>' . xml_e((string) $item['updated_at']) . "</lastmod></url>\n";
        }
        foreach ($repository->sitemapMentorRequests() as $item) {
            echo '  <url><loc>' . xml_e(absolute_url('/mentor-request/' . rawurlencode((string) $item['slug']))) . '</loc><lastmod>' . xml_e((string) $item['updated_at']) . "</lastmod></url>\n";
        }
        foreach ($repository->sitemapLandings() as $item) {
            $location = (string) $item['location'] === 'online' ? 'online' : seo_slug((string) $item['location']);
            $landingPath = '/teachers/' . $location . '/' . seo_slug((string) $item['category']);
            echo '  <url><loc>' . xml_e(absolute_url($landingPath)) . '</loc><lastmod>' . xml_e((string) $item['updated_at']) . "</lastmod></url>\n";
        }
        echo "</urlset>\n";
        exit;
    }

    if ($method === 'GET' && !str_starts_with($path, '/admin')) {
        $repository->trackPageView(visitor_hash(), $path);
    }

    if ($method === 'GET' && $path === '/') {
        view('home', [
            'pageTitle' => 'Moemzade.ge — ' . t('site.tagline'),
            'metaDescription' => t('home.subtitle'),
            'stats' => $repository->homeStats(),
            'teachers' => $repository->latestTeachers(6),
            'mentorRequests' => $repository->latestMentorRequests(),
            'categories' => $repository->categorySummaries(),
            'options' => $repository->filterOptions(),
            'canonicalUrl' => absolute_url('/'),
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
            'canonicalUrl' => absolute_url('/teachers' . ($page > 1 ? '?page=' . $page : '')),
        ]);
    }

    if ($method === 'GET' && $path === '/mentor-requests') {
        $filters = mentor_request_filters($_GET);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 24;
        $total = $repository->countMentorRequests($filters);
        view('mentor-requests', [
            'pageTitle' => t('mentor.catalog_title') . ' — Moemzade.ge',
            'metaDescription' => t('mentor.catalog_subtitle'),
            'filters' => $filters,
            'requests' => $repository->searchMentorRequests($filters, $perPage, ($page - 1) * $perPage),
            'options' => $repository->filterOptions(),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'canonicalUrl' => absolute_url('/mentor-requests' . ($page > 1 ? '?page=' . $page : '')),
        ]);
    }

    if ($method === 'GET' && $path === '/mentor-requests/new') {
        view('mentor-request-form', [
            'pageTitle' => t('mentor.form_title') . ' — Moemzade.ge',
            'metaDescription' => t('mentor.form_subtitle'),
            'options' => $repository->filterOptions(),
            'old' => mentor_request_prefill($_GET),
            'errors' => [],
            'canonicalUrl' => absolute_url('/mentor-requests/new'),
            'robots' => 'noindex,follow',
        ]);
    }

    if ($method === 'POST' && $path === '/mentor-requests') {
        verify_csrf();
        [$submission, $errors] = validate_public_mentor_request($_POST);
        if ($errors) {
            view('mentor-request-form', [
                'pageTitle' => t('mentor.form_title') . ' — Moemzade.ge',
                'metaDescription' => t('mentor.form_subtitle'),
                'options' => $repository->filterOptions(),
                'old' => $_POST,
                'errors' => $errors,
                'canonicalUrl' => absolute_url('/mentor-requests/new'),
                'robots' => 'noindex,follow',
            ], 422);
        }

        try {
            $repository->saveMentorRequest($submission);
        } catch (\Throwable $exception) {
            $errors['form'] = $config['debug'] ? $exception->getMessage() : t('mentor.failed');
            view('mentor-request-form', [
                'pageTitle' => t('mentor.form_title') . ' — Moemzade.ge',
                'metaDescription' => t('mentor.form_subtitle'),
                'options' => $repository->filterOptions(),
                'old' => $_POST,
                'errors' => $errors,
                'canonicalUrl' => absolute_url('/mentor-requests/new'),
                'robots' => 'noindex,follow',
            ], 422);
        }
        redirect('/mentor-requests/success');
    }

    if ($method === 'GET' && $path === '/mentor-requests/success') {
        view('mentor-request-success', [
            'pageTitle' => t('mentor.success_title') . ' — Moemzade.ge',
            'metaDescription' => t('mentor.success_text'),
            'canonicalUrl' => absolute_url('/mentor-requests/new'),
            'robots' => 'noindex,follow',
        ]);
    }

    if ($method === 'GET' && preg_match('#^/mentor-request/([^/]+)$#u', $path, $matches)) {
        $request = $repository->findMentorRequestBySlug($matches[1]);
        if ($request === null) {
            view('error', ['pageTitle' => '404 — Moemzade.ge', 'message' => t('mentor.empty')], 404);
        }
        view('mentor-request', [
            'pageTitle' => (string) $request['subject'] . ' — ' . (string) $request['name'] . ' | Moemzade.ge',
            'metaDescription' => mb_substr(trim((string) ($request['learning_goal'] ?: $request['details'])), 0, 160, 'UTF-8'),
            'request' => $request,
            'canonicalUrl' => absolute_url('/mentor-request/' . rawurlencode((string) $request['slug'])),
        ]);
    }

    if ($method === 'GET' && preg_match('#^/teachers/([^/]+)/([^/]+)$#', $path, $matches)) {
        $landing = $repository->seoLanding($matches[1], $matches[2]);
        if ($landing === null) {
            view('error', ['pageTitle' => '404 — Moemzade.ge', 'message' => t('search.empty')], 404);
        }
        $landingUrl = absolute_url($path);
        $structuredItems = [];
        foreach (array_slice($landing['teachers'], 0, 20) as $index => $item) {
            $structuredItems[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => absolute_url('/teacher/' . rawurlencode((string) $item['slug'])),
                'name' => localized($item, 'name'),
            ];
        }
        view('seo-landing', [
            'pageTitle' => $landing['category'] . ' — ' . $landing['location'] . ' | Moemzade.ge',
            'metaDescription' => $landing['location'] . ': ' . $landing['category'] . ' მიმართულების მასწავლებლები, ფასები და საკონტაქტო ინფორმაცია.',
            'landing' => $landing,
            'canonicalUrl' => $landingUrl,
            'structuredData' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $landing['category'] . ' — ' . $landing['location'],
                'itemListElement' => $structuredItems,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    if ($method === 'GET' && $path === '/register') {
        view('register', [
            'pageTitle' => t('register.title') . ' — Moemzade.ge',
            'metaDescription' => t('register.subtitle'),
            'options' => $repository->filterOptions(),
            'old' => [],
            'errors' => [],
            'canonicalUrl' => absolute_url('/register'),
        ]);
    }

    if ($method === 'POST' && $path === '/register') {
        verify_csrf();
        [$submission, $errors] = validate_public_registration($_POST);
        if ($errors) {
            view('register', [
                'pageTitle' => t('register.title') . ' — Moemzade.ge',
                'metaDescription' => t('register.subtitle'),
                'options' => $repository->filterOptions(),
                'old' => $_POST,
                'errors' => $errors,
                'canonicalUrl' => absolute_url('/register'),
            ], 422);
        }

        $teacherId = 0;
        $storedVariants = [];
        try {
            $teacherId = $repository->saveTeacher($submission);
            if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $manager = new MediaManager($config['media']);
                $storedVariants = $manager->storeTeacherPhoto($teacherId, $_FILES['photo']);
                $repository->replaceTeacherMedia($teacherId, $storedVariants);
            }
        } catch (\Throwable $exception) {
            if ($storedVariants) {
                try {
                    (new MediaManager($config['media']))->deleteStored($storedVariants);
                } catch (\Throwable $cleanupException) {
                    error_log($cleanupException->__toString());
                }
            }
            if ($teacherId > 0) {
                $repository->deleteTeacher($teacherId);
            }
            $errors['form'] = $config['debug'] ? $exception->getMessage() : t('register.failed');
            view('register', [
                'pageTitle' => t('register.title') . ' — Moemzade.ge',
                'metaDescription' => t('register.subtitle'),
                'options' => $repository->filterOptions(),
                'old' => $_POST,
                'errors' => $errors,
                'canonicalUrl' => absolute_url('/register'),
            ], 422);
        }
        redirect('/register/success');
    }

    if ($method === 'GET' && $path === '/register/success') {
        view('register-success', [
            'pageTitle' => t('register.success_title') . ' — Moemzade.ge',
            'metaDescription' => t('register.success_text'),
            'canonicalUrl' => absolute_url('/register'),
            'robots' => 'noindex,follow',
        ]);
    }

    if ($method === 'GET' && $path === '/faq') {
        view('faq', [
            'pageTitle' => t('faq.title') . ' — Moemzade.ge',
            'metaDescription' => t('faq.subtitle'),
            'canonicalUrl' => absolute_url('/faq'),
        ]);
    }

    if ($path === '/match' && in_array($method, ['GET', 'POST'], true)) {
        if ($method === 'POST') {
            verify_csrf();
        }
        $source = $method === 'POST' ? $_POST : $_GET;
        $prefill = mentor_request_prefill($source);
        $query = array_filter([
            'category' => $prefill['category'] ?? '',
            'region' => $prefill['region'] ?? '',
            'format' => $prefill['format'] ?? '',
        ], static fn (string $value): bool => $value !== '');
        redirect('/mentor-requests/new' . ($query ? '?' . http_build_query($query) : ''));
    }

    if ($method === 'GET' && preg_match('#^/teacher/([^/]+)$#u', $path, $matches)) {
        $teacher = $repository->findTeacherBySlug($matches[1]);
        if ($teacher === null) {
            view('error', ['pageTitle' => '404', 'message' => t('search.empty')], 404);
        }
        $repository->trackTeacherView((int) $teacher['id'], visitor_hash());
        $teacherName = localized($teacher, 'name');
        $teacherBio = trim(localized($teacher, 'bio'));
        $teacherUrl = absolute_url('/teacher/' . rawurlencode((string) $teacher['slug']));
        view('teacher', [
            'pageTitle' => $teacherName . ' — Moemzade.ge',
            'metaDescription' => mb_substr($teacherBio !== '' ? $teacherBio : localized($teacher, 'profession'), 0, 160, 'UTF-8'),
            'teacher' => $teacher,
            'comments' => $repository->approvedComments((int) $teacher['id']),
            'similarTeachers' => $repository->similarTeachers($teacher),
            'canonicalUrl' => $teacherUrl,
            'ogType' => 'profile',
            'ogImage' => !empty($teacher['photo_url']) ? absolute_url((string) $teacher['photo_url']) : null,
            'structuredData' => json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $teacherName,
                'description' => $teacherBio,
                'url' => $teacherUrl,
                'image' => !empty($teacher['photo_url']) ? absolute_url((string) $teacher['photo_url']) : null,
                'jobTitle' => localized($teacher, 'profession'),
                'telephone' => (string) $teacher['phone'],
            ], static fn (mixed $value): bool => $value !== null && $value !== ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
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

    if ($path === '/admin/security' && $method === 'GET') {
        require_admin();
        view('admin/security', [
            'pageTitle' => t('admin.security') . ' — Moemzade.ge',
            'adminEmail' => (string) $config['admin']['email'],
        ]);
    }

    if ($path === '/admin/security' && $method === 'POST') {
        require_admin();
        verify_csrf();

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');

        if (!admin_password_matches($currentPassword)) {
            flash('error', t('admin.current_password_invalid'));
            redirect('/admin/security');
        }
        if (mb_strlen($newPassword, 'UTF-8') < 12 || mb_strlen($newPassword, 'UTF-8') > 1024) {
            flash('error', t('admin.password_length'));
            redirect('/admin/security');
        }
        if (!hash_equals($newPassword, $confirmation)) {
            flash('error', t('admin.password_mismatch'));
            redirect('/admin/security');
        }
        if (admin_password_matches($newPassword)) {
            flash('error', t('admin.password_must_change'));
            redirect('/admin/security');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Password hash could not be generated.');
        }
        update_env_value(BASE_PATH . '/.env', 'ADMIN_PASSWORD_HASH', $hash);

        $_SESSION = [];
        session_regenerate_id(true);
        flash('success', t('admin.password_changed'));
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

    if ($path === '/admin/categories' && $method === 'GET') {
        require_admin();
        view('admin/categories', [
            'pageTitle' => t('admin.categories') . ' — Moemzade.ge',
            'categories' => $repository->adminCatalogCategories(),
        ]);
    }

    if ($path === '/admin/categories' && $method === 'POST') {
        require_admin();
        verify_csrf();
        try {
            $repository->createCatalogCategory(
                (string) ($_POST['name'] ?? ''),
                (int) ($_POST['sort_order'] ?? 100)
            );
            flash('success', 'სფერო დაემატა და ყველა შესაბამის სიაში გამოჩნდება.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/categories');
    }

    if ($method === 'POST' && preg_match('#^/admin/categories/(\d+)$#', $path, $matches)) {
        require_admin();
        verify_csrf();
        try {
            $repository->updateCatalogCategory(
                (int) $matches[1],
                (string) ($_POST['name'] ?? ''),
                (int) ($_POST['sort_order'] ?? 100)
            );
            flash('success', 'სფერო და მასთან დაკავშირებული ჩანაწერები განახლდა.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/categories');
    }

    if ($method === 'POST' && preg_match('#^/admin/categories/(\d+)/image$#', $path, $matches)) {
        require_admin();
        verify_csrf();
        $categoryId = (int) $matches[1];
        try {
            $manager = new MediaManager($config['media']);
            $oldMedia = $repository->categoryMedia($categoryId);

            if (isset($_POST['remove_image'])) {
                $repository->deleteCategoryMedia($categoryId);
                if ($oldMedia !== null) {
                    $manager->deleteStored([$oldMedia]);
                }
                flash('success', 'კატეგორიის ფოტო წაიშალა.');
            } else {
                $stored = $manager->storeCategoryPhoto($categoryId, $_FILES['category_image'] ?? []);
                try {
                    $repository->saveCategoryMedia($categoryId, $stored);
                } catch (\Throwable $exception) {
                    $manager->deleteStored([$stored]);
                    throw $exception;
                }
                if ($oldMedia !== null) {
                    $manager->deleteStored([$oldMedia]);
                }
                flash('success', 'კატეგორიის ფოტო განახლდა.');
            }
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/categories');
    }

    if ($path === '/admin/regions' && $method === 'GET') {
        require_admin();
        view('admin/regions', [
            'pageTitle' => t('admin.regions') . ' — Moemzade.ge',
            'regions' => $repository->adminCatalogRegions(),
            'settlements' => $repository->adminCatalogSettlements(),
        ]);
    }

    if ($path === '/admin/regions' && $method === 'POST') {
        require_admin();
        verify_csrf();
        try {
            $repository->createCatalogRegion(
                (string) ($_POST['name'] ?? ''),
                (int) ($_POST['sort_order'] ?? 100)
            );
            flash('success', 'რეგიონი დაემატა. ახლა მას ქალაქები ან უბნები შეგიძლიათ მიამაგროთ.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/regions');
    }

    if ($method === 'POST' && preg_match('#^/admin/regions/(\d+)$#', $path, $matches)) {
        require_admin();
        verify_csrf();
        try {
            $repository->updateCatalogRegion(
                (int) $matches[1],
                (string) ($_POST['name'] ?? ''),
                (int) ($_POST['sort_order'] ?? 100)
            );
            flash('success', 'რეგიონი და მასთან დაკავშირებული ჩანაწერები განახლდა.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/regions');
    }

    if ($path === '/admin/settlements' && $method === 'POST') {
        require_admin();
        verify_csrf();
        try {
            $repository->createCatalogSettlement(
                (int) ($_POST['region_id'] ?? 0),
                (string) ($_POST['name'] ?? ''),
                (int) ($_POST['sort_order'] ?? 100)
            );
            flash('success', 'ქალაქი ან უბანი რეგიონს მიემაგრა.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/regions');
    }

    if ($method === 'POST' && preg_match('#^/admin/settlements/(\d+)$#', $path, $matches)) {
        require_admin();
        verify_csrf();
        try {
            $repository->updateCatalogSettlement(
                (int) $matches[1],
                (int) ($_POST['region_id'] ?? 0),
                (string) ($_POST['name'] ?? ''),
                (int) ($_POST['sort_order'] ?? 100)
            );
            flash('success', 'ქალაქი ან უბანი და დაკავშირებული ჩანაწერები განახლდა.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }
        redirect('/admin/regions');
    }

    if ($path === '/admin/teachers/export.csv' && $method === 'GET') {
        require_admin();
        $filters = admin_teacher_filters($_GET);
        $teachers = $repository->adminTeachers($filters);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="moemzade-teachers-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('CSV output could not be opened.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Name', 'Category', 'Region', 'Settlement', 'Phone', 'Price', 'Status', 'Created']);
        foreach ($teachers as $item) {
            fputcsv($output, [
                $item['id'], localized($item, 'name'), $item['category'], $item['region'], $item['settlement'],
                $item['phone'], teacher_price($item), teacher_status_label((string) $item['status']), $item['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    if ($path === '/admin/teachers' && $method === 'GET') {
        require_admin();
        $filters = admin_teacher_filters($_GET);
        view('admin/teachers', [
            'pageTitle' => t('admin.teachers') . ' — Moemzade.ge',
            'teachers' => $repository->adminTeachers($filters),
            'filters' => $filters,
            'stats' => $repository->adminStats(),
        ]);
    }

    if ($method === 'POST' && preg_match('#^/admin/teachers/(\d+)/status$#', $path, $matches)) {
        require_admin();
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $status = match ($action) {
            'approve' => 'published',
            'reject' => 'archived',
            'pending' => 'draft',
            default => '',
        };
        if ($status === '') {
            flash('error', t('admin.invalid_action'));
        } else {
            $repository->setTeacherStatus((int) $matches[1], $status);
            flash('success', t('admin.status_updated'));
        }
        redirect('/admin/teachers?status=' . rawurlencode($status === 'published' ? 'draft' : $status));
    }

    if ($path === '/admin/teachers/new' && $method === 'GET') {
        require_admin();
        view('admin/teacher-form', [
            'pageTitle' => t('admin.add_teacher') . ' — Moemzade.ge',
            'teacher' => null,
            'options' => $repository->filterOptions(),
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
            'options' => $repository->filterOptions(),
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

    if ($path === '/admin/mentor-requests/export.csv' && $method === 'GET') {
        require_admin();
        $filters = admin_teacher_filters($_GET);
        $requests = $repository->adminMentorRequests($filters);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="moemzade-mentor-requests-' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('CSV output could not be opened.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['ID', 'Name', 'Category', 'Subject', 'Region', 'Settlement', 'Availability', 'Phone', 'Email', 'Budget', 'Status', 'Created']);
        foreach ($requests as $item) {
            fputcsv($output, [
                $item['id'], $item['name'], $item['category'], $item['subject'], $item['region'], $item['settlement'],
                $item['availability'], $item['phone'], $item['email'], mentor_request_budget($item),
                teacher_status_label((string) $item['status']), $item['created_at'],
            ]);
        }
        fclose($output);
        exit;
    }

    if ($path === '/admin/mentor-requests' && $method === 'GET') {
        require_admin();
        $filters = admin_teacher_filters($_GET);
        view('admin/mentor-requests', [
            'pageTitle' => t('admin.mentor_requests') . ' — Moemzade.ge',
            'requests' => $repository->adminMentorRequests($filters),
            'filters' => $filters,
            'stats' => $repository->adminStats(),
        ]);
    }

    if ($method === 'POST' && preg_match('#^/admin/mentor-requests/(\d+)/status$#', $path, $matches)) {
        require_admin();
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');
        $status = match ($action) {
            'approve' => 'published',
            'reject' => 'archived',
            'pending' => 'draft',
            default => '',
        };
        if ($status === '') {
            flash('error', t('admin.invalid_action'));
        } else {
            $repository->setMentorRequestStatus((int) $matches[1], $status);
            flash('success', t('mentor.admin_status_updated'));
        }
        redirect('/admin/mentor-requests?status=' . rawurlencode($status === 'published' ? 'draft' : $status));
    }

    if ($path === '/admin/mentor-requests/new' && $method === 'GET') {
        require_admin();
        view('admin/mentor-request-form', [
            'pageTitle' => t('mentor.admin_add') . ' — Moemzade.ge',
            'request' => null,
            'options' => $repository->filterOptions(),
        ]);
    }

    if ($method === 'GET' && preg_match('#^/admin/mentor-requests/(\d+)/edit$#', $path, $matches)) {
        require_admin();
        $request = $repository->findMentorRequestById((int) $matches[1]);
        if ($request === null) {
            view('error', ['pageTitle' => '404', 'message' => t('mentor.empty')], 404);
        }
        view('admin/mentor-request-form', [
            'pageTitle' => (string) $request['name'] . ' — ' . t('admin.mentor_requests'),
            'request' => $request,
            'options' => $repository->filterOptions(),
        ]);
    }

    if ($path === '/admin/mentor-requests/save' && $method === 'POST') {
        require_admin();
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $repository->saveMentorRequest($_POST, $id > 0 ? $id : null);
            flash('success', t('mentor.admin_saved'));
            redirect('/admin/mentor-requests');
        } catch (\Throwable $exception) {
            flash('error', $config['debug'] ? $exception->getMessage() : t('mentor.failed'));
            redirect($id > 0 ? "/admin/mentor-requests/{$id}/edit" : '/admin/mentor-requests/new');
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
 *  @return array{q:string,category:string,region:string,settlement:string,format:string,language:string}
 */
function request_filters(array $source): array
{
    $format = (string) ($source['format'] ?? '');
    return [
        'q' => mb_substr(trim((string) ($source['q'] ?? '')), 0, 100, 'UTF-8'),
        'category' => mb_substr(trim((string) ($source['category'] ?? '')), 0, 120, 'UTF-8'),
        'region' => mb_substr(trim((string) ($source['region'] ?? '')), 0, 120, 'UTF-8'),
        'settlement' => mb_substr(trim((string) ($source['settlement'] ?? '')), 0, 140, 'UTF-8'),
        'format' => in_array($format, ['online', 'in_person'], true) ? $format : '',
        'language' => mb_substr(trim((string) ($source['language'] ?? '')), 0, 60, 'UTF-8'),
    ];
}

/** @param array<string, mixed> $source
 *  @return array{q:string,category:string,region:string,settlement:string,format:string}
 */
function mentor_request_filters(array $source): array
{
    $format = (string) ($source['format'] ?? '');
    return [
        'q' => mb_substr(trim((string) ($source['q'] ?? '')), 0, 100, 'UTF-8'),
        'category' => mb_substr(trim((string) ($source['category'] ?? '')), 0, 120, 'UTF-8'),
        'region' => mb_substr(trim((string) ($source['region'] ?? '')), 0, 120, 'UTF-8'),
        'settlement' => mb_substr(trim((string) ($source['settlement'] ?? '')), 0, 140, 'UTF-8'),
        'format' => in_array($format, ['online', 'in_person'], true) ? $format : '',
    ];
}

/** @param array<string, mixed> $source
 *  @return array<string, string>
 */
function mentor_request_prefill(array $source): array
{
    $filters = mentor_request_filters($source);
    return [
        'category' => $filters['category'],
        'region' => $filters['region'],
        'settlement' => $filters['settlement'],
        'format' => $filters['format'],
        'format_online' => $filters['format'] === 'online' ? '1' : '',
        'format_in_person' => $filters['format'] === 'in_person' ? '1' : '',
    ];
}

/** @param array<string, mixed> $source
 *  @return array{q:string,status:string,sort:string}
 */
function admin_teacher_filters(array $source): array
{
    $status = (string) ($source['status'] ?? '');
    $sort = (string) ($source['sort'] ?? 'newest');
    return [
        'q' => mb_substr(trim((string) ($source['q'] ?? '')), 0, 100, 'UTF-8'),
        'status' => in_array($status, ['draft', 'published', 'archived'], true) ? $status : '',
        'sort' => in_array($sort, ['newest', 'oldest', 'name', 'category'], true) ? $sort : 'newest',
    ];
}

/** @param array<string, mixed> $source
 *  @return array{0:array<string,mixed>,1:array<string,string>}
 */
function validate_public_registration(array $source): array
{
    $value = static fn (string $key, int $max): string => mb_substr(trim((string) ($source[$key] ?? '')), 0, $max, 'UTF-8');
    $name = $value('name_ka', 190);
    $profession = $value('profession_ka', 190);
    $bio = $value('bio_ka', 8000);
    $category = $value('category', 120);
    $region = $value('region', 120);
    $settlement = $value('settlement', 140);
    $phone = $value('phone', 50);
    $languages = $value('languages', 255);
    $priceUnit = (string) ($source['price_unit'] ?? 'hour');
    $price = trim((string) ($source['price_from'] ?? ''));
    $formatOnline = !empty($source['format_online']);
    $formatInPerson = !empty($source['format_in_person']);
    $errors = [];

    if (mb_strlen($name, 'UTF-8') < 2) $errors['name_ka'] = t('register.error_name');
    if ($category === '') $errors['category'] = t('register.error_category');
    if ($profession === '') $errors['profession_ka'] = t('register.error_profession');
    if ($region === '') $errors['region'] = t('register.error_region');
    if ($settlement === '') $errors['settlement'] = t('register.error_settlement');
    if (mb_strlen($bio, 'UTF-8') < 30) $errors['bio_ka'] = t('register.error_bio');
    if (!$formatOnline && !$formatInPerson) $errors['format'] = t('register.error_format');
    if (!in_array($priceUnit, ['hour', 'month', 'course', 'negotiable'], true)) {
        $errors['price_unit'] = t('register.error_price');
        $priceUnit = 'hour';
    }
    if ($priceUnit !== 'negotiable' && ($price === '' || !is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99)) {
        $errors['price_from'] = t('register.error_price');
    }
    $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($phoneDigits) < 8 || strlen($phoneDigits) > 15) $errors['phone'] = t('register.error_phone');
    if (($source['consent'] ?? '') !== '1') $errors['consent'] = t('register.error_consent');

    return [[
        'name_ka' => $name,
        'profession_ka' => $profession,
        'bio_ka' => $bio,
        'category' => $category,
        'region' => $region,
        'settlement' => $settlement,
        'languages' => $languages,
        'format_online' => $formatOnline ? 1 : 0,
        'format_in_person' => $formatInPerson ? 1 : 0,
        'price_from' => $priceUnit === 'negotiable' ? '' : $price,
        'price_unit' => $priceUnit,
        'phone' => $phone,
        'facebook_url' => '',
        'instagram_url' => '',
        'status' => 'draft',
    ], $errors];
}

/** @param array<string, mixed> $source
 *  @return array{0:array<string,mixed>,1:array<string,string>}
 */
function validate_public_mentor_request(array $source): array
{
    $value = static fn (string $key, int $max): string => mb_substr(trim((string) ($source[$key] ?? '')), 0, $max, 'UTF-8');
    $name = $value('name', 190);
    $learnerGroup = $value('learner_group', 100);
    $category = $value('category', 120);
    $subject = $value('subject', 190);
    $currentLevel = $value('current_level', 190);
    $learningGoal = $value('learning_goal', 4000);
    $region = $value('region', 120);
    $settlement = $value('settlement', 140);
    $availability = $value('availability', 1000);
    $desiredStart = $value('desired_start', 190);
    $phone = $value('phone', 50);
    $email = $value('email', 190);
    $details = $value('details', 4000);
    $budgetUnit = (string) ($source['budget_unit'] ?? 'negotiable');
    $budget = trim((string) ($source['budget_from'] ?? ''));
    $formatOnline = !empty($source['format_online']);
    $formatInPerson = !empty($source['format_in_person']);
    $errors = [];

    if (mb_strlen($name, 'UTF-8') < 2) $errors['name'] = t('mentor.error_name');
    if (!in_array($learnerGroup, ['child', 'school_student', 'university_student', 'adult'], true)) {
        $errors['learner_group'] = t('mentor.error_learner_group');
        $learnerGroup = '';
    }
    if ($category === '') $errors['category'] = t('register.error_category');
    if ($subject === '') $errors['subject'] = t('mentor.error_subject');
    if ($currentLevel === '') $errors['current_level'] = t('mentor.error_level');
    if (mb_strlen($learningGoal, 'UTF-8') < 10) $errors['learning_goal'] = t('mentor.error_goal');
    if ($region === '') $errors['region'] = t('register.error_region');
    if ($settlement === '') $errors['settlement'] = t('register.error_settlement');
    if (!$formatOnline && !$formatInPerson) $errors['format'] = t('register.error_format');
    if (mb_strlen($availability, 'UTF-8') < 3) $errors['availability'] = t('mentor.error_availability');
    if (!in_array($budgetUnit, ['hour', 'month', 'course', 'lesson', 'negotiable'], true)) {
        $errors['budget_unit'] = t('register.error_price');
        $budgetUnit = 'negotiable';
    }
    if ($budgetUnit !== 'negotiable' && ($budget === '' || !is_numeric($budget) || (float) $budget < 0 || (float) $budget > 99999999.99)) {
        $errors['budget_from'] = t('register.error_price');
    }
    $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($phoneDigits) < 8 || strlen($phoneDigits) > 15) $errors['phone'] = t('register.error_phone');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) $errors['email'] = t('mentor.error_email');
    if (($source['consent'] ?? '') !== '1') $errors['consent'] = t('mentor.error_consent');

    return [[
        'name' => $name,
        'learner_group' => $learnerGroup,
        'category' => $category,
        'subject' => $subject,
        'current_level' => $currentLevel,
        'learning_goal' => $learningGoal,
        'region' => $region,
        'settlement' => $settlement,
        'format_online' => $formatOnline ? 1 : 0,
        'format_in_person' => $formatInPerson ? 1 : 0,
        'availability' => $availability,
        'desired_start' => $desiredStart,
        'budget_from' => $budgetUnit === 'negotiable' ? '' : $budget,
        'budget_unit' => $budgetUnit,
        'phone' => $phone,
        'email' => $email,
        'details' => $details,
        'status' => 'draft',
    ], $errors];
}

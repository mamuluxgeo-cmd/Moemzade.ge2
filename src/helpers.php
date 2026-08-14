<?php

declare(strict_types=1);

use Moemzade\Support\Env;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function resolve_language(): string
{
    $supported = ['ka', 'en', 'ru'];
    $requested = strtolower((string) ($_GET['lang'] ?? $_COOKIE['moemzade_lang'] ?? 'ka'));
    $language = in_array($requested, $supported, true) ? $requested : 'ka';

    if (isset($_GET['lang']) && !headers_sent()) {
        setcookie('moemzade_lang', $language, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    return $language;
}

/** @param array<string, scalar> $replace */
function t(string $key, array $replace = []): string
{
    global $translator;
    return $translator->get($key, $replace);
}

function locale(): string
{
    global $translator;
    return $translator->locale();
}

function url(string $path = ''): string
{
    global $config;
    return $config['url'] . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return '/' . ltrim($path, '/');
}

function localized(array $row, string $field, ?string $language = null): string
{
    $language ??= locale();
    $preferred = trim((string) ($row[$field . '_' . $language] ?? ''));
    if ($preferred !== '') {
        return $preferred;
    }

    foreach (['ka', 'en', 'ru'] as $fallback) {
        $value = trim((string) ($row[$field . '_' . $fallback] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return trim((string) ($row[$field] ?? ''));
}

/** @param array<string, mixed> $data */
function view(string $template, array $data = [], int $status = 200): never
{
    global $config, $translator;
    http_response_code($status);
    extract($data, EXTR_SKIP);
    ob_start();
    require BASE_PATH . '/views/' . $template . '.php';
    $content = (string) ob_get_clean();
    require BASE_PATH . '/views/layout.php';
    exit;
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = (string) ($_POST['_csrf'] ?? '');
    if ($provided === '' || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}

function visitor_hash(): string
{
    global $config;
    $cookieName = 'moemzade_vid';
    $identifier = (string) ($_COOKIE[$cookieName] ?? '');

    if (!preg_match('/^[a-f0-9]{64}$/', $identifier)) {
        $identifier = bin2hex(random_bytes(32));
        $_COOKIE[$cookieName] = $identifier;
        setcookie($cookieName, $identifier, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return hash_hmac('sha256', $identifier, (string) $config['key']);
}

function is_admin(): bool
{
    return ($_SESSION['admin_authenticated'] ?? false) === true;
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('error', t('admin.login_required'));
        redirect('/admin/login');
    }
}

function admin_attempt(string $email, string $password): bool
{
    global $config;
    $expectedEmail = (string) $config['admin']['email'];

    if (!hash_equals($expectedEmail, strtolower(trim($email))) || !admin_password_matches($password)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_email'] = $expectedEmail;
    return true;
}

function admin_password_matches(string $password): bool
{
    global $config;
    $hash = (string) $config['admin']['password_hash'];

    return $hash !== '' && password_verify($password, $hash);
}

function update_env_value(string $path, string $key, string $value): void
{
    if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || strpbrk($value, "\r\n") !== false) {
        throw new InvalidArgumentException('Invalid environment entry.');
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Environment file could not be read.');
    }

    $line = $key . '=' . $value;
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
    if (preg_match($pattern, $contents) === 1) {
        $updated = preg_replace_callback(
            $pattern,
            static fn (array $matches): string => $line,
            $contents
        );
        if (!is_string($updated)) {
            throw new RuntimeException('Environment value could not be updated.');
        }
    } else {
        $updated = rtrim($contents, "\r\n") . PHP_EOL . $line . PHP_EOL;
    }

    $temporary = tempnam(dirname($path), '.env-update-');
    if ($temporary === false) {
        throw new RuntimeException('Temporary environment file could not be created.');
    }

    try {
        if (file_put_contents($temporary, $updated, LOCK_EX) === false || !chmod($temporary, 0600)) {
            throw new RuntimeException('Environment file could not be written securely.');
        }
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Environment file could not be replaced.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

function query_url(string $path, array $changes = []): string
{
    $query = array_merge($_GET, $changes);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** @param array<string, mixed> $options */
function category_option_tags(
    array $options,
    string $selectedCategory = '',
    string $emptyLabel = '—'
): string {
    $html = '<option value="">' . e($emptyLabel) . '</option>';
    $selectedFound = $selectedCategory === '';
    $rendered = [];
    $tree = $options['category_tree'] ?? [];

    if (is_array($tree) && $tree !== []) {
        foreach ($tree as $root) {
            if (!is_array($root)) {
                continue;
            }
            $name = trim((string) ($root['name'] ?? ''));
            if ($name === '' || isset($rendered[$name])) {
                continue;
            }
            $selected = $selectedCategory === $name;
            $selectedFound = $selectedFound || $selected;
            $rendered[$name] = true;
            $html .= '<option value="' . e($name) . '" data-category-depth="0"'
                . ($selected ? ' selected' : '') . '>' . e($name) . '</option>';

            foreach (($root['children'] ?? []) as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $childName = trim((string) ($child['name'] ?? ''));
                if ($childName === '' || isset($rendered[$childName])) {
                    continue;
                }
                $childSelected = $selectedCategory === $childName;
                $selectedFound = $selectedFound || $childSelected;
                $rendered[$childName] = true;
                $html .= '<option value="' . e($childName) . '" data-category-depth="1"'
                    . ($childSelected ? ' selected' : '') . '>↳ ' . e($childName) . '</option>';
            }
        }
    } else {
        foreach (($options['categories'] ?? []) as $category) {
            $name = trim((string) $category);
            if ($name === '' || isset($rendered[$name])) {
                continue;
            }
            $selected = $selectedCategory === $name;
            $selectedFound = $selectedFound || $selected;
            $rendered[$name] = true;
            $html .= '<option value="' . e($name) . '"' . ($selected ? ' selected' : '') . '>'
                . e($name) . '</option>';
        }
    }

    if (!$selectedFound && $selectedCategory !== '') {
        $html .= '<option value="' . e($selectedCategory) . '" selected>' . e($selectedCategory) . '</option>';
    }
    return $html;
}

/** @param array<string, mixed> $options */
function settlement_option_tags(
    array $options,
    string $selectedRegion = '',
    string $selectedSettlement = '',
    string $emptyLabel = '—'
): string {
    $html = '<option value="">' . e($emptyLabel) . '</option>';
    $selectedFound = $selectedSettlement === '';
    foreach (($options['region_settlements'] ?? []) as $region => $settlements) {
        if (!is_array($settlements)) {
            continue;
        }
        foreach ($settlements as $settlement) {
            $region = (string) $region;
            $settlement = (string) $settlement;
            $selected = $selectedRegion === $region && $selectedSettlement === $settlement;
            $selectedFound = $selectedFound || $selected;
            $html .= '<option value="' . e($settlement) . '" data-region="' . e($region) . '"'
                . ($selected ? ' selected' : '') . '>' . e($settlement) . '</option>';
        }
    }
    if (!$selectedFound && $selectedSettlement !== '') {
        $html .= '<option value="' . e($selectedSettlement) . '" data-region="' . e($selectedRegion)
            . '" selected>' . e($selectedSettlement) . '</option>';
    }
    return $html;
}

function slugify(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
    return trim($value, '-');
}

function absolute_url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }
    return url($path);
}

function teacher_price(array $teacher): string
{
    $unit = (string) ($teacher['price_unit'] ?? 'hour');
    if ($unit === 'negotiable' || $teacher['price_from'] === null || $teacher['price_from'] === '') {
        return t('price.negotiable');
    }

    $amount = rtrim(rtrim(number_format((float) $teacher['price_from'], 2, '.', ''), '0'), '.');
    $label = match ($unit) {
        'month' => t('price.month_short'),
        'course' => t('price.course_short'),
        'lesson' => t('price.lesson_short'),
        default => t('price.hour_short'),
    };

    return $amount . ' ₾/' . $label;
}

function mentor_request_budget(array $request): string
{
    $unit = (string) ($request['budget_unit'] ?? 'negotiable');
    if ($unit === 'negotiable' || ($request['budget_from'] ?? null) === null || $request['budget_from'] === '') {
        return t('price.negotiable');
    }

    $amount = rtrim(rtrim(number_format((float) $request['budget_from'], 2, '.', ''), '0'), '.');
    $label = match ($unit) {
        'month' => t('price.month_short'),
        'course' => t('price.course_short'),
        'lesson' => t('price.lesson_short'),
        default => t('price.hour_short'),
    };

    return $amount . ' ₾/' . $label;
}

function mentor_learner_group_label(string $group): string
{
    return match ($group) {
        'child' => t('mentor.group_child'),
        'school_student' => t('mentor.group_school'),
        'university_student' => t('mentor.group_university'),
        'adult' => t('mentor.group_adult'),
        default => $group,
    };
}

function whatsapp_number(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '995')) {
        return $digits;
    }
    $digits = ltrim($digits, '0');
    return strlen($digits) === 9 ? '995' . $digits : $digits;
}

function seo_slug(string $value): string
{
    $known = [
        'მუსიკა' => 'musika', 'ცეკვა' => 'cekva', 'სილამაზე' => 'silamaze',
        'სასკოლო საგნები' => 'saskolo-sagnebi', 'ტექნოლოგია' => 'teqnologia',
        'შემოქმედება' => 'shemoqmedeba', 'ენები' => 'enebi', 'ხელსაქმე' => 'khelsakme',
        'ხელსაქმე / ტექნიკური' => 'khelsakme-teqnikuri',
        'სპორტი და ჯანმრთელობა' => 'sporti-da-janmrteloba', 'კულინარია' => 'kulinaria',
        'ყოფა და ლაიფსტაილი' => 'yofa-da-laipstaili',
        'თეატრი და მედია' => 'teatri-da-media', 'მართვა' => 'martva',
        'ბიზნესი და ფინანსები' => 'biznesi-da-finansebi', 'სხვა' => 'sxva',
        'თბილისი' => 'tbilisi', 'ბათუმი' => 'batumi', 'ქუთაისი' => 'kutaisi',
        'რუსთავი' => 'rustavi', 'ზუგდიდი' => 'zugdidi', 'ფოთი' => 'poti',
        'გორი' => 'gori', 'თელავი' => 'telavi', 'ქობულეთი' => 'qobuleti',
        'ბორჯომი' => 'borjomi', 'ახალციხე' => 'akhaltsikhe', 'მცხეთა' => 'mtskheta',
        'ოზურგეთი' => 'ozurgeti', 'სამტრედია' => 'samtredia', 'ზესტაფონი' => 'zestafoni',
        'სენაკი' => 'senaki', 'მარნეული' => 'marneuli', 'გარდაბანი' => 'gardabani',
        'ქარელი' => 'qareli', 'ხაშური' => 'khashuri',
        'აჭარა' => 'achara', 'იმერეთი' => 'imereti', 'კახეთი' => 'kakheti',
        'შიდა ქართლი' => 'shida-qartli', 'ქვემო ქართლი' => 'qvemo-qartli',
        'სამეგრელო-ზემო სვანეთი' => 'samegrelo-zemo-svaneti', 'გურია' => 'guria',
        'სამცხე-ჯავახეთი' => 'samtskhe-javakheti', 'მცხეთა-მთიანეთი' => 'mtskheta-mtianeti',
        'რაჭა-ლეჩხუმი და ქვემო სვანეთი' => 'racha-lechkhumi-da-qvemo-svaneti',
    ];
    if (isset($known[$value])) {
        return $known[$value];
    }

    $alphabet = [
        'ა'=>'a','ბ'=>'b','გ'=>'g','დ'=>'d','ე'=>'e','ვ'=>'v','ზ'=>'z','თ'=>'t','ი'=>'i','კ'=>'k',
        'ლ'=>'l','მ'=>'m','ნ'=>'n','ო'=>'o','პ'=>'p','ჟ'=>'zh','რ'=>'r','ს'=>'s','ტ'=>'t','უ'=>'u',
        'ფ'=>'f','ქ'=>'q','ღ'=>'gh','ყ'=>'y','შ'=>'sh','ჩ'=>'ch','ც'=>'ts','ძ'=>'dz','წ'=>'ts',
        'ჭ'=>'ch','ხ'=>'kh','ჯ'=>'j','ჰ'=>'h',
    ];
    $value = strtr(mb_strtolower(trim($value), 'UTF-8'), $alphabet);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'page';
}

function category_icon(string $category): string
{
    return match ($category) {
        'სასკოლო საგნები' => '∑',
        'ენები' => '文',
        'ტექნოლოგია' => '⌨',
        'მუსიკა' => '♪',
        'სპორტი და ჯანმრთელობა' => '+',
        'ბიზნესი და ფინანსები' => '↗',
        'შემოქმედება', 'ხელსაქმე' => '◇',
        'ხელსაქმე / ტექნიკური' => '⌘',
        'ცეკვა' => '≈',
        'სილამაზე' => '✦',
        'კულინარია', 'ყოფა და ლაიფსტაილი' => '⌂',
        'თეატრი და მედია' => '◉',
        'მართვა' => '→',
        default => '✦',
    };
}

/** @return array{0: int, 1: int} */
function category_illustration_position(string $category): array
{
    $index = match ($category) {
        'სასკოლო საგნები' => 0,
        'ენები' => 1,
        'მართვა' => 2,
        'სხვა' => 3,
        'ტექნოლოგია' => 4,
        'მუსიკა' => 5,
        'ცეკვა' => 6,
        'სილამაზე' => 7,
        'შემოქმედება' => 8,
        'ხელსაქმე', 'ხელსაქმე / ტექნიკური' => 9,
        'სპორტი და ჯანმრთელობა' => 10,
        'კულინარია', 'ყოფა და ლაიფსტაილი' => 11,
        'თეატრი და მედია' => 12,
        'ბიზნესი და ფინანსები' => 13,
        'მარკეტინგი და გაყიდვები' => 14,
        'სამართალი და იურისპრუდენცია' => 15,
        'კარიერული განვითარება და მენეჯმენტი' => 16,
        default => 17,
    };

    return [($index % 5) * 25, intdiv($index, 5) * 33];
}

function teacher_status_label(string $status): string
{
    return match ($status) {
        'published' => t('status.published'),
        'archived' => t('status.archived'),
        default => t('status.pending'),
    };
}

function xml_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

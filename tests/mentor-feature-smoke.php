<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Translator.php';
require BASE_PATH . '/src/helpers.php';

$config = [
    'url' => 'https://moemzade.ge',
    'debug' => true,
    'media' => ['max_upload_bytes' => 10 * 1024 * 1024],
];
$translator = new Moemzade\Translator('ka');
$_SERVER['REQUEST_URI'] = '/mentor-requests';
$_SESSION = [];
$_GET = [];

/** @param array<string, mixed> $variables */
function render_feature_template(string $template, array $variables): string
{
    extract($variables, EXTR_SKIP);
    ob_start();
    require BASE_PATH . '/views/' . $template . '.php';
    return (string) ob_get_clean();
}

$options = [
    'categories' => ['ენები', 'სასკოლო საგნები'],
    'regions' => ['თბილისი', 'აჭარა'],
    'settlements' => ['თბილისი', 'ბათუმი'],
    'region_settlements' => ['თბილისი' => ['ვაკე', 'საბურთალო'], 'აჭარა' => ['ბათუმი', 'ქობულეთი']],
    'languages' => ['ქართული'],
    'subcategories' => ['ინგლისური', 'მათემატიკა'],
    'category_subcategories' => ['ენები' => ['ინგლისური'], 'სასკოლო საგნები' => ['მათემატიკა']],
];
$request = [
    'id' => 7,
    'slug' => 'english-nino',
    'name' => 'ნინო ბერიძე',
    'learner_group' => 'school_student',
    'category' => 'ენები',
    'subject' => '<script>alert(1)</script>',
    'current_level' => 'B1',
    'learning_goal' => 'საუბრის უნარის გაუმჯობესება',
    'region' => 'თბილისი',
    'settlement' => 'თბილისი',
    'format_online' => 1,
    'format_in_person' => 0,
    'availability' => 'ორშაბათი და ოთხშაბათი 18:00-ის შემდეგ',
    'desired_start' => 'სექტემბრიდან',
    'budget_from' => '35.00',
    'budget_unit' => 'hour',
    'phone' => '555 123 456',
    'email' => 'parent@example.com',
    'details' => 'გამოცდისთვის მომზადება',
    'status' => 'published',
    'published_at' => '2026-08-09 12:00:00',
];

$catalog = render_feature_template('mentor-requests', [
    'filters' => ['q' => '', 'category' => '', 'region' => '', 'settlement' => '', 'format' => ''],
    'requests' => [$request],
    'options' => $options,
    'total' => 1,
    'page' => 1,
    'pages' => 1,
]);
$form = render_feature_template('mentor-request-form', ['old' => [], 'errors' => [], 'options' => $options]);
$detail = render_feature_template('mentor-request', ['request' => $request]);
$home = render_feature_template('home', [
    'stats' => ['teachers' => 93, 'categories' => 13, 'regions' => 12],
    'teachers' => [],
    'mentorRequests' => [$request],
    'categories' => [],
    'options' => $options,
]);
$layout = render_feature_template('layout', [
    'content' => $catalog,
    'pageTitle' => 'Mentor requests — Moemzade.ge',
    'metaDescription' => 'Mentor requests',
    'canonicalUrl' => 'https://moemzade.ge/mentor-requests',
]);

$renderMode = (string) ($argv[1] ?? '');
if ($renderMode === '--render-catalog') {
    echo $layout;
    exit;
}
if ($renderMode === '--render-form') {
    echo render_feature_template('layout', [
        'content' => $form,
        'pageTitle' => 'ვეძებ მენტორს — Moemzade.ge',
        'metaDescription' => 'დაწერე რისი სწავლა გინდა',
        'canonicalUrl' => 'https://moemzade.ge/mentor-requests/new',
        'robots' => 'noindex,follow',
    ]);
    exit;
}
if ($renderMode === '--render-home') {
    echo render_feature_template('layout', [
        'content' => $home,
        'pageTitle' => 'Moemzade.ge',
        'metaDescription' => 'იპოვე სწორი მასწავლებელი',
        'canonicalUrl' => 'https://moemzade.ge/',
    ]);
    exit;
}

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$expect(str_contains($catalog, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Catalog output is not escaped.');
$expect(!str_contains($catalog, '<script>alert(1)</script>'), 'Catalog contains raw script markup.');
$expect(str_contains($catalog, 'href="tel:555123456"'), 'Catalog call action is missing.');
$expect(str_contains($catalog, 'https://wa.me/995555123456'), 'Catalog WhatsApp action is missing.');
$expect(str_contains($form, 'data-registration-form'), 'Three-step request form marker is missing.');
$expect(str_contains($form, 'data-location-form'), 'Dependent region/settlement form marker is missing.');
$expect(str_contains($form, 'data-region="თბილისი"'), 'Region-specific settlement options are missing.');
$expect(str_contains($form, 'name="learner_group"'), 'Learner group field is missing.');
$expect(str_contains($form, 'მშობლის ან მეურვის'), 'Guardian safety notice is missing.');
$expect(str_contains($form, 'name="consent"'), 'Publication consent is missing.');
$expect(str_contains($detail, 'სასწავლო მიზანი'), 'Request detail goal is missing.');
$expect(str_contains($detail, 'შესთავაზე სწავლება'), 'Request detail teaching offer is missing.');
$expect(strpos($home, 'id="latest"') < strpos($home, 'id="mentor-requests"'), 'Mentor requests are not positioned below teachers.');
$expect(str_contains($layout, 'href="/mentor-requests"'), 'Main navigation link is missing.');
$expect(str_contains($layout, 'href="/mentor-requests/new"'), 'Request form link is missing.');
$expect(mentor_request_budget($request) === '35 ₾/სთ', 'Budget formatter returned an unexpected value.');
$expect(mentor_learner_group_label('school_student') === 'სკოლის მოსწავლისთვის', 'Learner group formatter returned an unexpected value.');

$ka = require BASE_PATH . '/lang/ka.php';
$en = require BASE_PATH . '/lang/en.php';
$ru = require BASE_PATH . '/lang/ru.php';
$expect(array_diff_key($ka, $en) === [], 'English translations are incomplete.');
$expect(array_diff_key($ka, $ru) === [], 'Russian translations are incomplete.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Mentor feature render checks passed.\n");

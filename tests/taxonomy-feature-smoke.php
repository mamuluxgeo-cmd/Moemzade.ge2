<?php

declare(strict_types=1);

use Moemzade\Repository;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/src/Translator.php';
require BASE_PATH . '/src/Repository.php';
require BASE_PATH . '/src/helpers.php';

$config = [
    'url' => 'https://moemzade.ge',
    'debug' => true,
    'media' => ['max_upload_bytes' => 10 * 1024 * 1024],
];
$translator = new Moemzade\Translator('ka');
$_SERVER['REQUEST_URI'] = '/admin/regions';
$_SESSION = [];
$_GET = [];

/** @param array<string, mixed> $variables */
function render_taxonomy_template(string $template, array $variables): string
{
    global $config, $translator;
    extract($variables, EXTR_SKIP);
    ob_start();
    require BASE_PATH . '/views/' . $template . '.php';
    return (string) ob_get_clean();
}

$taxonomy = require BASE_PATH . '/config/taxonomy.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec(
    'CREATE TABLE catalog_categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        config_key TEXT UNIQUE,
        sort_order INTEGER NOT NULL DEFAULT 100
    );
    CREATE TABLE catalog_regions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        config_key TEXT UNIQUE,
        sort_order INTEGER NOT NULL DEFAULT 100
    );
    CREATE TABLE catalog_settlements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        region_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        config_key TEXT UNIQUE,
        sort_order INTEGER NOT NULL DEFAULT 100,
        UNIQUE (region_id, name)
    );
    CREATE TABLE teachers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        status TEXT NOT NULL,
        category TEXT NOT NULL,
        region TEXT NOT NULL,
        settlement TEXT NOT NULL,
        profession_ka TEXT NOT NULL DEFAULT \'\',
        languages TEXT NOT NULL DEFAULT \'\'
    );
    CREATE TABLE mentor_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        status TEXT NOT NULL,
        category TEXT NOT NULL,
        region TEXT NOT NULL,
        settlement TEXT NOT NULL
    );
    CREATE TABLE search_events (id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT, region TEXT);
    CREATE TABLE match_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT, region TEXT);'
);

$db->exec(
    "INSERT INTO catalog_categories (name, config_key, sort_order) VALUES
        ('ენები', 'ენები', 10),
        ('სასკოლო საგნები', 'სასკოლო საგნები', 20);
     INSERT INTO catalog_regions (name, config_key, sort_order) VALUES
        ('თბილისი', 'თბილისი', 10),
        ('აჭარა', 'აჭარა', 20);
     INSERT INTO catalog_settlements (region_id, name, config_key, sort_order) VALUES
        (1, 'ვაკე', 'tbilisi-vake', 10),
        (1, 'საბურთალო', 'tbilisi-saburtalo', 20),
        (2, 'ბათუმი', 'adjara-batumi', 10);
     INSERT INTO teachers (status, category, region, settlement, profession_ka, languages)
        VALUES ('published', 'ენები', 'თბილისი', 'ვაკე', 'ინგლისური', 'ქართული, ინგლისური');
     INSERT INTO mentor_requests (status, category, region, settlement)
        VALUES ('published', 'ენები', 'თბილისი', 'ვაკე');
     INSERT INTO search_events (category, region) VALUES ('ენები', 'თბილისი');
     INSERT INTO match_requests (category, region) VALUES ('ენები', 'თბილისი');"
);

$repository = new Repository($db, $taxonomy);
$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$options = $repository->filterOptions();
$expect($options['region_settlements']['თბილისი'] === ['ვაკე', 'საბურთალო'], 'თბილისის დასახლებები არასწორად დაჯგუფდა.');
$expect($options['region_settlements']['აჭარა'] === ['ბათუმი'], 'აჭარის დასახლებები არასწორად დაჯგუფდა.');
$expect(in_array('ინგლისური', $options['category_subcategories']['ენები'], true), 'არსებული პროფესია სფეროს არ მიება.');

$newCategoryId = $repository->createCatalogCategory('ბიზნესი და ფინანსები', 5);
$expect($repository->filterOptions()['categories'][0] === 'ბიზნესი და ფინანსები', 'ახალი სფეროს რიგითობა საჯარო სიაში არ აისახა.');
$childCategoryId = $repository->createCatalogCategory('ბიზნესის დაგეგმვა', 10, $newCategoryId);
$categoryTree = $repository->filterOptions()['category_tree'];
$expect(
    ($categoryTree[0]['children'][0]['name'] ?? '') === 'ბიზნესის დაგეგმვა',
    'ქვესფერო მშობელი სფეროს ქვეშ არ გამოჩნდა.'
);

$thirdLevelRejected = false;
try {
    $repository->createCatalogCategory('დაუშვებელი მესამე დონე', 10, $childCategoryId);
} catch (Throwable) {
    $thirdLevelRejected = true;
}
$expect($thirdLevelRejected, 'მესამე დონის სფერო არ დაიბლოკა.');

$repository->updateCatalogCategory(1, 'ენები და თარგმნა', 10);
foreach (['teachers', 'mentor_requests', 'search_events', 'match_requests'] as $table) {
    $value = (string) $db->query("SELECT category FROM {$table} LIMIT 1")->fetchColumn();
    $expect($value === 'ენები და თარგმნა', "სფეროს გადარქმევა {$table}-ში არ გავრცელდა.");
}

$repository->reorderCatalogCategories([
    ['id' => 2, 'parent_id' => null, 'sort_order' => 10],
    ['id' => 1, 'parent_id' => null, 'sort_order' => 20],
    ['id' => $newCategoryId, 'parent_id' => null, 'sort_order' => 30],
    ['id' => $childCategoryId, 'parent_id' => $newCategoryId, 'sort_order' => 10],
]);
$expect(
    $repository->filterOptions()['categories'] === ['სასკოლო საგნები', 'ენები და თარგმნა', 'ბიზნესი და ფინანსები', 'ბიზნესის დაგეგმვა'],
    'გადაადგილებული სფეროების რიგითობა საჯარო სიაში არ აისახა.'
);

$parentDeleteRejected = false;
try {
    $repository->deleteCatalogCategory($newCategoryId);
} catch (Throwable) {
    $parentDeleteRejected = true;
}
$expect($parentDeleteRejected, 'ქვესფეროებიანი სფეროს წაშლა არ დაიბლოკა.');

$disposableCategoryId = $repository->createCatalogCategory('დროებითი სფერო', 999);
$repository->deleteCatalogCategory($disposableCategoryId);
$expect(
    !in_array('დროებითი სფერო', $repository->filterOptions()['categories'], true),
    'წაშლილი სფერო საჯარო სიიდან არ გაქრა.'
);

$newRegionId = $repository->createCatalogRegion('ახალი რეგიონი', 30);
$newSettlementId = $repository->createCatalogSettlement($newRegionId, 'ახალი ქალაქი', 10);
$expect($newRegionId > 0 && $newSettlementId > 0, 'რეგიონის ან ქალაქის დამატება ვერ შესრულდა.');
$expect(
    $repository->filterOptions()['region_settlements']['ახალი რეგიონი'] === ['ახალი ქალაქი'],
    'ახალი რეგიონი და ქალაქი საჯარო პარამეტრებში არ გამოჩნდა.'
);

$repository->updateCatalogRegion(1, 'თბილისის რეგიონი', 10);
foreach (['teachers', 'mentor_requests', 'search_events', 'match_requests'] as $table) {
    $value = (string) $db->query("SELECT region FROM {$table} LIMIT 1")->fetchColumn();
    $expect($value === 'თბილისის რეგიონი', "რეგიონის გადარქმევა {$table}-ში არ გავრცელდა.");
}

$repository->updateCatalogSettlement(1, 2, 'ბათუმის ძველი უბანი', 30);
$teacherLocation = $db->query('SELECT region, settlement FROM teachers LIMIT 1')->fetch();
$expect(
    $teacherLocation === ['region' => 'აჭარა', 'settlement' => 'ბათუმის ძველი უბანი'],
    'ქალაქის გადატანა დაკავშირებულ მასწავლებელზე არ გავრცელდა.'
);
$requestLocation = $db->query('SELECT region, settlement FROM mentor_requests LIMIT 1')->fetch();
$expect(
    $requestLocation === ['region' => 'აჭარა', 'settlement' => 'ბათუმის ძველი უბანი'],
    'ქალაქის გადატანა დაკავშირებულ განცხადებაზე არ გავრცელდა.'
);

$selectionCheck = new ReflectionMethod(Repository::class, 'assertCatalogSelection');
$selectionCheck->setAccessible(true);
$selectionCheck->invoke($repository, 'ენები და თარგმნა', 'აჭარა', 'ბათუმის ძველი უბანი');
$invalidPairRejected = false;
try {
    $selectionCheck->invoke($repository, 'ენები და თარგმნა', 'თბილისის რეგიონი', 'ბათუმი');
} catch (Throwable) {
    $invalidPairRejected = true;
}
$expect($invalidPairRejected, 'რეგიონთან შეუსაბამო ქალაქი არ დაიბლოკა.');

$categories = $repository->adminCatalogCategories();
$regions = $repository->adminCatalogRegions();
$settlements = $repository->adminCatalogSettlements();
$categoriesHtml = render_taxonomy_template('admin/categories', ['categories' => $categories]);
$regionsHtml = render_taxonomy_template('admin/regions', ['regions' => $regions, 'settlements' => $settlements]);
$registerHtml = render_taxonomy_template('register', [
    'old' => [],
    'errors' => [],
    'options' => $repository->filterOptions(),
]);

$expect(str_contains($categoriesHtml, 'action="/admin/categories"'), 'სფეროს დამატების ადმინ-ფორმა არ გამოჩნდა.');
$expect(str_contains($categoriesHtml, 'ბიზნესი და ფინანსები'), 'ახალი სფერო ადმინ-გვერდზე არ გამოჩნდა.');
$expect(str_contains($categoriesHtml, 'data-category-tree'), 'სფეროების ხისებრი რედაქტორი არ გამოჩნდა.');
$expect(str_contains($categoriesHtml, 'action="/admin/categories/reorder"'), 'სფეროების განლაგების შენახვა არ გამოჩნდა.');
$expect(str_contains($regionsHtml, 'action="/admin/settlements"'), 'ქალაქის დამატების ადმინ-ფორმა არ გამოჩნდა.');
$expect(str_contains($regionsHtml, 'ახალი რეგიონი'), 'ახალი რეგიონი ადმინ-გვერდზე არ გამოჩნდა.');
$expect(str_contains($registerHtml, 'data-location-form'), 'რეგისტრაციის დამოკიდებული მდებარეობის მარკერი აკლია.');
$expect(str_contains($registerHtml, 'data-region="აჭარა"'), 'რეგისტრაციაში რეგიონთან მიბმული ქალაქები არ გამოჩნდა.');
$expect(str_contains($registerHtml, '↳ ბიზნესის დაგეგმვა'), 'რეგისტრაციაში ქვესფეროს იერარქია არ გამოჩნდა.');

$configuredRegions = array_fill_keys($taxonomy['regions'], true);
foreach ($taxonomy['region_settlements'] as $region => $items) {
    $expect(isset($configuredRegions[$region]), "{$region}-ის ქალაქები უცნობ რეგიონზეა მიბმული.");
    $expect(is_array($items) && $items !== [], "{$region}-ს ქალაქების ცარიელი სია აქვს.");
}

$expect($newCategoryId > 0, 'ახალი სფეროს ID არ დაბრუნდა.');
$expect($childCategoryId > 0, 'ახალი ქვესფეროს ID არ დაბრუნდა.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Taxonomy catalog and admin checks passed.\n");

<?php

declare(strict_types=1);

namespace Moemzade;

use PDO;
use RuntimeException;

final class Repository
{
    /** @var array{categories:list<string>,category_tree:list<array{id:int,name:string,children:list<array{id:int,name:string}>}>,regions:list<string>,settlements:list<string>,region_settlements:array<string,list<string>>,languages:list<string>,subcategories:list<string>,category_subcategories:array<string,list<string>>}|null */
    private ?array $filterOptionsCache = null;
    private bool $categoryMediaReady = false;
    private bool $categoryHierarchyReady = false;
    private bool $teacherCardCropReady = false;

    /** @param array<string, mixed> $taxonomy */
    public function __construct(private readonly PDO $db, private readonly array $taxonomy = [])
    {
    }

    /** @return array{teachers:int,categories:int,regions:int} */
    public function homeStats(): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS teachers,
                    COUNT(DISTINCT NULLIF(category, '')) AS categories,
                    COUNT(DISTINCT NULLIF(region, '')) AS regions
             FROM teachers WHERE status = 'published'"
        )->fetch() ?: [];

        $options = $this->filterOptions();

        return [
            'teachers' => (int) ($row['teachers'] ?? 0),
            'categories' => count($options['categories']),
            'regions' => count($options['regions']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function latestTeachers(int $limit = 6): array
    {
        $limit = max(1, min(24, $limit));
        $statement = $this->db->query(
            "SELECT t.*, m.public_url AS photo_url
             FROM teachers t
             LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'profile'
             WHERE t.status = 'published'
             ORDER BY t.published_at DESC, t.id DESC
             LIMIT {$limit}"
        );
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function carouselTeachers(): array
    {
        return $this->db->query(
            "SELECT t.*, m.public_url AS photo_url
             FROM teachers t
             LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'profile'
             WHERE t.status = 'published'
             ORDER BY t.id"
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function latestMentorRequests(int $limit = 6): array
    {
        $limit = max(1, min(24, $limit));
        return $this->db->query(
            "SELECT * FROM mentor_requests
             WHERE status = 'published'
             ORDER BY published_at DESC, id DESC
             LIMIT {$limit}"
        )->fetchAll();
    }

    /** @return list<array{category:string,total:int,image_url:string}> */
    public function categorySummaries(int $limit = 30): array
    {
        $this->ensureCategoryHierarchySchema();
        $this->ensureCategoryMediaTable();
        $limit = max(1, min(30, $limit));
        $rows = $this->db->query(
            "SELECT category, COUNT(*) AS total
             FROM teachers
             WHERE status = 'published' AND category <> ''
             GROUP BY category
             ORDER BY category"
        )->fetchAll();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(string) $row['category']] = (int) $row['total'];
        }

        $imageRows = $this->db->query(
            'SELECT c.name, m.public_url
             FROM catalog_categories c
             LEFT JOIN catalog_category_media m ON m.category_id = c.id
             WHERE c.is_active = 1'
        )->fetchAll();
        $images = [];
        foreach ($imageRows as $row) {
            $images[(string) $row['name']] = (string) ($row['public_url'] ?? '');
        }

        $summaries = [];
        foreach ($this->filterOptions()['categories'] as $category) {
            $summaries[] = [
                'category' => $category,
                'total' => $totals[$category] ?? 0,
                'image_url' => $images[$category] ?? '',
            ];
        }

        return array_slice($summaries, 0, $limit);
    }

    /** @return array{categories:list<string>,category_tree:list<array{id:int,name:string,children:list<array{id:int,name:string}>}>,regions:list<string>,settlements:list<string>,region_settlements:array<string,list<string>>,languages:list<string>,subcategories:list<string>,category_subcategories:array<string,list<string>>} */
    public function filterOptions(): array
    {
        if ($this->filterOptionsCache !== null) {
            return $this->filterOptionsCache;
        }

        $this->ensureCategoryHierarchySchema();

        $categoryRows = $this->db->query(
            'SELECT id, name, config_key, parent_id, sort_order
             FROM catalog_categories
             WHERE is_active = 1
             ORDER BY parent_id IS NOT NULL, sort_order, name'
        )->fetchAll();
        $categoryTree = self::categoryTree($categoryRows);
        $categories = [];
        foreach ($categoryTree as $root) {
            $categories[] = (string) $root['name'];
            foreach ($root['children'] as $child) {
                $categories[] = (string) $child['name'];
            }
        }

        $regionRows = $this->db->query(
            'SELECT id, name FROM catalog_regions ORDER BY sort_order, name'
        )->fetchAll();
        $regions = array_map(static fn (array $row): string => (string) $row['name'], $regionRows);

        $settlementRows = $this->db->query(
            'SELECT r.name AS region, s.name
             FROM catalog_settlements s
             JOIN catalog_regions r ON r.id = s.region_id
             ORDER BY r.sort_order, r.name, s.sort_order, s.name'
        )->fetchAll();
        $regionSettlements = [];
        foreach ($regions as $region) {
            $regionSettlements[$region] = [];
        }
        foreach ($settlementRows as $row) {
            $region = (string) $row['region'];
            $regionSettlements[$region] ??= [];
            $regionSettlements[$region][] = (string) $row['name'];
        }

        $legacyPairs = $this->db->query(
            "SELECT region, settlement FROM teachers WHERE status = 'published' AND region <> '' AND settlement <> ''
             UNION SELECT region, settlement FROM mentor_requests WHERE status = 'published' AND region <> '' AND settlement <> ''
             ORDER BY region, settlement"
        )->fetchAll();
        foreach ($legacyPairs as $row) {
            $region = trim((string) $row['region']);
            $settlement = trim((string) $row['settlement']);
            if ($region === '' || $settlement === '') {
                continue;
            }
            if (!in_array($region, $regions, true)) {
                $regions[] = $region;
            }
            $regionSettlements[$region] = self::mergeOptions($regionSettlements[$region] ?? [], [$settlement]);
        }

        $professionRows = $this->db->query(
            "SELECT category, profession_ka FROM teachers
             WHERE status = 'published' AND profession_ka <> ''
             ORDER BY category, profession_ka"
        )->fetchAll();

        $languageRows = $this->db->query(
            "SELECT languages FROM teachers WHERE status = 'published' AND languages <> ''"
        )->fetchAll(PDO::FETCH_COLUMN);
        $languages = [];
        foreach ($languageRows as $row) {
            foreach (array_filter(array_map('trim', explode(',', (string) $row))) as $language) {
                $languages[$language] = true;
            }
        }
        $languageList = array_keys($languages);
        sort($languageList, SORT_NATURAL | SORT_FLAG_CASE);

        $categorySubcategories = [];
        $catalogSubcategories = [];
        foreach ($categoryRows as $row) {
            $category = trim((string) $row['name']);
            $configKey = trim((string) $row['config_key']);
            $items = $this->taxonomy['categories'][$configKey] ?? [];
            if ($category === '') {
                continue;
            }
            $categorySubcategories[$category] = self::mergeOptions([], is_array($items) ? $items : []);
        }
        foreach ($professionRows as $row) {
            $category = trim((string) $row['category']);
            $profession = trim((string) $row['profession_ka']);
            if ($profession === '') {
                continue;
            }
            if ($category !== '' && isset($categorySubcategories[$category])) {
                $categorySubcategories[$category] = self::mergeOptions($categorySubcategories[$category], [$profession]);
            } else {
                $catalogSubcategories = self::mergeOptions($catalogSubcategories, [$profession]);
            }
        }
        foreach ($categorySubcategories as $items) {
            $catalogSubcategories = self::mergeOptions($catalogSubcategories, $items);
        }

        $settlements = [];
        foreach ($regionSettlements as $items) {
            $settlements = self::mergeOptions($settlements, $items);
        }

        $this->filterOptionsCache = [
            'categories' => self::mergeOptions($categories, array_keys($categorySubcategories)),
            'category_tree' => $categoryTree,
            'regions' => array_values($regions),
            'settlements' => $settlements,
            'region_settlements' => $regionSettlements,
            'languages' => $languageList,
            'subcategories' => $catalogSubcategories,
            'category_subcategories' => $categorySubcategories,
        ];

        return $this->filterOptionsCache;
    }

    /** @param array<mixed> $preferred
     *  @param array<mixed> $existing
     *  @return list<string>
     */
    private static function mergeOptions(array $preferred, array $existing): array
    {
        $result = [];
        $included = [];
        foreach (array_merge($preferred, $existing) as $value) {
            $value = trim((string) $value);
            if ($value === '' || isset($included[$value])) {
                continue;
            }
            $included[$value] = true;
            $result[] = $value;
        }
        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{id:int,name:string,children:list<array{id:int,name:string}>}>
     */
    private static function categoryTree(array $rows): array
    {
        $roots = [];
        $children = [];
        foreach ($rows as $row) {
            $item = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
            ];
            $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;
            if ($parentId > 0) {
                $children[$parentId][] = $item;
            } else {
                $roots[] = $item;
            }
        }

        $tree = [];
        $rootIds = [];
        foreach ($roots as $root) {
            $rootIds[$root['id']] = true;
            $tree[] = [
                'id' => $root['id'],
                'name' => $root['name'],
                'children' => $children[$root['id']] ?? [],
            ];
        }

        // An orphaned child should remain selectable instead of silently disappearing.
        foreach ($children as $parentId => $items) {
            if (isset($rootIds[$parentId])) {
                continue;
            }
            foreach ($items as $item) {
                $tree[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'children' => [],
                ];
            }
        }

        return $tree;
    }

    public function seedTaxonomyCatalog(): void
    {
        $this->ensureCategoryHierarchySchema();
        $this->db->beginTransaction();
        try {
            $insertCategory = $this->db->prepare(
                'INSERT INTO catalog_categories (name, config_key, sort_order)
                 VALUES (:name, :config_key, :sort_order)
                 ON DUPLICATE KEY UPDATE config_key = COALESCE(config_key, VALUES(config_key))'
            );
            $categoryOrder = 10;
            foreach (array_keys($this->taxonomy['categories'] ?? []) as $category) {
                $category = trim((string) $category);
                if ($category === '') {
                    continue;
                }
                $insertCategory->execute([
                    'name' => $category,
                    'config_key' => $category,
                    'sort_order' => $categoryOrder,
                ]);
                $categoryOrder += 10;
            }

            $legacyCategories = $this->db->query(
                "SELECT category FROM teachers WHERE category <> ''
                 UNION SELECT category FROM mentor_requests WHERE category <> ''
                 ORDER BY category"
            )->fetchAll(PDO::FETCH_COLUMN);
            $insertLegacyCategory = $this->db->prepare(
                'INSERT IGNORE INTO catalog_categories (name, config_key, sort_order)
                 VALUES (:name, NULL, :sort_order)'
            );
            foreach ($legacyCategories as $category) {
                $insertLegacyCategory->execute([
                    'name' => trim((string) $category),
                    'sort_order' => $categoryOrder,
                ]);
                $categoryOrder += 10;
            }

            $insertRegion = $this->db->prepare(
                'INSERT INTO catalog_regions (name, config_key, sort_order)
                 VALUES (:name, :config_key, :sort_order)
                 ON DUPLICATE KEY UPDATE config_key = COALESCE(config_key, VALUES(config_key))'
            );
            $regionOrder = 10;
            foreach (($this->taxonomy['regions'] ?? []) as $region) {
                $region = trim((string) $region);
                if ($region === '') {
                    continue;
                }
                $insertRegion->execute([
                    'name' => $region,
                    'config_key' => $region,
                    'sort_order' => $regionOrder,
                ]);
                $regionOrder += 10;
            }

            $legacyRegions = $this->db->query(
                "SELECT region FROM teachers WHERE region <> ''
                 UNION SELECT region FROM mentor_requests WHERE region <> ''
                 ORDER BY region"
            )->fetchAll(PDO::FETCH_COLUMN);
            $insertLegacyRegion = $this->db->prepare(
                'INSERT IGNORE INTO catalog_regions (name, config_key, sort_order)
                 VALUES (:name, NULL, :sort_order)'
            );
            foreach ($legacyRegions as $region) {
                $insertLegacyRegion->execute([
                    'name' => trim((string) $region),
                    'sort_order' => $regionOrder,
                ]);
                $regionOrder += 10;
            }

            $findRegion = $this->db->prepare(
                'SELECT id FROM catalog_regions
                 WHERE config_key = :config_key OR name = :name
                 ORDER BY config_key IS NULL
                 LIMIT 1'
            );
            $insertSettlement = $this->db->prepare(
                'INSERT INTO catalog_settlements (region_id, name, config_key, sort_order)
                 VALUES (:region_id, :name, :config_key, :sort_order)
                 ON DUPLICATE KEY UPDATE config_key = COALESCE(config_key, VALUES(config_key))'
            );
            foreach (($this->taxonomy['region_settlements'] ?? []) as $regionKey => $settlements) {
                if (!is_array($settlements)) {
                    continue;
                }
                $findRegion->execute(['config_key' => (string) $regionKey, 'name' => (string) $regionKey]);
                $regionId = (int) $findRegion->fetchColumn();
                if ($regionId <= 0) {
                    continue;
                }
                $settlementOrder = 10;
                foreach ($settlements as $settlement) {
                    $settlement = trim((string) $settlement);
                    if ($settlement === '') {
                        continue;
                    }
                    $insertSettlement->execute([
                        'region_id' => $regionId,
                        'name' => $settlement,
                        'config_key' => hash('sha256', (string) $regionKey . "\0" . $settlement),
                        'sort_order' => $settlementOrder,
                    ]);
                    $settlementOrder += 10;
                }
            }

            $legacyPairs = $this->db->query(
                "SELECT region, settlement FROM teachers WHERE region <> '' AND settlement <> ''
                 UNION SELECT region, settlement FROM mentor_requests WHERE region <> '' AND settlement <> ''
                 ORDER BY region, settlement"
            )->fetchAll();
            $findRegionByName = $this->db->prepare('SELECT id FROM catalog_regions WHERE name = :name LIMIT 1');
            $insertLegacySettlement = $this->db->prepare(
                'INSERT IGNORE INTO catalog_settlements (region_id, name, config_key, sort_order)
                 VALUES (:region_id, :name, NULL, :sort_order)'
            );
            $legacySettlementOrder = 1000;
            foreach ($legacyPairs as $pair) {
                $findRegionByName->execute(['name' => trim((string) $pair['region'])]);
                $regionId = (int) $findRegionByName->fetchColumn();
                if ($regionId <= 0) {
                    continue;
                }
                $insertLegacySettlement->execute([
                    'region_id' => $regionId,
                    'name' => trim((string) $pair['settlement']),
                    'sort_order' => $legacySettlementOrder,
                ]);
                $legacySettlementOrder += 10;
            }

            $this->db->commit();
            $this->filterOptionsCache = null;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function adminCatalogCategories(): array
    {
        $this->ensureCategoryHierarchySchema();
        $this->ensureCategoryMediaTable();
        return $this->db->query(
            'SELECT c.*,
                    p.name AS parent_name,
                    cm.public_url AS image_url,
                    cm.storage_key AS image_storage_key,
                    cm.storage_driver AS image_storage_driver,
                    (SELECT COUNT(*) FROM teachers t WHERE t.category = c.name) AS teacher_count,
                    (SELECT COUNT(*) FROM mentor_requests mr WHERE mr.category = c.name) AS request_count,
                    (SELECT COUNT(*) FROM catalog_categories ch WHERE ch.parent_id = c.id AND ch.is_active = 1) AS child_count
             FROM catalog_categories c
             LEFT JOIN catalog_categories p ON p.id = c.parent_id
             LEFT JOIN catalog_category_media cm ON cm.category_id = c.id
             WHERE c.is_active = 1
             ORDER BY c.parent_id IS NOT NULL, c.sort_order, c.name'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function categoryMedia(int $categoryId): ?array
    {
        $this->ensureCategoryMediaTable();
        $statement = $this->db->prepare('SELECT * FROM catalog_category_media WHERE category_id = :category_id LIMIT 1');
        $statement->execute(['category_id' => $categoryId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $media */
    public function saveCategoryMedia(int $categoryId, array $media): void
    {
        $this->ensureCategoryMediaTable();
        $this->catalogRow('catalog_categories', $categoryId);
        $statement = $this->db->prepare(
            'INSERT INTO catalog_category_media
                (category_id, storage_driver, storage_key, public_url, width, height, bytes, mime)
             VALUES
                (:category_id, :storage_driver, :storage_key, :public_url, :width, :height, :bytes, :mime)
             ON DUPLICATE KEY UPDATE
                storage_driver = VALUES(storage_driver), storage_key = VALUES(storage_key),
                public_url = VALUES(public_url), width = VALUES(width), height = VALUES(height),
                bytes = VALUES(bytes), mime = VALUES(mime), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'category_id' => $categoryId,
            'storage_driver' => (string) $media['driver'],
            'storage_key' => (string) $media['key'],
            'public_url' => (string) $media['url'],
            'width' => (int) $media['width'],
            'height' => (int) $media['height'],
            'bytes' => (int) $media['bytes'],
            'mime' => (string) $media['mime'],
        ]);
    }

    public function deleteCategoryMedia(int $categoryId): void
    {
        $this->ensureCategoryMediaTable();
        $statement = $this->db->prepare('DELETE FROM catalog_category_media WHERE category_id = :category_id');
        $statement->execute(['category_id' => $categoryId]);
    }

    private function ensureCategoryMediaTable(): void
    {
        if ($this->categoryMediaReady) {
            return;
        }
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS catalog_category_media (
                    category_id INTEGER NOT NULL PRIMARY KEY,
                    storage_driver VARCHAR(32) NOT NULL,
                    storage_key VARCHAR(500) NOT NULL,
                    public_url VARCHAR(1000) NOT NULL,
                    width INTEGER NOT NULL,
                    height INTEGER NOT NULL,
                    bytes INTEGER NOT NULL,
                    mime VARCHAR(100) NOT NULL DEFAULT 'image/webp',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES catalog_categories(id) ON DELETE CASCADE
                )"
            );
        } else {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS catalog_category_media (
                category_id BIGINT UNSIGNED NOT NULL,
                storage_driver VARCHAR(32) NOT NULL,
                storage_key VARCHAR(500) NOT NULL,
                public_url VARCHAR(1000) NOT NULL,
                width INT UNSIGNED NOT NULL,
                height INT UNSIGNED NOT NULL,
                bytes INT UNSIGNED NOT NULL,
                mime VARCHAR(100) NOT NULL DEFAULT 'image/webp',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (category_id),
                CONSTRAINT catalog_category_media_category_fk FOREIGN KEY (category_id)
                    REFERENCES catalog_categories(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $this->categoryMediaReady = true;
    }

    /** @return list<array<string, mixed>> */
    public function adminCatalogRegions(): array
    {
        return $this->db->query(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM catalog_settlements s WHERE s.region_id = r.id) AS settlement_count,
                    (SELECT COUNT(*) FROM teachers t WHERE t.region = r.name) AS teacher_count,
                    (SELECT COUNT(*) FROM mentor_requests m WHERE m.region = r.name) AS request_count
             FROM catalog_regions r
             ORDER BY r.sort_order, r.name'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function adminCatalogSettlements(): array
    {
        return $this->db->query(
            'SELECT s.*, r.name AS region_name,
                    (SELECT COUNT(*) FROM teachers t WHERE t.region = r.name AND t.settlement = s.name) AS teacher_count,
                    (SELECT COUNT(*) FROM mentor_requests m WHERE m.region = r.name AND m.settlement = s.name) AS request_count
             FROM catalog_settlements s
             JOIN catalog_regions r ON r.id = s.region_id
             ORDER BY r.sort_order, r.name, s.sort_order, s.name'
        )->fetchAll();
    }

    public function createCatalogCategory(string $name, int $sortOrder, ?int $parentId = null): int
    {
        $this->ensureCategoryHierarchySchema();
        $name = self::catalogName($name, 120);
        $parentId = $this->categoryParentId($parentId);
        $existingStatement = $this->db->prepare(
            'SELECT id, is_active FROM catalog_categories WHERE name = :name LIMIT 1'
        );
        $existingStatement->execute(['name' => $name]);
        $existing = $existingStatement->fetch();
        if (is_array($existing)) {
            if ((int) $existing['is_active'] === 1) {
                throw new RuntimeException('ასეთი მნიშვნელობა უკვე არსებობს.');
            }
            $statement = $this->db->prepare(
                'UPDATE catalog_categories
                 SET parent_id = :parent_id, is_active = 1, sort_order = :sort_order
                 WHERE id = :id'
            );
            $statement->execute([
                'parent_id' => $parentId,
                'sort_order' => self::catalogSortOrder($sortOrder),
                'id' => (int) $existing['id'],
            ]);
            $this->filterOptionsCache = null;
            return (int) $existing['id'];
        }

        $statement = $this->db->prepare(
            'INSERT INTO catalog_categories (name, config_key, parent_id, is_active, sort_order)
             VALUES (:name, NULL, :parent_id, 1, :sort_order)'
        );
        $statement->execute([
            'name' => $name,
            'parent_id' => $parentId,
            'sort_order' => self::catalogSortOrder($sortOrder),
        ]);
        $this->filterOptionsCache = null;
        return (int) $this->db->lastInsertId();
    }

    public function updateCatalogCategory(int $id, string $name, int $sortOrder, ?int $parentId = null): void
    {
        $this->ensureCategoryHierarchySchema();
        $name = self::catalogName($name, 120);
        $current = $this->catalogRow('catalog_categories', $id);
        if ((int) ($current['is_active'] ?? 1) !== 1) {
            throw new RuntimeException('სფერო ვერ მოიძებნა.');
        }
        $parentId = $this->categoryParentId($parentId, $id);
        $this->assertCatalogNameAvailable('catalog_categories', $name, $id);

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'UPDATE catalog_categories
                 SET name = :name, parent_id = :parent_id, sort_order = :sort_order
                 WHERE id = :id'
            );
            $statement->execute([
                'name' => $name,
                'parent_id' => $parentId,
                'sort_order' => self::catalogSortOrder($sortOrder),
                'id' => $id,
            ]);
            $this->replaceTextValue('teachers', 'category', (string) $current['name'], $name);
            $this->replaceTextValue('mentor_requests', 'category', (string) $current['name'], $name);
            $this->replaceTextValue('search_events', 'category', (string) $current['name'], $name);
            $this->replaceTextValue('match_requests', 'category', (string) $current['name'], $name);
            $this->db->commit();
            $this->filterOptionsCache = null;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function deleteCatalogCategory(int $id): void
    {
        $this->ensureCategoryHierarchySchema();
        $category = $this->catalogRow('catalog_categories', $id);
        if ((int) ($category['is_active'] ?? 1) !== 1) {
            throw new RuntimeException('სფერო ვერ მოიძებნა.');
        }
        $name = (string) $category['name'];
        $teacherStatement = $this->db->prepare('SELECT COUNT(*) FROM teachers WHERE category = :name');
        $teacherStatement->execute(['name' => $name]);
        $teacherCount = (int) $teacherStatement->fetchColumn();
        $requestStatement = $this->db->prepare('SELECT COUNT(*) FROM mentor_requests WHERE category = :name');
        $requestStatement->execute(['name' => $name]);
        $requestCount = (int) $requestStatement->fetchColumn();
        $childStatement = $this->db->prepare(
            'SELECT COUNT(*) FROM catalog_categories WHERE parent_id = :id AND is_active = 1'
        );
        $childStatement->execute(['id' => $id]);
        $childCount = (int) $childStatement->fetchColumn();

        if ($teacherCount > 0 || $requestCount > 0 || $childCount > 0) {
            throw new \RuntimeException(
                "სფერო ვერ წაიშლება: მას იყენებს {$teacherCount} მასწავლებელი, {$requestCount} განცხადება და {$childCount} ქვესფერო. ჯერ გადაიტანეთ ისინი სხვა სფეროში."
            );
        }

        if ($category['config_key'] !== null && trim((string) $category['config_key']) !== '') {
            $this->deleteCategoryMedia($id);
            $statement = $this->db->prepare(
                'UPDATE catalog_categories SET parent_id = NULL, is_active = 0 WHERE id = :id'
            );
            $statement->execute(['id' => $id]);
        } else {
            $statement = $this->db->prepare('DELETE FROM catalog_categories WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
        $this->filterOptionsCache = null;
    }

    /** @param list<array<string, mixed>> $items */
    public function reorderCatalogCategories(array $items): void
    {
        $this->ensureCategoryHierarchySchema();
        if ($items === [] || count($items) > 500) {
            throw new RuntimeException('სფეროების განლაგება არასწორია.');
        }

        $rows = $this->db->query(
            'SELECT id FROM catalog_categories WHERE is_active = 1 ORDER BY id'
        )->fetchAll(PDO::FETCH_COLUMN);
        $expectedIds = array_map('intval', $rows);
        sort($expectedIds, SORT_NUMERIC);

        $structure = [];
        foreach ($items as $position => $item) {
            if (!is_array($item)) {
                throw new RuntimeException('სფეროების განლაგება არასწორია.');
            }
            $id = (int) ($item['id'] ?? 0);
            $parentId = isset($item['parent_id']) && (int) $item['parent_id'] > 0
                ? (int) $item['parent_id'] : null;
            if ($id <= 0 || isset($structure[$id])) {
                throw new RuntimeException('სფეროების სიაში განმეორებული ან უცნობი ჩანაწერია.');
            }
            $structure[$id] = [
                'parent_id' => $parentId,
                'sort_order' => self::catalogSortOrder((int) ($item['sort_order'] ?? (($position + 1) * 10))),
            ];
        }

        $submittedIds = array_keys($structure);
        sort($submittedIds, SORT_NUMERIC);
        if ($submittedIds !== $expectedIds) {
            throw new RuntimeException('სიის შენახვამდე გვერდი განაახლეთ — სფეროების შემადგენლობა შეიცვალა.');
        }

        $childCounts = [];
        foreach ($structure as $id => $item) {
            $parentId = $item['parent_id'];
            if ($parentId === null) {
                continue;
            }
            if ($parentId === $id || !isset($structure[$parentId])) {
                throw new RuntimeException('ქვესფეროს მშობელი სფერო არასწორია.');
            }
            $childCounts[$parentId] = ($childCounts[$parentId] ?? 0) + 1;
        }
        foreach ($structure as $id => $item) {
            $parentId = $item['parent_id'];
            if ($parentId !== null && $structure[$parentId]['parent_id'] !== null) {
                throw new RuntimeException('დაშვებულია მხოლოდ ორი დონე: სფერო და ქვესფერო.');
            }
            if ($parentId !== null && ($childCounts[$id] ?? 0) > 0) {
                throw new RuntimeException('ქვესფეროს საკუთარი ქვესფერო ვერ ექნება.');
            }
        }

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'UPDATE catalog_categories SET parent_id = :parent_id, sort_order = :sort_order WHERE id = :id'
            );
            foreach ($structure as $id => $item) {
                $statement->execute([
                    'parent_id' => $item['parent_id'],
                    'sort_order' => $item['sort_order'],
                    'id' => $id,
                ]);
            }
            $this->db->commit();
            $this->filterOptionsCache = null;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function categoryParentId(?int $parentId, ?int $categoryId = null): ?int
    {
        if ($parentId === null || $parentId <= 0) {
            return null;
        }
        if ($categoryId !== null && $parentId === $categoryId) {
            throw new RuntimeException('სფერო საკუთარ თავში ვერ ჩაიშლება.');
        }

        $parent = $this->catalogRow('catalog_categories', $parentId);
        if ((int) ($parent['is_active'] ?? 1) !== 1) {
            throw new RuntimeException('მშობელი სფერო ვერ მოიძებნა.');
        }
        if (isset($parent['parent_id']) && (int) $parent['parent_id'] > 0) {
            throw new RuntimeException('დაშვებულია მხოლოდ ორი დონე: სფერო და ქვესფერო.');
        }

        if ($categoryId !== null) {
            $statement = $this->db->prepare(
                'SELECT COUNT(*) FROM catalog_categories WHERE parent_id = :id AND is_active = 1'
            );
            $statement->execute(['id' => $categoryId]);
            if ((int) $statement->fetchColumn() > 0) {
                throw new RuntimeException('ჯერ არსებული ქვესფეროები გადაიტანეთ და შემდეგ ჩაშალეთ ეს სფერო.');
            }
        }

        return $parentId;
    }

    private function ensureCategoryHierarchySchema(): void
    {
        if ($this->categoryHierarchyReady) {
            return;
        }

        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = $this->db->query('PRAGMA table_info(catalog_categories)')->fetchAll();
            $columnNames = array_fill_keys(array_map(
                static fn (array $column): string => (string) $column['name'],
                $columns
            ), true);
            if (!isset($columnNames['parent_id'])) {
                $this->db->exec('ALTER TABLE catalog_categories ADD COLUMN parent_id INTEGER NULL');
            }
            if (!isset($columnNames['is_active'])) {
                $this->db->exec('ALTER TABLE catalog_categories ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
            }
            $this->db->exec(
                'CREATE INDEX IF NOT EXISTS catalog_categories_tree_idx
                 ON catalog_categories (is_active, parent_id, sort_order, name)'
            );
            $this->categoryHierarchyReady = true;
            return;
        }

        $databaseName = (string) $this->db->query('SELECT DATABASE()')->fetchColumn();
        if ($databaseName === '') {
            throw new RuntimeException('მონაცემთა ბაზის სქემა ვერ განისაზღვრა.');
        }
        $columnExists = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $hasColumn = function (string $column) use ($columnExists, $databaseName): bool {
            $columnExists->execute([
                'schema_name' => $databaseName,
                'table_name' => 'catalog_categories',
                'column_name' => $column,
            ]);
            return (int) $columnExists->fetchColumn() > 0;
        };
        if (!$hasColumn('parent_id')) {
            $this->db->exec(
                'ALTER TABLE catalog_categories
                 ADD COLUMN parent_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER config_key'
            );
        }
        if (!$hasColumn('is_active')) {
            $this->db->exec(
                'ALTER TABLE catalog_categories
                 ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER parent_id'
            );
        }

        $indexExists = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
        );
        $hasIndex = function (string $index) use ($indexExists, $databaseName): bool {
            $indexExists->execute([
                'schema_name' => $databaseName,
                'table_name' => 'catalog_categories',
                'index_name' => $index,
            ]);
            return (int) $indexExists->fetchColumn() > 0;
        };
        if (!$hasIndex('catalog_categories_parent_idx')) {
            $this->db->exec(
                'ALTER TABLE catalog_categories ADD INDEX catalog_categories_parent_idx (parent_id)'
            );
        }
        if (!$hasIndex('catalog_categories_tree_idx')) {
            $this->db->exec(
                'ALTER TABLE catalog_categories
                 ADD INDEX catalog_categories_tree_idx (is_active, parent_id, sort_order, name)'
            );
        }

        $this->categoryHierarchyReady = true;
    }

    public function createCatalogRegion(string $name, int $sortOrder): int
    {
        $name = self::catalogName($name, 120);
        $this->assertCatalogNameAvailable('catalog_regions', $name);
        $statement = $this->db->prepare(
            'INSERT INTO catalog_regions (name, config_key, sort_order) VALUES (:name, NULL, :sort_order)'
        );
        $statement->execute(['name' => $name, 'sort_order' => self::catalogSortOrder($sortOrder)]);
        $this->filterOptionsCache = null;
        return (int) $this->db->lastInsertId();
    }

    public function updateCatalogRegion(int $id, string $name, int $sortOrder): void
    {
        $name = self::catalogName($name, 120);
        $current = $this->catalogRow('catalog_regions', $id);
        $this->assertCatalogNameAvailable('catalog_regions', $name, $id);

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'UPDATE catalog_regions SET name = :name, sort_order = :sort_order WHERE id = :id'
            );
            $statement->execute(['name' => $name, 'sort_order' => self::catalogSortOrder($sortOrder), 'id' => $id]);
            $this->replaceTextValue('teachers', 'region', (string) $current['name'], $name);
            $this->replaceTextValue('mentor_requests', 'region', (string) $current['name'], $name);
            $this->replaceTextValue('search_events', 'region', (string) $current['name'], $name);
            $this->replaceTextValue('match_requests', 'region', (string) $current['name'], $name);
            $this->db->commit();
            $this->filterOptionsCache = null;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function createCatalogSettlement(int $regionId, string $name, int $sortOrder): int
    {
        $name = self::catalogName($name, 140);
        $this->catalogRow('catalog_regions', $regionId);
        $this->assertSettlementAvailable($regionId, $name);
        $statement = $this->db->prepare(
            'INSERT INTO catalog_settlements (region_id, name, config_key, sort_order)
             VALUES (:region_id, :name, NULL, :sort_order)'
        );
        $statement->execute([
            'region_id' => $regionId,
            'name' => $name,
            'sort_order' => self::catalogSortOrder($sortOrder),
        ]);
        $this->filterOptionsCache = null;
        return (int) $this->db->lastInsertId();
    }

    public function updateCatalogSettlement(int $id, int $regionId, string $name, int $sortOrder): void
    {
        $name = self::catalogName($name, 140);
        $current = $this->db->prepare(
            'SELECT s.*, r.name AS region_name
             FROM catalog_settlements s JOIN catalog_regions r ON r.id = s.region_id
             WHERE s.id = :id LIMIT 1'
        );
        $current->execute(['id' => $id]);
        $row = $current->fetch();
        if (!$row) {
            throw new RuntimeException('ქალაქი ან უბანი ვერ მოიძებნა.');
        }
        $newRegion = $this->catalogRow('catalog_regions', $regionId);
        $this->assertSettlementAvailable($regionId, $name, $id);

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'UPDATE catalog_settlements
                 SET region_id = :region_id, name = :name, sort_order = :sort_order
                 WHERE id = :id'
            );
            $statement->execute([
                'region_id' => $regionId,
                'name' => $name,
                'sort_order' => self::catalogSortOrder($sortOrder),
                'id' => $id,
            ]);
            foreach (['teachers', 'mentor_requests'] as $table) {
                $update = $this->db->prepare(
                    "UPDATE {$table}
                     SET region = :new_region, settlement = :new_settlement
                     WHERE region = :old_region AND settlement = :old_settlement"
                );
                $update->execute([
                    'new_region' => (string) $newRegion['name'],
                    'new_settlement' => $name,
                    'old_region' => (string) $row['region_name'],
                    'old_settlement' => (string) $row['name'],
                ]);
            }
            $this->db->commit();
            $this->filterOptionsCache = null;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private static function catalogName(string $name, int $maximumLength): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        if ($name === '' || mb_strlen($name, 'UTF-8') > $maximumLength) {
            throw new RuntimeException('სახელი სავალდებულოა და დასაშვებ სიგრძეს არ უნდა აღემატებოდეს.');
        }
        return $name;
    }

    private static function catalogSortOrder(int $sortOrder): int
    {
        return max(0, min(1000000, $sortOrder));
    }

    /** @return array<string, mixed> */
    private function catalogRow(string $table, int $id): array
    {
        if (!in_array($table, ['catalog_categories', 'catalog_regions'], true)) {
            throw new RuntimeException('Invalid catalog table.');
        }
        $statement = $this->db->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!$row) {
            throw new RuntimeException('კატალოგის ჩანაწერი ვერ მოიძებნა.');
        }
        return $row;
    }

    private function assertCatalogNameAvailable(string $table, string $name, ?int $ignoreId = null): void
    {
        if (!in_array($table, ['catalog_categories', 'catalog_regions'], true)) {
            throw new RuntimeException('Invalid catalog table.');
        }
        $sql = "SELECT id FROM {$table} WHERE name = :name" . ($ignoreId !== null ? ' AND id <> :id' : '') . ' LIMIT 1';
        $statement = $this->db->prepare($sql);
        $parameters = ['name' => $name];
        if ($ignoreId !== null) {
            $parameters['id'] = $ignoreId;
        }
        $statement->execute($parameters);
        if ($statement->fetchColumn()) {
            throw new RuntimeException('ასეთი ჩანაწერი უკვე არსებობს.');
        }
    }

    private function assertSettlementAvailable(int $regionId, string $name, ?int $ignoreId = null): void
    {
        $sql = 'SELECT id FROM catalog_settlements WHERE region_id = :region_id AND name = :name'
            . ($ignoreId !== null ? ' AND id <> :id' : '') . ' LIMIT 1';
        $statement = $this->db->prepare($sql);
        $parameters = ['region_id' => $regionId, 'name' => $name];
        if ($ignoreId !== null) {
            $parameters['id'] = $ignoreId;
        }
        $statement->execute($parameters);
        if ($statement->fetchColumn()) {
            throw new RuntimeException('ეს ქალაქი ან უბანი არჩეულ რეგიონში უკვე არსებობს.');
        }
    }

    private function replaceTextValue(string $table, string $column, string $oldValue, string $newValue): void
    {
        $allowed = [
            'teachers.category', 'teachers.region',
            'mentor_requests.category', 'mentor_requests.region',
            'search_events.category', 'search_events.region',
            'match_requests.category', 'match_requests.region',
        ];
        if (!in_array($table . '.' . $column, $allowed, true) || $oldValue === $newValue) {
            return;
        }
        $statement = $this->db->prepare("UPDATE {$table} SET {$column} = :new_value WHERE {$column} = :old_value");
        $statement->execute(['new_value' => $newValue, 'old_value' => $oldValue]);
    }

    private function assertCatalogSelection(string $category, string $region, string $settlement): void
    {
        if ($category !== '') {
            $statement = $this->db->prepare('SELECT id FROM catalog_categories WHERE name = :name LIMIT 1');
            $statement->execute(['name' => $category]);
            if (!$statement->fetchColumn()) {
                throw new RuntimeException('არჩეული სფერო კატალოგში აღარ არსებობს.');
            }
        }
        if ($region === '') {
            if ($settlement !== '') {
                throw new RuntimeException('ქალაქის ან უბნის არჩევამდე მიუთითეთ რეგიონი.');
            }
            return;
        }

        $statement = $this->db->prepare('SELECT id FROM catalog_regions WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $region]);
        $regionId = (int) $statement->fetchColumn();
        if ($regionId <= 0) {
            throw new RuntimeException('არჩეული რეგიონი კატალოგში აღარ არსებობს.');
        }
        if ($settlement !== '') {
            $statement = $this->db->prepare(
                'SELECT id FROM catalog_settlements WHERE region_id = :region_id AND name = :name LIMIT 1'
            );
            $statement->execute(['region_id' => $regionId, 'name' => $settlement]);
            if (!$statement->fetchColumn()) {
                throw new RuntimeException('არჩეული ქალაქი ან უბანი ამ რეგიონს არ ეკუთვნის.');
            }
        }
    }

    /** @param array<string, string> $filters
     *  @return list<array<string, mixed>>
     */
    public function searchTeachers(array $filters, int $limit = 24, int $offset = 0): array
    {
        [$where, $parameters] = $this->teacherWhere($filters);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $sql = "SELECT t.*, m.public_url AS photo_url
                FROM teachers t
                LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'profile'
                {$where}
                ORDER BY t.published_at DESC, t.id DESC
                LIMIT {$limit} OFFSET {$offset}";
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @param array<string, string> $filters */
    public function countTeachers(array $filters): int
    {
        [$where, $parameters] = $this->teacherWhere($filters);
        $statement = $this->db->prepare("SELECT COUNT(*) FROM teachers t {$where}");
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** @param array<string, string> $filters
     *  @return array{0:string,1:array<string,string>}
     */
    private function teacherWhere(array $filters): array
    {
        $conditions = ["t.status = 'published'"];
        $parameters = [];

        $keyword = trim($filters['q'] ?? '');
        if ($keyword !== '') {
            $searchFields = [
                't.name_ka', 't.name_en', 't.name_ru',
                't.profession_ka', 't.profession_en', 't.profession_ru',
                't.bio_ka', 't.bio_en', 't.bio_ru', 't.settlement',
            ];
            $keywordConditions = [];
            foreach ($searchFields as $index => $field) {
                $parameter = 'keyword_' . $index;
                $keywordConditions[] = "{$field} LIKE :{$parameter}";
                $parameters[$parameter] = '%' . $keyword . '%';
            }
            $conditions[] = '(' . implode(' OR ', $keywordConditions) . ')';
        }

        foreach (['category', 'region', 'settlement'] as $field) {
            $value = trim($filters[$field] ?? '');
            if ($value !== '') {
                $conditions[] = "t.{$field} = :{$field}";
                $parameters[$field] = $value;
            }
        }

        $format = trim($filters['format'] ?? '');
        if ($format === 'online') {
            $conditions[] = 't.format_online = 1';
        } elseif ($format === 'in_person') {
            $conditions[] = 't.format_in_person = 1';
        }

        $language = trim($filters['language'] ?? '');
        if ($language !== '') {
            $conditions[] = "FIND_IN_SET(:language, REPLACE(t.languages, ', ', ',')) > 0";
            $parameters['language'] = $language;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @param array<string, string> $filters
     *  @return list<array<string, mixed>>
     */
    public function searchMentorRequests(array $filters, int $limit = 24, int $offset = 0): array
    {
        [$where, $parameters] = $this->mentorRequestWhere($filters);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $statement = $this->db->prepare(
            "SELECT * FROM mentor_requests {$where}
             ORDER BY published_at DESC, id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @param array<string, string> $filters */
    public function countMentorRequests(array $filters): int
    {
        [$where, $parameters] = $this->mentorRequestWhere($filters);
        $statement = $this->db->prepare("SELECT COUNT(*) FROM mentor_requests {$where}");
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** @param array<string, string> $filters
     *  @return array{0:string,1:array<string,string>}
     */
    private function mentorRequestWhere(array $filters): array
    {
        $conditions = ["status = 'published'"];
        $parameters = [];

        $keyword = trim($filters['q'] ?? '');
        if ($keyword !== '') {
            $searchFields = ['name', 'subject', 'current_level', 'learning_goal', 'details', 'availability', 'settlement'];
            $keywordConditions = [];
            foreach ($searchFields as $index => $field) {
                $parameter = 'mentor_keyword_' . $index;
                $keywordConditions[] = "{$field} LIKE :{$parameter}";
                $parameters[$parameter] = '%' . $keyword . '%';
            }
            $conditions[] = '(' . implode(' OR ', $keywordConditions) . ')';
        }

        foreach (['category', 'region', 'settlement'] as $field) {
            $value = trim($filters[$field] ?? '');
            if ($value !== '') {
                $conditions[] = "{$field} = :{$field}";
                $parameters[$field] = $value;
            }
        }

        $format = trim($filters['format'] ?? '');
        if ($format === 'online') {
            $conditions[] = 'format_online = 1';
        } elseif ($format === 'in_person') {
            $conditions[] = 'format_in_person = 1';
        }

        return ['WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @return array<string, mixed>|null */
    public function findMentorRequestBySlug(string $slug, bool $includeUnpublished = false): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM mentor_requests WHERE slug = :slug'
            . ($includeUnpublished ? '' : " AND status = 'published'") . ' LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findMentorRequestById(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM mentor_requests WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findTeacherBySlug(string $slug, bool $includeUnpublished = false): ?array
    {
        $sql = "SELECT t.*, m.public_url AS photo_url
                FROM teachers t
                LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'large'
                WHERE t.slug = :slug" . ($includeUnpublished ? '' : " AND t.status = 'published'") . ' LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findTeacherById(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM teachers WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function similarTeachers(array $teacher, int $limit = 4): array
    {
        $limit = max(1, min(8, $limit));
        $statement = $this->db->prepare(
            "SELECT t.*, m.public_url AS photo_url
             FROM teachers t
             LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'thumbnail'
             WHERE t.status = 'published'
               AND t.id <> :id
               AND t.category = :category
               AND t.region = :region
             ORDER BY (t.settlement = :settlement) DESC, t.published_at DESC, t.id DESC
             LIMIT {$limit}"
        );
        $statement->execute([
            'id' => (int) $teacher['id'],
            'category' => (string) $teacher['category'],
            'region' => (string) $teacher['region'],
            'settlement' => (string) $teacher['settlement'],
        ]);
        return $statement->fetchAll();
    }

    /** @return array{location:string,category:string,online:bool,teachers:list<array<string,mixed>>}|null */
    public function seoLanding(string $locationSlug, string $categorySlug): ?array
    {
        $categories = $this->db->query(
            "SELECT DISTINCT category FROM teachers WHERE status = 'published' AND category <> ''"
        )->fetchAll(PDO::FETCH_COLUMN);
        $category = null;
        foreach ($categories as $candidate) {
            if (seo_slug((string) $candidate) === $categorySlug) {
                $category = (string) $candidate;
                break;
            }
        }
        if ($category === null) {
            return null;
        }

        $online = $locationSlug === 'online';
        $location = t('search.online');
        $parameters = ['category' => $category];
        $locationCondition = 't.format_online = 1';
        if (!$online) {
            $values = $this->db->query(
                "SELECT region AS value FROM teachers WHERE status = 'published' AND region <> ''
                 UNION SELECT settlement AS value FROM teachers WHERE status = 'published' AND settlement <> ''"
            )->fetchAll(PDO::FETCH_COLUMN);
            $location = '';
            foreach ($values as $candidate) {
                if (seo_slug((string) $candidate) === $locationSlug) {
                    $location = (string) $candidate;
                    break;
                }
            }
            if ($location === '') {
                return null;
            }
            $locationCondition = '(t.region = :location_region OR t.settlement = :location_settlement)';
            $parameters['location_region'] = $location;
            $parameters['location_settlement'] = $location;
        }

        $statement = $this->db->prepare(
            "SELECT t.*, m.public_url AS photo_url
             FROM teachers t
             LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'profile'
             WHERE t.status = 'published' AND t.category = :category AND {$locationCondition}
             ORDER BY t.published_at DESC, t.id DESC"
        );
        $statement->execute($parameters);
        $teachers = $statement->fetchAll();
        if (!$teachers) {
            return null;
        }

        return ['location' => $location, 'category' => $category, 'online' => $online, 'teachers' => $teachers];
    }

    /** @return list<array<string, mixed>> */
    public function approvedComments(int $teacherId): array
    {
        $statement = $this->db->prepare(
            "SELECT author_name, rating, body, published_at
             FROM comments WHERE teacher_id = :teacher_id AND status = 'approved'
             ORDER BY published_at DESC, id DESC"
        );
        $statement->execute(['teacher_id' => $teacherId]);
        return $statement->fetchAll();
    }

    public function upsertComment(int $teacherId, string $visitorHash, string $name, int $rating, string $body): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO comments (teacher_id, visitor_hash, author_name, rating, body, status)
             VALUES (:teacher_id, :visitor_hash, :author_name, :rating, :body, 'pending')
             ON DUPLICATE KEY UPDATE
                author_name = VALUES(author_name), rating = VALUES(rating), body = VALUES(body),
                status = 'pending', reviewed_at = NULL, published_at = NULL, updated_at = CURRENT_TIMESTAMP"
        );
        $statement->execute([
            'teacher_id' => $teacherId,
            'visitor_hash' => $visitorHash,
            'author_name' => $name,
            'rating' => $rating,
            'body' => $body,
        ]);
    }

    /** @param array<string, string> $filters */
    public function logSearch(string $visitorHash, array $filters, int $resultCount, string $source = 'search'): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO search_events
                (visitor_hash, source, keyword, category, region, teaching_format, language, result_count, filters_json)
             VALUES
                (:visitor_hash, :source, :keyword, :category, :region, :teaching_format, :language, :result_count, :filters_json)'
        );
        $statement->execute([
            'visitor_hash' => $visitorHash,
            'source' => $source,
            'keyword' => trim($filters['q'] ?? ''),
            'category' => trim($filters['category'] ?? ''),
            'region' => trim($filters['region'] ?? ''),
            'teaching_format' => trim($filters['format'] ?? ''),
            'language' => trim($filters['language'] ?? ''),
            'result_count' => $resultCount,
            'filters_json' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    /** @param array<string, string> $filters */
    public function logMatchRequest(string $visitorHash, array $filters, int $matchedCount): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO match_requests
                (visitor_hash, category, region, teaching_format, language, matched_count, request_json)
             VALUES
                (:visitor_hash, :category, :region, :teaching_format, :language, :matched_count, :request_json)'
        );
        $statement->execute([
            'visitor_hash' => $visitorHash,
            'category' => trim($filters['category'] ?? ''),
            'region' => trim($filters['region'] ?? ''),
            'teaching_format' => trim($filters['format'] ?? ''),
            'language' => trim($filters['language'] ?? ''),
            'matched_count' => $matchedCount,
            'request_json' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    public function trackPageView(string $visitorHash, string $path): void
    {
        $path = mb_substr($path, 0, 190, 'UTF-8');
        $this->db->beginTransaction();
        try {
            $visitor = $this->db->prepare(
                'INSERT IGNORE INTO site_daily_visitors (visit_date, visitor_hash) VALUES (CURRENT_DATE, :visitor_hash)'
            );
            $visitor->execute(['visitor_hash' => $visitorHash]);

            $view = $this->db->prepare(
                'INSERT INTO page_views_daily (view_date, path, views) VALUES (CURRENT_DATE, :path, 1)
                 ON DUPLICATE KEY UPDATE views = views + 1'
            );
            $view->execute(['path' => $path]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function trackTeacherView(int $teacherId, string $visitorHash): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO teacher_daily_visitors (view_date, teacher_id, visitor_hash, view_count)
             VALUES (CURRENT_DATE, :teacher_id, :visitor_hash, 1)
             ON DUPLICATE KEY UPDATE view_count = view_count + 1'
        );
        $statement->execute(['teacher_id' => $teacherId, 'visitor_hash' => $visitorHash]);
    }

    /** @return array<string, int> */
    public function adminStats(): array
    {
        $row = $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM teachers) AS teachers,
                (SELECT COUNT(*) FROM teachers WHERE status = 'published') AS published,
                (SELECT COUNT(*) FROM teachers WHERE status = 'draft') AS pending_teachers,
                (SELECT COUNT(*) FROM mentor_requests) AS mentor_requests,
                (SELECT COUNT(*) FROM mentor_requests WHERE status = 'published') AS published_mentor_requests,
                (SELECT COUNT(*) FROM mentor_requests WHERE status = 'draft') AS pending_mentor_requests,
                (SELECT COUNT(*) FROM comments WHERE status = 'pending') AS pending_comments,
                (SELECT COUNT(*) FROM site_daily_visitors WHERE visit_date = CURRENT_DATE) AS today_visitors,
                (SELECT COALESCE(SUM(views), 0) FROM page_views_daily) AS page_views,
                (SELECT COUNT(*) FROM search_events WHERE result_count = 0) AS failed_searches"
        )->fetch() ?: [];

        return array_map('intval', $row);
    }

    public function initializeHumanAnalytics(): void
    {
        $resetKey = 'human-analytics-v1-20260831';
        $localMarker = dirname(__DIR__) . '/storage/' . $resetKey . '.done';
        if (is_file($localMarker)) return;

        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS analytics_resets (
                    reset_key TEXT PRIMARY KEY,
                    reset_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
            );
            $marker = $this->db->prepare('INSERT OR IGNORE INTO analytics_resets (reset_key) VALUES (:reset_key)');
        } else {
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS analytics_resets (
                    reset_key VARCHAR(100) NOT NULL PRIMARY KEY,
                    reset_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $marker = $this->db->prepare('INSERT IGNORE INTO analytics_resets (reset_key) VALUES (:reset_key)');
        }

        $this->db->beginTransaction();
        try {
            $marker->execute(['reset_key' => $resetKey]);
            if ($marker->rowCount() === 1) {
                foreach (['teacher_daily_visitors', 'page_views_daily', 'site_daily_visitors', 'search_events', 'match_requests'] as $table) {
                    $this->db->exec("DELETE FROM {$table}");
                }
            }
            $this->db->commit();
            $markerDirectory = dirname($localMarker);
            if (is_dir($markerDirectory) || @mkdir($markerDirectory, 0775, true)) {
                @file_put_contents($localMarker, date(DATE_ATOM), LOCK_EX);
            }
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function topTeachers(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        return $this->db->query(
            "SELECT t.id, t.slug, t.name_ka, t.name_en, t.name_ru,
                    COALESCE(SUM(v.view_count), 0) AS total_views,
                    COUNT(DISTINCT CONCAT(v.view_date, ':', v.visitor_hash)) AS unique_views
             FROM teachers t
             LEFT JOIN teacher_daily_visitors v ON v.teacher_id = t.id
             GROUP BY t.id
             ORDER BY total_views DESC, t.id DESC
             LIMIT {$limit}"
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function unsuccessfulSearches(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->query(
            "SELECT category, region, teaching_format, language, COUNT(*) AS attempts,
                    MAX(created_at) AS last_attempt
             FROM search_events
             WHERE result_count = 0
             GROUP BY category, region, teaching_format, language
             ORDER BY attempts DESC, last_attempt DESC
             LIMIT {$limit}"
        )->fetchAll();
    }

    /** @param array{q?:string,status?:string,sort?:string} $filters
     *  @return list<array<string, mixed>>
     */
    public function adminTeachers(array $filters = []): array
    {
        $conditions = [];
        $parameters = [];
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $conditions[] = "(t.name_ka LIKE :query OR t.name_en LIKE :query OR t.name_ru LIKE :query
                OR t.profession_ka LIKE :query OR t.category LIKE :query OR t.region LIKE :query
                OR t.settlement LIKE :query OR t.phone LIKE :query)";
            $parameters['query'] = '%' . $query . '%';
        }
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $conditions[] = 't.status = :status';
            $parameters['status'] = $status;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $order = match ((string) ($filters['sort'] ?? 'newest')) {
            'oldest' => 't.created_at ASC, t.id ASC',
            'name' => 'COALESCE(NULLIF(t.name_ka, \'\'), NULLIF(t.name_en, \'\'), t.name_ru) ASC',
            'category' => 't.category ASC, t.updated_at DESC',
            default => 't.updated_at DESC, t.id DESC',
        };

        $statement = $this->db->prepare(
            "SELECT t.*, m.public_url AS photo_url
             FROM teachers t
             LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'thumbnail'
             {$where}
             ORDER BY {$order}"
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @param array{q?:string,status?:string,sort?:string} $filters
     *  @return list<array<string, mixed>>
     */
    public function adminMentorRequests(array $filters = []): array
    {
        $conditions = [];
        $parameters = [];
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $searchFields = ['name', 'subject', 'category', 'region', 'settlement', 'phone', 'email'];
            $queryConditions = [];
            foreach ($searchFields as $index => $field) {
                $parameter = 'admin_mentor_query_' . $index;
                $queryConditions[] = "{$field} LIKE :{$parameter}";
                $parameters[$parameter] = '%' . $query . '%';
            }
            $conditions[] = '(' . implode(' OR ', $queryConditions) . ')';
        }
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $conditions[] = 'status = :status';
            $parameters['status'] = $status;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $order = match ((string) ($filters['sort'] ?? 'newest')) {
            'oldest' => 'created_at ASC, id ASC',
            'name' => 'name ASC, id DESC',
            'category' => 'category ASC, updated_at DESC',
            default => 'updated_at DESC, id DESC',
        };

        $statement = $this->db->prepare("SELECT * FROM mentor_requests {$where} ORDER BY {$order}");
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function adminComments(string $status = 'pending'): array
    {
        $allowed = ['pending', 'approved', 'rejected'];
        $status = in_array($status, $allowed, true) ? $status : 'pending';
        $statement = $this->db->prepare(
            'SELECT c.*, t.slug, t.name_ka, t.name_en, t.name_ru
             FROM comments c JOIN teachers t ON t.id = c.teacher_id
             WHERE c.status = :status ORDER BY c.updated_at DESC'
        );
        $statement->execute(['status' => $status]);
        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function saveTeacher(array $data, ?int $id = null): int
    {
        $this->ensureTeacherCardCropSchema();
        $name = trim((string) ($data['name_ka'] ?? $data['name_en'] ?? $data['name_ru'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Teacher name is required.');
        }

        $slug = slugify((string) ($data['slug'] ?? '')) ?: slugify($name);
        $slug = $this->uniqueSlug($slug ?: 'teacher', $id);
        $status = in_array(($data['status'] ?? ''), ['draft', 'published', 'archived'], true)
            ? (string) $data['status'] : 'draft';

        $priceUnit = in_array((string) ($data['price_unit'] ?? ''), ['hour', 'month', 'course', 'lesson', 'negotiable'], true)
            ? (string) $data['price_unit'] : 'hour';
        $fields = [
            'slug' => $slug,
            'name_ka' => trim((string) ($data['name_ka'] ?? '')),
            'name_en' => trim((string) ($data['name_en'] ?? '')),
            'name_ru' => trim((string) ($data['name_ru'] ?? '')),
            'profession_ka' => trim((string) ($data['profession_ka'] ?? '')),
            'profession_en' => trim((string) ($data['profession_en'] ?? '')),
            'profession_ru' => trim((string) ($data['profession_ru'] ?? '')),
            'bio_ka' => trim((string) ($data['bio_ka'] ?? '')),
            'bio_en' => trim((string) ($data['bio_en'] ?? '')),
            'bio_ru' => trim((string) ($data['bio_ru'] ?? '')),
            'category' => trim((string) ($data['category'] ?? '')),
            'region' => trim((string) ($data['region'] ?? '')),
            'settlement' => trim((string) ($data['settlement'] ?? '')),
            'languages' => implode(', ', array_unique(array_filter(array_map('trim', explode(',', (string) ($data['languages'] ?? '')))))),
            'format_online' => !empty($data['format_online']) ? 1 : 0,
            'format_in_person' => !empty($data['format_in_person']) ? 1 : 0,
            'price_from' => $priceUnit === 'negotiable' || ($data['price_from'] ?? '') === ''
                ? null : max(0, (float) $data['price_from']),
            'price_unit' => $priceUnit,
            'phone' => trim((string) ($data['phone'] ?? '')),
            'facebook_url' => trim((string) ($data['facebook_url'] ?? '')),
            'instagram_url' => trim((string) ($data['instagram_url'] ?? '')),
            'status' => $status,
        ];
        if (array_key_exists('card_photo_x', $data)) {
            $fields['card_photo_x'] = max(0, min(100, (float) $data['card_photo_x']));
            $fields['card_photo_y'] = max(0, min(100, (float) ($data['card_photo_y'] ?? 50)));
            $fields['card_photo_zoom'] = max(1, min(2.5, (float) ($data['card_photo_zoom'] ?? 1)));
        }
        $this->assertCatalogSelection($fields['category'], $fields['region'], $fields['settlement']);

        if ($id === null) {
            $columns = implode(', ', array_keys($fields));
            $placeholders = ':' . implode(', :', array_keys($fields));
            $statement = $this->db->prepare(
                "INSERT INTO teachers ({$columns}, published_at)
                 VALUES ({$placeholders}, IF(:publish_now = 1, CURRENT_TIMESTAMP, NULL))"
            );
            $statement->execute($fields + ['publish_now' => $status === 'published' ? 1 : 0]);
            return (int) $this->db->lastInsertId();
        }

        $assignments = implode(', ', array_map(static fn (string $field): string => "{$field} = :{$field}", array_keys($fields)));
        $statement = $this->db->prepare(
            "UPDATE teachers SET {$assignments},
                published_at = IF(:publish_now = 1, COALESCE(published_at, CURRENT_TIMESTAMP), published_at)
             WHERE id = :id"
        );
        $statement->execute($fields + ['publish_now' => $status === 'published' ? 1 : 0, 'id' => $id]);
        return $id;
    }

    private function ensureTeacherCardCropSchema(): void
    {
        if ($this->teacherCardCropReady) return;

        $definitions = [
            'card_photo_x' => 'DECIMAL(5,2) NULL DEFAULT NULL',
            'card_photo_y' => 'DECIMAL(5,2) NULL DEFAULT NULL',
            'card_photo_zoom' => 'DECIMAL(4,2) NULL DEFAULT NULL',
        ];
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = $this->db->query('PRAGMA table_info(teachers)')->fetchAll();
            $names = array_fill_keys(array_map(static fn (array $column): string => (string) $column['name'], $columns), true);
            foreach ($definitions as $column => $definition) {
                if (!isset($names[$column])) $this->db->exec("ALTER TABLE teachers ADD COLUMN {$column} REAL NULL");
            }
            $this->teacherCardCropReady = true;
            return;
        }

        $databaseName = (string) $this->db->query('SELECT DATABASE()')->fetchColumn();
        $exists = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        foreach ($definitions as $column => $definition) {
            $exists->execute(['schema_name' => $databaseName, 'table_name' => 'teachers', 'column_name' => $column]);
            if ((int) $exists->fetchColumn() === 0) {
                $this->db->exec("ALTER TABLE teachers ADD COLUMN {$column} {$definition}");
            }
        }
        $this->teacherCardCropReady = true;
    }

    /** @param array<string, mixed> $data */
    public function saveMentorRequest(array $data, ?int $id = null): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($name === '' || $subject === '') {
            throw new RuntimeException('Mentor request name and subject are required.');
        }

        $slugBase = slugify((string) ($data['slug'] ?? '')) ?: slugify($subject . '-' . $name);
        $slug = $this->uniqueMentorRequestSlug($slugBase ?: 'mentor-request', $id);
        $status = in_array(($data['status'] ?? ''), ['draft', 'published', 'archived'], true)
            ? (string) $data['status'] : 'draft';
        $budgetUnit = in_array((string) ($data['budget_unit'] ?? ''), ['hour', 'month', 'course', 'lesson', 'negotiable'], true)
            ? (string) $data['budget_unit'] : 'negotiable';
        $fields = [
            'slug' => $slug,
            'name' => $name,
            'learner_group' => trim((string) ($data['learner_group'] ?? '')),
            'category' => trim((string) ($data['category'] ?? '')),
            'subject' => $subject,
            'current_level' => trim((string) ($data['current_level'] ?? '')),
            'learning_goal' => trim((string) ($data['learning_goal'] ?? '')),
            'region' => trim((string) ($data['region'] ?? '')),
            'settlement' => trim((string) ($data['settlement'] ?? '')),
            'format_online' => !empty($data['format_online']) ? 1 : 0,
            'format_in_person' => !empty($data['format_in_person']) ? 1 : 0,
            'availability' => trim((string) ($data['availability'] ?? '')),
            'desired_start' => trim((string) ($data['desired_start'] ?? '')),
            'budget_from' => $budgetUnit === 'negotiable' || ($data['budget_from'] ?? '') === ''
                ? null : max(0, (float) $data['budget_from']),
            'budget_unit' => $budgetUnit,
            'phone' => trim((string) ($data['phone'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'details' => trim((string) ($data['details'] ?? '')),
            'status' => $status,
        ];
        $this->assertCatalogSelection($fields['category'], $fields['region'], $fields['settlement']);

        if ($id === null) {
            $columns = implode(', ', array_keys($fields));
            $placeholders = ':' . implode(', :', array_keys($fields));
            $statement = $this->db->prepare(
                "INSERT INTO mentor_requests ({$columns}, published_at)
                 VALUES ({$placeholders}, IF(:publish_now = 1, CURRENT_TIMESTAMP, NULL))"
            );
            $statement->execute($fields + ['publish_now' => $status === 'published' ? 1 : 0]);
            return (int) $this->db->lastInsertId();
        }

        $assignments = implode(', ', array_map(static fn (string $field): string => "{$field} = :{$field}", array_keys($fields)));
        $statement = $this->db->prepare(
            "UPDATE mentor_requests SET {$assignments},
                published_at = IF(:publish_now = 1, COALESCE(published_at, CURRENT_TIMESTAMP), published_at)
             WHERE id = :id"
        );
        $statement->execute($fields + ['publish_now' => $status === 'published' ? 1 : 0, 'id' => $id]);
        return $id;
    }

    public function setTeacherStatus(int $teacherId, string $status): void
    {
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new RuntimeException('Invalid teacher status.');
        }
        $statement = $this->db->prepare(
            "UPDATE teachers SET status = :status,
                published_at = IF(:publish_now = 1, COALESCE(published_at, CURRENT_TIMESTAMP), published_at)
             WHERE id = :id"
        );
        $statement->execute([
            'status' => $status,
            'publish_now' => $status === 'published' ? 1 : 0,
            'id' => $teacherId,
        ]);
    }

    public function setMentorRequestStatus(int $requestId, string $status): void
    {
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new RuntimeException('Invalid mentor request status.');
        }
        $statement = $this->db->prepare(
            "UPDATE mentor_requests SET status = :status,
                published_at = IF(:publish_now = 1, COALESCE(published_at, CURRENT_TIMESTAMP), published_at)
             WHERE id = :id"
        );
        $statement->execute([
            'status' => $status,
            'publish_now' => $status === 'published' ? 1 : 0,
            'id' => $requestId,
        ]);
    }

    public function deleteTeacher(int $teacherId): void
    {
        $statement = $this->db->prepare('DELETE FROM teachers WHERE id = :id');
        $statement->execute(['id' => $teacherId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('პროფილი ვერ მოიძებნა.');
        }
    }

    private function uniqueSlug(string $base, ?int $ignoreId): string
    {
        $candidate = mb_substr($base, 0, 170, 'UTF-8');
        $suffix = 1;
        while (true) {
            $statement = $this->db->prepare(
                'SELECT id FROM teachers WHERE slug = :slug' . ($ignoreId !== null ? ' AND id <> :id' : '') . ' LIMIT 1'
            );
            $parameters = ['slug' => $candidate];
            if ($ignoreId !== null) {
                $parameters['id'] = $ignoreId;
            }
            $statement->execute($parameters);
            if (!$statement->fetchColumn()) {
                return $candidate;
            }
            $suffix++;
            $candidate = mb_substr($base, 0, 160, 'UTF-8') . '-' . $suffix;
        }
    }

    private function uniqueMentorRequestSlug(string $base, ?int $ignoreId): string
    {
        $candidate = mb_substr($base, 0, 170, 'UTF-8');
        $suffix = 1;
        while (true) {
            $statement = $this->db->prepare(
                'SELECT id FROM mentor_requests WHERE slug = :slug' . ($ignoreId !== null ? ' AND id <> :id' : '') . ' LIMIT 1'
            );
            $parameters = ['slug' => $candidate];
            if ($ignoreId !== null) {
                $parameters['id'] = $ignoreId;
            }
            $statement->execute($parameters);
            if (!$statement->fetchColumn()) {
                return $candidate;
            }
            $suffix++;
            $candidate = mb_substr($base, 0, 160, 'UTF-8') . '-' . $suffix;
        }
    }

    /** @param list<array{variant:string,driver:string,key:string,url:string,width:int,height:int,bytes:int,mime:string}> $variants */
    public function replaceTeacherMedia(int $teacherId, array $variants): void
    {
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare('DELETE FROM teacher_media WHERE teacher_id = :teacher_id');
            $delete->execute(['teacher_id' => $teacherId]);
            $insert = $this->db->prepare(
                'INSERT INTO teacher_media
                    (teacher_id, variant, storage_driver, storage_key, public_url, width, height, bytes, mime_type)
                 VALUES
                    (:teacher_id, :variant, :driver, :storage_key, :public_url, :width, :height, :bytes, :mime_type)'
            );
            foreach ($variants as $variant) {
                $insert->execute([
                    'teacher_id' => $teacherId,
                    'variant' => $variant['variant'],
                    'driver' => $variant['driver'],
                    'storage_key' => $variant['key'],
                    'public_url' => $variant['url'],
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                    'bytes' => $variant['bytes'],
                    'mime_type' => $variant['mime'],
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function teacherMedia(int $teacherId): array
    {
        $statement = $this->db->prepare('SELECT * FROM teacher_media WHERE teacher_id = :teacher_id');
        $statement->execute(['teacher_id' => $teacherId]);
        return $statement->fetchAll();
    }

    /** @return list<array{slug:string,updated_at:string}> */
    public function sitemapTeachers(): array
    {
        return $this->db->query(
            "SELECT slug, DATE(updated_at) AS updated_at
             FROM teachers WHERE status = 'published'
             ORDER BY id"
        )->fetchAll();
    }

    /** @return list<array{slug:string,updated_at:string}> */
    public function sitemapMentorRequests(): array
    {
        return $this->db->query(
            "SELECT slug, DATE(updated_at) AS updated_at
             FROM mentor_requests WHERE status = 'published'
             ORDER BY id"
        )->fetchAll();
    }

    /** @return list<array{location:string,category:string,updated_at:string}> */
    public function sitemapLandings(): array
    {
        $rows = $this->db->query(
            "SELECT COALESCE(NULLIF(settlement, ''), region) AS location, category, DATE(MAX(updated_at)) AS updated_at
             FROM teachers
             WHERE status = 'published' AND category <> '' AND (settlement <> '' OR region <> '')
             GROUP BY COALESCE(NULLIF(settlement, ''), region), category
             UNION ALL
             SELECT 'online' AS location, category, DATE(MAX(updated_at)) AS updated_at
             FROM teachers
             WHERE status = 'published' AND format_online = 1 AND category <> ''
             GROUP BY category"
        )->fetchAll();

        $unique = [];
        foreach ($rows as $row) {
            $key = seo_slug((string) $row['location']) . '/' . seo_slug((string) $row['category']);
            $unique[$key] = [
                'location' => (string) $row['location'],
                'category' => (string) $row['category'],
                'updated_at' => (string) $row['updated_at'],
            ];
        }
        return array_values($unique);
    }

    public function moderateComment(int $commentId, string $status): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid moderation status.');
        }
        $statement = $this->db->prepare(
            'UPDATE comments SET status = :status, reviewed_at = CURRENT_TIMESTAMP,
                published_at = IF(:published_status = \'approved\', CURRENT_TIMESTAMP, NULL)
             WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'published_status' => $status, 'id' => $commentId]);
    }
}

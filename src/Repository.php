<?php

declare(strict_types=1);

namespace Moemzade;

use PDO;
use RuntimeException;

final class Repository
{
    public function __construct(private readonly PDO $db)
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

        return [
            'teachers' => (int) ($row['teachers'] ?? 0),
            'categories' => (int) ($row['categories'] ?? 0),
            'regions' => (int) ($row['regions'] ?? 0),
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

    /** @return array{categories:list<string>,regions:list<string>,languages:list<string>} */
    public function filterOptions(): array
    {
        $categories = $this->db->query(
            "SELECT DISTINCT category FROM teachers WHERE status = 'published' AND category <> '' ORDER BY category"
        )->fetchAll(PDO::FETCH_COLUMN);
        $regions = $this->db->query(
            "SELECT DISTINCT region FROM teachers WHERE status = 'published' AND region <> '' ORDER BY region"
        )->fetchAll(PDO::FETCH_COLUMN);

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

        return [
            'categories' => array_values(array_map('strval', $categories)),
            'regions' => array_values(array_map('strval', $regions)),
            'languages' => $languageList,
        ];
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

        foreach (['category', 'region'] as $field) {
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
                (SELECT COUNT(*) FROM comments WHERE status = 'pending') AS pending_comments,
                (SELECT COUNT(*) FROM site_daily_visitors WHERE visit_date = CURRENT_DATE) AS today_visitors,
                (SELECT COALESCE(SUM(views), 0) FROM page_views_daily) AS page_views,
                (SELECT COUNT(*) FROM search_events WHERE result_count = 0) AS failed_searches"
        )->fetch() ?: [];

        return array_map('intval', $row);
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

    /** @return list<array<string, mixed>> */
    public function adminTeachers(): array
    {
        return $this->db->query(
            "SELECT t.*, m.public_url AS photo_url
             FROM teachers t
             LEFT JOIN teacher_media m ON m.teacher_id = t.id AND m.variant = 'thumbnail'
             ORDER BY t.updated_at DESC, t.id DESC"
        )->fetchAll();
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
        $name = trim((string) ($data['name_ka'] ?? $data['name_en'] ?? $data['name_ru'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Teacher name is required.');
        }

        $slug = slugify((string) ($data['slug'] ?? '')) ?: slugify($name);
        $slug = $this->uniqueSlug($slug ?: 'teacher', $id);
        $status = in_array(($data['status'] ?? ''), ['draft', 'published', 'archived'], true)
            ? (string) $data['status'] : 'draft';

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
            'price_from' => ($data['price_from'] ?? '') === '' ? null : max(0, (float) $data['price_from']),
            'price_unit' => trim((string) ($data['price_unit'] ?? 'hour')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'facebook_url' => trim((string) ($data['facebook_url'] ?? '')),
            'instagram_url' => trim((string) ($data['instagram_url'] ?? '')),
            'status' => $status,
        ];

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

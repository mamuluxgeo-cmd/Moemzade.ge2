<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$schema = file_get_contents(BASE_PATH . '/database/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Cannot read database/schema.sql');
}

$database->exec($schema);
$repository->seedTaxonomyCatalog();
fwrite(STDOUT, "Database schema is ready.\n");

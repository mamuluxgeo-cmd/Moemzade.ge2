<?php

declare(strict_types=1);

$password = $argv[1] ?? '';
if (strlen($password) < 12) {
    fwrite(STDERR, "Provide a password with at least 12 characters.\n");
    exit(1);
}

fwrite(STDOUT, password_hash($password, PASSWORD_DEFAULT) . PHP_EOL);


<?php

$database = getenv('DB_DATABASE') ?: '';

if ($database !== 'pentagon_test') {
    fwrite(STDERR, "Refusing PostgreSQL tests: DB_DATABASE must be the disposable database 'pentagon_test'.\n");
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';

<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    throw new RuntimeException('Composer dependencies not installed. Run `composer install`.');
}

require_once $autoloadPath;

$projectRoot = dirname(__DIR__);

if (class_exists(Dotenv::class)) {
    $dotenv = Dotenv::createImmutable($projectRoot);
    $dotenv->safeLoad();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

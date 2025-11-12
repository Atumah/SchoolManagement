<?php

declare(strict_types=1);

namespace App\Config;

use App\Support\Env;

final class DatabaseConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $database,
        public readonly string $username,
        public readonly string $password,
        public readonly string $charset,
        public readonly string $collation,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            host: Env::string('DB_HOST', 'mariadb'),
            port: Env::int('DB_PORT', 3306),
            database: Env::string('DB_DATABASE', 'app'),
            username: Env::string('DB_USERNAME', 'app'),
            password: Env::string('DB_PASSWORD', 'secret'),
            charset: Env::string('DB_CHARSET', 'utf8mb4'),
            collation: Env::string('DB_COLLATION', 'utf8mb4_unicode_ci'),
        );
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\DatabaseConfig;
use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(?DatabaseConfig $config = null): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config ??= DatabaseConfig::fromEnvironment();

        try {
            $pdo = new PDO(
                $config->dsn(),
                $config->username,
                $config->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to the database: ' . $exception->getMessage(), 0, $exception);
        }

        self::$connection = $pdo;

        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}

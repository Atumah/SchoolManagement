<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Migrator
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public static function forDefaultConnection(): self
    {
        return new self(Database::connection());
    }

    public function ensureSchema(): void
    {
        $this->connection->exec(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS samples (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    message VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        );

        $countStatement = $this->connection->query('SELECT COUNT(*) AS total FROM samples');
        $count = $countStatement !== false ? (int) $countStatement->fetchColumn() : 0;

        if ($count === 0) {
            $insertStatement = $this->connection->prepare(
                'INSERT INTO samples (message) VALUES (:message)',
            );

            foreach ($this->defaultMessages() as $message) {
                $insertStatement->execute([':message' => $message]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function defaultMessages(): array
    {
        return [
            'Welcome to your PHP workspace!',
            'Edit `public/index.php` to get started.',
            'Run `composer check` before pushing changes.',
        ];
    }
}

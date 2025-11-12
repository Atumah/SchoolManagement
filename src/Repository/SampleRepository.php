<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;
use PDO;

final class SampleRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public static function forDefaultConnection(): self
    {
        return new self(Database::connection());
    }

    /**
     * @return list<array{id:int,message:string}>
     */
    public function latestMessages(int $limit = 5): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, message FROM samples ORDER BY created_at DESC, id DESC LIMIT :limit',
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array{id:int,message:string}> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }
}

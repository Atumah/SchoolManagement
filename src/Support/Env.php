<?php

declare(strict_types=1);

namespace App\Support;

final class Env
{
    /**
     * @param non-empty-string $key
     */
    public static function string(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if ($default !== null) {
            return $default;
        }

        throw new \RuntimeException(sprintf('Environment variable "%s" is not set.', $key));
    }

    /**
     * @param non-empty-string $key
     */
    public static function int(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        if (!is_string($value) || !is_numeric($value)) {
            throw new \RuntimeException(sprintf('Environment variable "%s" must be numeric.', $key));
        }

        return (int) $value;
    }
}

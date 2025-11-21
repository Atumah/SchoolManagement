<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CUID (Collision-resistant Unique Identifier) Generator
 * Generates URL-safe, collision-resistant unique identifiers
 */
class Cuid
{
    private static int $counter = 0;
    private static string $fingerprint = '';

    /**
     * Generate a new CUID
     */
    public static function generate(): string
    {
        $timestamp = (int)(microtime(true) * 1000);
        $counter = self::getCounter();
        $random = self::getRandom();
        $fingerprint = self::getFingerprint();

        $cuid = 'c' . self::toBase36($timestamp) . $counter . $fingerprint . $random;

        return $cuid;
    }

    /**
     * Get or initialize counter
     */
    private static function getCounter(): string
    {
        self::$counter++;
        if (self::$counter > 36 * 36 * 36) {
            self::$counter = 0;
        }
        return str_pad(self::toBase36(self::$counter), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get random string
     */
    private static function getRandom(): string
    {
        $random = '';
        for ($i = 0; $i < 12; $i++) {
            $random .= self::toBase36(random_int(0, 35));
        }
        return $random;
    }

    /**
     * Get fingerprint (hostname-based)
     */
    private static function getFingerprint(): string
    {
        if (self::$fingerprint === '') {
            $hostname = gethostname() ?: 'localhost';
            $hash = crc32($hostname);
            $fingerprint = self::toBase36(abs($hash));
            self::$fingerprint = str_pad(substr($fingerprint, -2), 2, '0', STR_PAD_LEFT);
        }
        return self::$fingerprint;
    }

    /**
     * Convert number to base36
     */
    private static function toBase36(int $number): string
    {
        $base36 = '';
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';

        do {
            $base36 = $chars[$number % 36] . $base36;
            $number = (int)($number / 36);
        } while ($number > 0);

        return $base36;
    }

    /**
     * Validate if a string is a valid CUID format
     */
    public static function isValid(string $cuid): bool
    {
        // CUID format: starts with 'c', followed by base36 characters
        // Total length is typically 25 characters
        return preg_match('/^c[a-z0-9]{24}$/i', $cuid) === 1;
    }
}

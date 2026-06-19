<?php

declare(strict_types=1);

namespace App\Service\Helper;

use Exception;

class Tools
{
    public const int MAX_PAGE_LIMIT = 100;

    /**
     * Clamps a client-supplied page size into [1, MAX_PAGE_LIMIT] so an oversized
     * `limit` cannot force the database/serializer to materialise a whole table.
     */
    public static function clampLimit(int $limit): int
    {
        return min(max($limit, 1), self::MAX_PAGE_LIMIT);
    }

    /**
     * @param string[] $expectedKeys
     * @param array<string, mixed> $data
     *
     * @throws Exception when a required key is missing
     */
    public static function checkExpectedKeys(array $expectedKeys, array $data): bool
    {
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new Exception('Missing required key: ' . $key);
            }
        }

        return true;
    }
}

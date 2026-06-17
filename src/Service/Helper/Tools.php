<?php

declare(strict_types=1);

namespace App\Service\Helper;

use Exception;

class Tools
{
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

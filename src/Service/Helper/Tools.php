<?php

declare(strict_types=1);

namespace App\Service\Helper;

use Exception;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

class Tools
{
    public const int MAX_PAGE_LIMIT = 100;
    public const int MINIMUM_PASSWORD_LENGTH = 8;

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

    /**
     * Validation constraints applied to every plain password a user sets
     * (registration, password reset, change password): non-blank, minimum length,
     * and not exposed in a known data breach (haveibeenpwned, k-anonymity).
     *
     * @return Constraint[]
     */
    public static function passwordConstraints(): array
    {
        return [
            new Assert\NotBlank(message: 'Password cannot be blank.'),
            new Assert\Length(
                min: self::MINIMUM_PASSWORD_LENGTH,
                minMessage: 'Password must be at least {{ limit }} characters long.',
            ),
            new Assert\NotCompromisedPassword(
                message: 'This password has appeared in a data breach. Please choose a different one.',
            ),
        ];
    }
}

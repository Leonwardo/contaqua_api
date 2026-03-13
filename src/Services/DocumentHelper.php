<?php

declare(strict_types=1);

namespace App\Services;

use MongoDB\BSON\ObjectId;

final class DocumentHelper
{
    /** @param array<string, mixed> $document */
    /** @return array<string, mixed> */
    public static function normalize(array $document): array
    {
        foreach ($document as $key => $value) {
            if ($value instanceof ObjectId) {
                $document[$key] = (string) $value;
                continue;
            }

            if (is_array($value)) {
                $document[$key] = self::normalize($value);
            }
        }

        return $document;
    }

    public static function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'y'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'no', 'n'], true)) {
                return false;
            }
        }

        return $default;
    }

    public static function isDateValid(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_int($value)) {
            return $value >= time();
        }

        if (is_string($value)) {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return false;
            }
            return $timestamp >= time();
        }

        return false;
    }
}

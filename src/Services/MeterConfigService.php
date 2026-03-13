<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;

final class MeterConfigService
{
    public function __construct(private MongoCollections $collections)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllowedConfigs(string $user, string $meterId): array
    {
        $cursor = $this->collections->meterConfig()->find();
        $result = [];

        foreach ($cursor as $document) {
            if (!is_array($document)) {
                continue;
            }

            if (!$this->matchesAllowed($document['allowed_users'] ?? null, $user)) {
                continue;
            }

            if (!$this->matchesAllowed($document['allowed_meters'] ?? null, $meterId)) {
                continue;
            }

            $result[] = DocumentHelper::normalize($document);
        }

        return $result;
    }

    private function matchesAllowed(mixed $allowedValue, string $value): bool
    {
        if ($allowedValue === null || $allowedValue === '' || $allowedValue === '*') {
            return true;
        }

        if (is_string($allowedValue)) {
            $items = array_map('trim', explode(',', $allowedValue));
            return in_array($value, $items, true);
        }

        if (is_array($allowedValue)) {
            return in_array($value, $allowedValue, true);
        }

        return false;
    }
}

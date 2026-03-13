<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;

final class MeterSessionService
{
    public function __construct(
        private MongoCollections $collections,
        private Logger $logger
    ) {
    }

    /** @param array<string, mixed> $payload */
    /** @return array<string, mixed> */
    public function storeSession(array $payload): array
    {
        $cleanPayload = [];
        foreach (['counter', 'sessionkey', 'deveui', 'comment'] as $key) {
            if (array_key_exists($key, $payload)) {
                $cleanPayload[$key] = $payload[$key];
            }
        }

        if (!isset($cleanPayload['counter'], $cleanPayload['sessionkey'], $cleanPayload['deveui'])) {
            throw new \InvalidArgumentException('counter, sessionkey e deveui são obrigatórios.');
        }

        $insertResult = $this->collections->meterSession()->insertOne($cleanPayload);

        $this->logger->info('Session stored', [
            'inserted_id' => (string) $insertResult->getInsertedId(),
            'deveui' => $cleanPayload['deveui'] ?? null,
        ]);

        return [
            'inserted_id' => (string) $insertResult->getInsertedId(),
            'stored' => true,
        ];
    }
}

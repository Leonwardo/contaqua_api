<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;
use MongoDB\Collection;

final class UserAuthService
{
    public function __construct(
        private MongoCollections $collections,
        private Logger $logger
    ) {
    }

    /** @return array<string, mixed>|null */
    public function validateToken(string $token): ?array
    {
        $token = strtoupper(trim($token));
        if ($token === '') {
            return null;
        }

        /** @var Collection $collection */
        $collection = $this->collections->userAuth();
        $cursor = $collection->find();
        foreach ($cursor as $document) {
            if (!is_array($document)) {
                continue;
            }

            $access = (int) ($document['access'] ?? 0);
            $userId = (int) ($document['user_id'] ?? 0);
            if ($access <= 0 || $userId <= 0) {
                continue;
            }

            if ($this->buildUserToken($access, $userId) !== $token) {
                continue;
            }

            return DocumentHelper::normalize($document);
        }

        return null;
    }

    public function loginAndGetToken(string $user, string $pass): ?string
    {
        $collection = $this->collections->userAuth();

        $document = $collection->findOne([
            '$or' => [
                ['user' => $user],
            ],
        ]);
        if (!is_array($document)) {
            $this->logger->warning('Login failed: user not found', ['user' => $user]);
            return null;
        }

        $storedPass = (string) ($document['pass'] ?? '');
        $salt = (string) ($document['salt'] ?? '');
        $hashMatches = $salt !== '' && hash_equals($storedPass, hash('sha256', $salt . ':' . $pass));
        $legacyPlainMatches = $salt === '' && $storedPass !== '' && hash_equals($storedPass, $pass);

        if (!$hashMatches && !$legacyPlainMatches) {
            $this->logger->warning('Login failed: wrong password', ['user' => $user]);
            return null;
        }

        $access = (int) ($document['access'] ?? 0);
        $userId = (int) ($document['user_id'] ?? 0);
        if ($access <= 0 || $userId <= 0) {
            $this->logger->warning('Login failed: malformed user document', ['user' => $user]);
            return null;
        }

        return $this->buildUserToken($access, $userId);
    }

    private function buildUserToken(int $access, int $userId): string
    {
        $tokenBytes = array_fill(0, 9, 0);
        $tokenBytes[0] = max(0, $access - 1) & 0xFF;

        $userIdString = (string) max(0, $userId);
        $parseLen = min(3, strlen($userIdString));
        for ($i = 0; $i < $parseLen; $i++) {
            $digit = $userIdString[$i];
            if (ctype_digit($digit)) {
                $tokenBytes[$i + 1] = (int) $digit;
            }
        }

        return strtoupper(bin2hex(pack('C*', ...$tokenBytes)));
    }
}

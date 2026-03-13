<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;

final class MeterAuthService
{
    private function buildAuthTechKey(int $access, int $userId): string
    {
        return strtoupper(substr(hash('sha256', 'AUTH_TECH:' . $access . ':' . $userId), 0, 32));
    }

    public function __construct(
        private MongoCollections $collections,
        private UserAuthService $userAuthService
    ) {
    }

    /** @return array<string, mixed>|null */
    public function authorize(string $authKey, string $user, string $meterId): ?array
    {
        $meterId = strtoupper(trim($meterId));
        $authKey = strtoupper(trim($authKey));

        $document = $this->collections->meterAuth()->findOne([
            '$or' => [
                [
                    'authkey' => $authKey,
                    'user' => $user,
                    'meterid' => $meterId,
                ],
                [
                    'deveui' => $meterId,
                    'authkeys' => ['$in' => [$authKey]],
                ],
            ],
        ]);

        if (!is_array($document)) {
            return null;
        }

        if (!DocumentHelper::isDateValid($document['valid_until'] ?? null)) {
            return null;
        }

        return DocumentHelper::normalize($document);
    }

    public function generateMeterToken(string $userToken, string $challengeHex, string $meterId): ?string
    {
        $userDocument = $this->userAuthService->validateToken($userToken);
        if ($userDocument === null) {
            return null;
        }

        $user = (string) ($userDocument['user'] ?? $userDocument['username'] ?? '');
        if ($user === '') {
            return null;
        }

        $normalizedMeterId = strtoupper(trim($meterId));
        $meterAuth = $this->collections->meterAuth()->findOne([
            '$or' => [
                [
                    'user' => $user,
                    'meterid' => $normalizedMeterId,
                ],
                [
                    'deveui' => $normalizedMeterId,
                ],
            ],
        ]);

        if (!is_array($meterAuth)) {
            return null;
        }

        if (!DocumentHelper::isDateValid($meterAuth['valid_until'] ?? null)) {
            return null;
        }

        $authKeys = [];
        if (isset($meterAuth['authkeys']) && is_array($meterAuth['authkeys'])) {
            foreach ($meterAuth['authkeys'] as $value) {
                if (is_string($value) && $value !== '') {
                    $authKeys[] = strtoupper($value);
                }
            }
        }

        $singleAuthKey = (string) ($meterAuth['authkey'] ?? '');
        if ($singleAuthKey !== '') {
            $authKeys[] = strtoupper($singleAuthKey);
        }
        $authKeys = array_values(array_unique($authKeys));

        $authKey = '';
        $access = (int) ($userDocument['access'] ?? 0);
        $userId = (int) ($userDocument['user_id'] ?? 0);
        if ($access > 0 && $userId > 0) {
            $preferredKey = $this->buildAuthTechKey($access, $userId);
            if (in_array($preferredKey, $authKeys, true)) {
                $authKey = $preferredKey;
            }
        }

        if ($authKey === '' && $authKeys !== []) {
            $authKey = $authKeys[0];
        }
        if ($authKey === '') {
            return null;
        }

        $challenge = @hex2bin($challengeHex);
        if ($challenge === false) {
            return null;
        }

        return strtoupper(hash_hmac('sha256', $challenge, $authKey));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;
use DateInterval;
use DateTimeImmutable;

final class AdminService
{
    /** @var array<string, int> */
    private const ROLE_ACCESS_MAP = [
        'TECHNICIAN' => 1,
        'MANAGER' => 2,
        'MANUFACTURER' => 3,
        'FACTORY' => 4,
    ];

    private bool $mockMode;

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

    private function buildAuthTechKey(int $access, int $userId): string
    {
        return strtoupper(substr(hash('sha256', 'AUTH_TECH:' . $access . ':' . $userId), 0, 32));
    }

    /** @param array<string, mixed> $document */
    private function normalizeUserWithDerivedFields(array $document): array
    {
        $normalized = DocumentHelper::normalize($document);
        $access = (int) ($normalized['access'] ?? 0);
        $userId = (int) ($normalized['user_id'] ?? 0);

        if ($access > 0 && $userId > 0) {
            $normalized['token'] = $this->buildUserToken($access, $userId);
            $normalized['auth_tech'] = $this->buildAuthTechKey($access, $userId);
        }

        return $normalized;
    }

    private function roleFromAccess(int $access): string
    {
        foreach (self::ROLE_ACCESS_MAP as $role => $value) {
            if ($value === $access) {
                return $role;
            }
        }

        return 'TECHNICIAN';
    }

    private function resolveRoleAccess(string $role): int
    {
        $roleCode = strtoupper(trim($role));
        return self::ROLE_ACCESS_MAP[$roleCode] ?? self::ROLE_ACCESS_MAP['TECHNICIAN'];
    }

    /** @return array<string, mixed>|null */
    private function findUserDocument(string $user): ?array
    {
        $user = trim($user);
        if ($user === '') {
            return null;
        }

        $document = $this->collections->userAuth()->findOne([
            '$or' => [
                ['user' => $user],
                ['username' => $user],
            ],
        ]);

        return is_array($document) ? $document : null;
    }

    /** @return array<string, string> */
    private function userAuthKeyMap(): array
    {
        $map = [];
        if ($this->mockMode) {
            return $map;
        }

        $cursor = $this->collections->userAuth()->find();
        foreach ($cursor as $document) {
            if (!is_array($document)) {
                continue;
            }

            $user = (string) ($document['user'] ?? $document['username'] ?? '');
            $access = (int) ($document['access'] ?? 0);
            $userId = (int) ($document['user_id'] ?? 0);
            if ($user === '' || $access <= 0 || $userId <= 0) {
                continue;
            }

            $map[$this->buildAuthTechKey($access, $userId)] = $user;
        }

        return $map;
    }

    /** @return array<int, string> */
    private function parseUsersCsv(string $usersCsv): array
    {
        $rawUsers = preg_split('/[,;\n\r]+/', $usersCsv) ?: [];
        $users = [];
        foreach ($rawUsers as $rawUser) {
            $value = trim($rawUser);
            if ($value !== '' && !in_array($value, $users, true)) {
                $users[] = $value;
            }
        }

        return $users;
    }

    /** @return array{users: array<int, string>, authkeys: array<int, string>} */
    private function resolveUsersAndAuthKeys(string $usersCsv): array
    {
        $users = $this->parseUsersCsv($usersCsv);
        if ($users === []) {
            throw new \InvalidArgumentException('Indique pelo menos um utilizador.');
        }

        $authKeys = [];
        foreach ($users as $user) {
            if ($this->mockMode) {
                $authKeys[] = strtoupper(substr(hash('sha256', 'MOCK_AUTH_TECH:' . $user), 0, 32));
                continue;
            }

            $userDoc = $this->findUserDocument($user);
            if (!is_array($userDoc)) {
                throw new \RuntimeException('Utilizador não encontrado: ' . $user);
            }

            $access = (int) ($userDoc['access'] ?? 0);
            $userId = (int) ($userDoc['user_id'] ?? 0);
            if ($access <= 0 || $userId <= 0) {
                throw new \RuntimeException('Utilizador inválido: ' . $user);
            }

            $authKeys[] = $this->buildAuthTechKey($access, $userId);
        }

        return [
            'users' => $users,
            'authkeys' => array_values(array_unique($authKeys)),
        ];
    }

    /** @param array<int, string> $authKeys */
    private function upsertMeterAuth(string $meterId, array $authKeys): void
    {
        $existing = $this->collections->meterAuth()->findOne([
            '$or' => [
                ['deveui' => $meterId],
                ['meterid' => $meterId],
            ],
        ]);

        if (is_array($existing)) {
            $this->collections->meterAuth()->updateOne(
                ['_id' => $existing['_id']],
                ['$set' => [
                    'deveui' => $meterId,
                    'authkeys' => $authKeys,
                ]]
            );

            return;
        }

        $this->collections->meterAuth()->insertOne([
            'deveui' => $meterId,
            'authkeys' => $authKeys,
        ]);
    }

    private function normalizeDeveui(string $value): string
    {
        $deveui = strtoupper(trim($value));
        if ($deveui === '') {
            throw new \InvalidArgumentException('DevEUI é obrigatório.');
        }

        if (preg_match('/[;,]/', $deveui) === 1) {
            throw new \InvalidArgumentException('DevEUI inválido: use um DevEUI por linha, sem vírgulas ou ponto e vírgula.');
        }

        if (preg_match('/^[0-9A-F]{16}$/', $deveui) !== 1) {
            throw new \InvalidArgumentException('DevEUI inválido: ' . $deveui . '. Formato esperado: 16 caracteres hexadecimais.');
        }

        return $deveui;
    }

    public function __construct(private MongoCollections $collections)
    {
        $this->mockMode = filter_var(getenv('MOCK_MODE') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    }

    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /** @return array<int, string> */
    public function availableRoles(): array
    {
        return array_keys(self::ROLE_ACCESS_MAP);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        if ($this->mockMode) {
            return [
                'user_auth' => 3,
                'meter_auth' => 5,
                'meter_config' => 2,
                'meter_session' => 12,
            ];
        }

        return [
            'user_auth' => $this->collections->userAuth()->countDocuments(),
            'meter_auth' => $this->collections->meterAuth()->countDocuments(),
            'meter_config' => $this->collections->meterConfig()->countDocuments(),
            'meter_session' => $this->collections->meterSession()->countDocuments(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function latestSessions(int $limit = 20): array
    {
        if ($this->mockMode) {
            return [
                [
                    '_id' => 'mock_session_1',
                    'user' => 'demo_user',
                    'meter_id' => 'MTR001',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'data' => ['voltage' => 230, 'current' => 5.2],
                    'received_at' => date('Y-m-d H:i:s'),
                ],
                [
                    '_id' => 'mock_session_2',
                    'user' => 'demo_user',
                    'meter_id' => 'MTR002',
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'data' => ['voltage' => 228, 'current' => 4.8],
                    'received_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                ],
            ];
        }

        $cursor = $this->collections->meterSession()->find([], [
            'sort' => ['_id' => -1],
            'limit' => $limit,
        ]);

        $items = [];
        foreach ($cursor as $document) {
            if (is_array($document)) {
                $items[] = DocumentHelper::normalize($document);
            }
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    public function listUsers(int $limit = 100): array
    {
        if ($this->mockMode) {
            return [
                [
                    '_id' => 'mock_user_1',
                    'user' => 'demo_user',
                    'access' => 1,
                    'user_id' => 1,
                    'salt' => 'demo_salt',
                    'pass' => 'demo_hash',
                ],
                [
                    '_id' => 'mock_user_2',
                    'user' => 'test_user',
                    'access' => 2,
                    'user_id' => 2,
                    'salt' => 'demo_salt',
                    'pass' => 'demo_hash',
                ],
            ];
        }

        $cursor = $this->collections->userAuth()->find([], ['limit' => $limit]);
        $items = [];
        foreach ($cursor as $document) {
            if (is_array($document)) {
                $items[] = $this->normalizeUserWithDerivedFields($document);
            }
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    public function listMeters(int $limit = 100): array
    {
        if ($this->mockMode) {
            return [
                [
                    '_id' => 'mock_meter_1',
                    'deveui' => 'A1B2C3D4E5F60708',
                    'authkeys' => ['00112233445566778899AABBCCDDEEFF'],
                    'assigned_users' => ['demo_user'],
                ],
            ];
        }

        $authKeyMap = $this->userAuthKeyMap();

        $cursor = $this->collections->meterAuth()->find([], [
            'sort' => ['_id' => -1],
            'limit' => $limit,
        ]);

        $items = [];
        foreach ($cursor as $document) {
            if (is_array($document)) {
                $normalized = DocumentHelper::normalize($document);
                $assignedUsers = [];
                if (isset($normalized['authkeys']) && is_array($normalized['authkeys'])) {
                    foreach ($normalized['authkeys'] as $key) {
                        if (!is_string($key) || $key === '') {
                            continue;
                        }

                        $user = $authKeyMap[strtoupper($key)] ?? null;
                        if ($user !== null && !in_array($user, $assignedUsers, true)) {
                            $assignedUsers[] = $user;
                        }
                    }
                }

                $normalized['assigned_users'] = $assignedUsers;
                $items[] = $normalized;
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public function createUser(string $user, string $pass, string $role = 'technician', int $validDays = 365): array
    {
        $user = trim($user);
        $pass = trim($pass);
        $access = $this->resolveRoleAccess($role);
        $roleCode = $this->roleFromAccess($access);

        if ($user === '' || $pass === '') {
            throw new \InvalidArgumentException('User e password são obrigatórios.');
        }

        $validUntil = (new DateTimeImmutable('now'))->add(new DateInterval('P' . max(1, $validDays) . 'D'));
        $rights = (string) max(0, $access - 1);
        $salt = bin2hex(random_bytes(8));
        $passHash = hash('sha256', $salt . ':' . $pass);

        $lastUser = null;
        if (!$this->mockMode) {
            $lastUser = $this->collections->userAuth()->findOne([], [
                'sort' => ['user_id' => -1, '_id' => -1],
            ]);
        }
        $nextUserId = is_array($lastUser) ? ((int) ($lastUser['user_id'] ?? 0) + 1) : 1;
        $techKey = $this->buildAuthTechKey($access, $nextUserId);
        $token = $this->buildUserToken($access, $nextUserId);
        $qrPayload = 'userid=' . rawurlencode($user) . '&rights=' . $rights . '&auth_tech=' . $techKey;

        if ($this->mockMode) {
            return [
                'ok' => true,
                'mock' => true,
                'user' => $user,
                'role' => strtolower($roleCode),
                'access' => $access,
                'user_id' => $nextUserId,
                'token' => $token,
                'auth_tech' => $techKey,
                'qr_payload' => $qrPayload,
                'valid_until' => $validUntil->format('Y-m-d H:i:s'),
            ];
        }

        if ($this->findUserDocument($user) !== null) {
            throw new \RuntimeException('Utilizador já existe.');
        }

        $this->collections->userAuth()->insertOne([
            'access' => $access,
            'user_id' => $nextUserId,
            'user' => $user,
            'pass' => $passHash,
            'salt' => $salt,
        ]);

        return [
            'ok' => true,
            'mock' => false,
            'user' => $user,
            'role' => strtolower($roleCode),
            'access' => $access,
            'user_id' => $nextUserId,
            'token' => $token,
            'auth_tech' => $techKey,
            'qr_payload' => $qrPayload,
            'valid_until' => $validUntil->format('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    public function createMeterLink(string $meterId, string $usersCsv, int $validDays = 365): array
    {
        $meterId = $this->normalizeDeveui($meterId);

        $validUntil = (new DateTimeImmutable('now'))->add(new DateInterval('P' . max(1, $validDays) . 'D'));
        $resolved = $this->resolveUsersAndAuthKeys($usersCsv);
        $users = $resolved['users'];
        $authKeys = $resolved['authkeys'];

        if ($this->mockMode) {
            return [
                'ok' => true,
                'mock' => true,
                'meterid' => $meterId,
                'deveui' => $meterId,
                'assigned_users' => $users,
                'authkeys' => $authKeys,
                'valid_until' => $validUntil->format('Y-m-d H:i:s'),
            ];
        }

        $this->upsertMeterAuth($meterId, $authKeys);

        return [
            'ok' => true,
            'mock' => false,
            'meterid' => $meterId,
            'deveui' => $meterId,
            'assigned_users' => $users,
            'authkeys' => $authKeys,
            'valid_until' => $validUntil->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Import meter links in bulk from multiline text.
     * Supported line formats:
     * - DevEUI por linha (ex.: 02F8CCFFFE483203)
     *
     * @return array<string, mixed>
     */
    public function importMeterList(string $usersCsv, string $rawList, int $validDays = 365): array
    {
        $resolved = $this->resolveUsersAndAuthKeys($usersCsv);
        $users = $resolved['users'];
        $authKeys = $resolved['authkeys'];

        $lines = preg_split('/\r\n|\r|\n/', $rawList) ?: [];
        $processed = 0;
        $createdOrUpdated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            $processed++;

            try {
                $meterId = $this->normalizeDeveui($trimmed);
                if ($this->mockMode) {
                    $this->createMeterLink($meterId, implode(',', $users), $validDays);
                } else {
                    $this->upsertMeterAuth($meterId, $authKeys);
                }
                $createdOrUpdated++;
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = 'Linha ' . ($index + 1) . ': ' . $exception->getMessage();
            }
        }

        return [
            'ok' => true,
            'users' => $users,
            'processed' => $processed,
            'created_or_updated' => $createdOrUpdated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /** @return array<string, mixed> */
    public function userQrData(string $user): array
    {
        $userDoc = $this->findUserDocument($user);
        if (!is_array($userDoc)) {
            throw new \RuntimeException('Utilizador não encontrado.');
        }

        $userName = (string) ($userDoc['user'] ?? $userDoc['username'] ?? '');
        $access = (int) ($userDoc['access'] ?? 0);
        $userId = (int) ($userDoc['user_id'] ?? 0);
        if ($userName === '' || $access <= 0 || $userId <= 0) {
            throw new \RuntimeException('Utilizador inválido para gerar QR.');
        }

        $authTech = $this->buildAuthTechKey($access, $userId);
        $rights = (string) max(0, $access - 1);

        return [
            'ok' => true,
            'user' => $userName,
            'access' => $access,
            'user_id' => $userId,
            'token' => $this->buildUserToken($access, $userId),
            'auth_tech' => $authTech,
            'qr_payload' => 'userid=' . rawurlencode($userName) . '&rights=' . $rights . '&auth_tech=' . $authTech,
        ];
    }

    /** @return array<string, mixed> */
    public function updateUser(string $user, string $role = '', string $pass = ''): array
    {
        if ($this->mockMode) {
            return ['ok' => true, 'mock' => true, 'user' => trim($user)];
        }

        $existing = $this->findUserDocument($user);
        if (!is_array($existing)) {
            throw new \RuntimeException('Utilizador não encontrado.');
        }

        $access = (int) ($existing['access'] ?? 0);
        $userId = (int) ($existing['user_id'] ?? 0);
        if ($access <= 0 || $userId <= 0) {
            throw new \RuntimeException('Documento de utilizador inválido.');
        }

        $set = [];
        $newAccess = $access;
        if (trim($role) !== '') {
            $newAccess = $this->resolveRoleAccess($role);
            if ($newAccess !== $access) {
                $set['access'] = $newAccess;
            }
        }

        $pass = trim($pass);
        if ($pass !== '') {
            $salt = bin2hex(random_bytes(8));
            $set['salt'] = $salt;
            $set['pass'] = hash('sha256', $salt . ':' . $pass);
        }

        if ($set !== []) {
            $this->collections->userAuth()->updateOne(['_id' => $existing['_id']], ['$set' => $set]);
        }

        $oldAuthKey = $this->buildAuthTechKey($access, $userId);
        $newAuthKey = $this->buildAuthTechKey($newAccess, $userId);
        if ($oldAuthKey !== $newAuthKey) {
            $cursor = $this->collections->meterAuth()->find(['authkeys' => ['$in' => [$oldAuthKey]]]);
            foreach ($cursor as $meterDocument) {
                if (!is_array($meterDocument)) {
                    continue;
                }

                $keys = [];
                if (isset($meterDocument['authkeys']) && is_array($meterDocument['authkeys'])) {
                    foreach ($meterDocument['authkeys'] as $key) {
                        if (is_string($key) && $key !== '') {
                            $keys[] = strtoupper($key);
                        }
                    }
                }

                $keys = array_values(array_unique(array_map(
                    static fn (string $value): string => $value === $oldAuthKey ? $newAuthKey : $value,
                    $keys
                )));

                $this->collections->meterAuth()->updateOne(['_id' => $meterDocument['_id']], ['$set' => ['authkeys' => $keys]]);
            }
        }

        return $this->userQrData($user);
    }

    /** @return array<string, mixed> */
    public function deleteUser(string $user): array
    {
        if ($this->mockMode) {
            return ['ok' => true, 'mock' => true, 'user' => trim($user), 'deleted' => 1];
        }

        $existing = $this->findUserDocument($user);
        if (!is_array($existing)) {
            throw new \RuntimeException('Utilizador não encontrado.');
        }

        $userName = (string) ($existing['user'] ?? $existing['username'] ?? '');
        $access = (int) ($existing['access'] ?? 0);
        $userId = (int) ($existing['user_id'] ?? 0);
        $authKey = ($access > 0 && $userId > 0) ? $this->buildAuthTechKey($access, $userId) : '';

        $deleteResult = $this->collections->userAuth()->deleteOne(['_id' => $existing['_id']]);
        $detached = 0;

        if ($authKey !== '') {
            $cursor = $this->collections->meterAuth()->find(['authkeys' => ['$in' => [$authKey]]]);
            foreach ($cursor as $meterDocument) {
                if (!is_array($meterDocument)) {
                    continue;
                }

                $keys = [];
                if (isset($meterDocument['authkeys']) && is_array($meterDocument['authkeys'])) {
                    foreach ($meterDocument['authkeys'] as $key) {
                        if (is_string($key) && $key !== '' && strtoupper($key) !== $authKey) {
                            $keys[] = strtoupper($key);
                        }
                    }
                }

                $this->collections->meterAuth()->updateOne(['_id' => $meterDocument['_id']], ['$set' => ['authkeys' => array_values(array_unique($keys))]]);
                $detached++;
            }
        }

        return [
            'ok' => true,
            'user' => $userName,
            'deleted' => $deleteResult->getDeletedCount(),
            'meters_detached' => $detached,
        ];
    }

    /** @return array<string, mixed> */
    public function assignMeterUsers(string $meterId, string $usersCsv): array
    {
        $meterId = strtoupper(trim($meterId));
        if ($meterId === '') {
            throw new \InvalidArgumentException('meterid é obrigatório.');
        }

        $resolved = $this->resolveUsersAndAuthKeys($usersCsv);
        $users = $resolved['users'];
        $authKeys = $resolved['authkeys'];

        if ($this->mockMode) {
            return ['ok' => true, 'mock' => true, 'deveui' => $meterId, 'assigned_users' => $users, 'authkeys' => $authKeys];
        }

        $existing = $this->collections->meterAuth()->findOne([
            '$or' => [
                ['deveui' => $meterId],
                ['meterid' => $meterId],
            ],
        ]);

        if (is_array($existing)) {
            $this->collections->meterAuth()->updateOne(
                ['_id' => $existing['_id']],
                ['$set' => ['deveui' => $meterId, 'authkeys' => $authKeys]]
            );
        } else {
            $this->collections->meterAuth()->insertOne(['deveui' => $meterId, 'authkeys' => $authKeys]);
        }

        return ['ok' => true, 'deveui' => $meterId, 'assigned_users' => $users, 'authkeys' => $authKeys];
    }

    /** @return array<string, mixed> */
    public function deleteMeter(string $meterId): array
    {
        $meterId = strtoupper(trim($meterId));
        if ($meterId === '') {
            throw new \InvalidArgumentException('meterid é obrigatório.');
        }

        if ($this->mockMode) {
            return ['ok' => true, 'mock' => true, 'deveui' => $meterId, 'deleted' => 1];
        }

        $result = $this->collections->meterAuth()->deleteOne([
            '$or' => [
                ['deveui' => $meterId],
                ['meterid' => $meterId],
            ],
        ]);

        return ['ok' => true, 'deveui' => $meterId, 'deleted' => $result->getDeletedCount()];
    }
}

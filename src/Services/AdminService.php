<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;
use Psr\Log\LoggerInterface;

class AdminService
{
    public function __construct(
        private MongoCollections $collections,
        private UserAuthService $userAuthService,
        private MeterAuthService $meterAuthService,
        private MeterConfigService $meterConfigService,
        private MeterSessionService $meterSessionService,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Get dashboard counts
     */
    public function getCounts(): array
    {
        return [
            'user_auth' => $this->userAuthService->count(),
            'meter_auth' => $this->meterAuthService->count(),
            'meter_config' => $this->meterConfigService->count(),
            'meter_session' => $this->meterSessionService->count(),
        ];
    }
    
    /**
     * Get latest sessions for dashboard
     */
    public function getLatestSessions(int $limit = 20): array
    {
        $sessions = $this->meterSessionService->getLatestSessions($limit);
        
        return array_map(fn($session) => [
            'id' => $session->_id ? (string) $session->_id : null,
            'deveui' => $session->deveui,
            'counter' => $session->counter,
            'sessionkey' => $session->sessionkey,
            'timestamp' => $session->timestamp?->format('Y-m-d H:i:s'),
            'comment' => $session->comment,
        ], $sessions);
    }
    
    /**
     * Get users list for admin
     */
    public function getUsers(): array
    {
        $users = $this->userAuthService->getAllUsers();
        
        return array_map(fn($user) => [
            '_id' => $user->_id ? (string) $user->_id : null,
            'user' => $user->user,
            'user_id' => $user->user_id,
            'access' => $user->access,
            'role' => $user->getRole(),
            'token' => $user->buildToken(),
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
        ], $users);
    }
    
    /**
     * Get meters list for admin
     */
    public function getMeters(): array
    {
        $meters = $this->meterAuthService->getAllMeters();
        
        return array_map(fn($meter) => [
            '_id' => $meter->_id ? (string) $meter->_id : null,
            'deveui' => $meter->deveui,
            'authkeys' => $meter->authkeys,
            'assigned_users' => $meter->assigned_users,
            'valid_until' => $meter->valid_until?->format('Y-m-d H:i:s'),
            'is_valid' => $meter->isValid(),
            'created_at' => $meter->created_at?->format('Y-m-d H:i:s'),
        ], $meters);
    }
    
    /**
     * Create new user
     */
    public function createUser(string $username, string $password, string $role = 'TECHNICIAN', ?int $userId = null, int $validDays = 365): array
    {
        $user = $this->userAuthService->createUser($username, $password, $role, $userId);
        
        return [
            'user' => $user->user,
            'user_id' => $user->user_id,
            'access' => $user->access,
            'role' => $user->getRole(),
            'token' => $user->buildToken(),
            'password' => $password, // Return for display only
        ];
    }
    
    /**
     * Update user
     */
    public function updateUser(string $username, ?string $role = null, ?string $password = null): void
    {
        $this->userAuthService->updateUser($username, $role, $password);
    }
    
    /**
     * Delete user
     */
    public function deleteUser(string $username): void
    {
        $this->userAuthService->deleteUser($username);
    }
    
    /**
     * Create or update meter
     */
    public function createOrUpdateMeter(string $deveui, string $usersCsv, int $validDays = 365): array
    {
        $usernames = array_map('trim', explode(',', $usersCsv));
        $usernames = array_filter($usernames);
        
        $meter = $this->meterAuthService->createOrUpdateMeter($deveui, $usernames, $validDays);
        
        return [
            'deveui' => $meter->deveui,
            'assigned_users' => $meter->assigned_users,
            'authkeys' => $meter->authkeys,
            'valid_until' => $meter->valid_until?->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Assign users to meter
     */
    public function assignMeterUsers(string $deveui, string $usersCsv): void
    {
        $usernames = array_map('trim', explode(',', $usersCsv));
        $usernames = array_filter($usernames);
        
        $this->meterAuthService->assignUsers($deveui, $usernames);
    }
    
    /**
     * Delete meter
     */
    public function deleteMeter(string $deveui): void
    {
        $this->meterAuthService->deleteMeter($deveui);
    }
    
    /**
     * Bulk import meters
     */
    public function bulkImportMeters(string $usersCsv, string $meterList, int $validDays = 365): array
    {
        $usernames = array_map('trim', explode(',', $usersCsv));
        $usernames = array_filter($usernames);
        
        $meters = array_map('trim', explode("\n", $meterList));
        $meters = array_filter($meters);
        
        $created = 0;
        $skipped = 0;
        
        foreach ($meters as $deveui) {
            $deveui = strtoupper(trim($deveui));
            if (strlen($deveui) !== 16 || !ctype_xdigit($deveui)) {
                $skipped++;
                continue;
            }
            
            try {
                $this->meterAuthService->createOrUpdateMeter($deveui, $usernames, $validDays);
                $created++;
            } catch (\Exception $e) {
                $this->logger->warning('Failed to import meter', ['deveui' => $deveui, 'error' => $e->getMessage()]);
                $skipped++;
            }
        }
        
        return [
            'created_or_updated' => $created,
            'skipped' => $skipped,
        ];
    }
    
    /**
     * Get system status
     */
    public function getSystemStatus(): array
    {
        $mongoStatus = $this->collections->getClient()->admin->command(['ping' => 1]);
        
        return [
            'mongodb' => $mongoStatus->toArray()[0]['ok'] === 1.0 ? 'connected' : 'error',
            'counts' => $this->getCounts(),
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Get available roles
     */
    public function getRoles(): array
    {
        return [
            ['id' => 1, 'name' => 'TECHNICIAN', 'label' => 'Técnico'],
            ['id' => 2, 'name' => 'MANAGER', 'label' => 'Gestor'],
            ['id' => 3, 'name' => 'MANUFACTURER', 'label' => 'Fabricante'],
            ['id' => 4, 'name' => 'FACTORY', 'label' => 'Fábrica'],
        ];
    }
}

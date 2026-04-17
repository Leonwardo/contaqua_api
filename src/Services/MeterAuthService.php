<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;
use App\Models\MeterAuth;
use App\Models\UserAuth;
use Psr\Log\LoggerInterface;

class MeterAuthService
{
    public function __construct(
        private MongoCollections $collections,
        private UserAuthService $userAuthService,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Authorize meter access using authkey
     */
    public function authorize(string $authKey, string $username, string $deveui): ?MeterAuth
    {
        $deveui = strtoupper(trim($deveui));
        $authKey = strtoupper(trim($authKey));
        
        $this->logger->debug('Meter authorization attempt', ['deveui' => $deveui, 'user' => $username]);
        
        try {
            // Support both new and legacy query structures
            $document = $this->collections->meterAuth()->findOne([
                '$or' => [
                    [
                        'authkey' => $authKey,
                        'user' => $username,
                        'meterid' => $deveui,
                    ],
                    [
                        'deveui' => $deveui,
                        'authkeys' => ['$in' => [$authKey]],
                    ],
                ],
            ]);
            
            if ($document === null) {
                $this->logger->warning('Meter authorization failed: not found', ['deveui' => $deveui]);
                return null;
            }
            
            $meter = MeterAuth::fromArray($document);
            
            if (!$meter->isValid()) {
                $this->logger->warning('Meter authorization failed: expired', ['deveui' => $deveui]);
                return null;
            }
            
            $this->logger->info('Meter authorized', ['deveui' => $deveui, 'user' => $username]);
            return $meter;
        } catch (\Exception $e) {
            $this->logger->error('Error authorizing meter', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Generate meter token for authentication
     */
    public function generateMeterToken(string $userToken, string $challengeHex, string $deveui): ?string
    {
        $user = $this->userAuthService->validateToken($userToken);
        if ($user === null) {
            $this->logger->warning('Meter token generation failed: invalid user token');
            return null;
        }
        
        $deveui = strtoupper(trim($deveui));
        
        $this->logger->debug('Generating meter token', ['deveui' => $deveui, 'user' => $user->user]);
        
        try {
            $document = $this->collections->meterAuth()->findOne([
                '$or' => [
                    ['deveui' => $deveui],
                    ['meterid' => $deveui],
                ],
            ]);
            
            if ($document === null) {
                $this->logger->warning('Meter token generation failed: meter not found', ['deveui' => $deveui]);
                return null;
            }
            
            $meter = MeterAuth::fromArray($document);
            
            if (!$meter->isValid()) {
                $this->logger->warning('Meter token generation failed: expired', ['deveui' => $deveui]);
                return null;
            }
            
            // Get the auth key to use for HMAC
            $authKey = $this->getAuthKeyForUser($meter, $user);
            
            if ($authKey === null) {
                $this->logger->warning('Meter token generation failed: no auth key', ['deveui' => $deveui]);
                return null;
            }
            
            $challenge = @hex2bin($challengeHex);
            if ($challenge === false) {
                $this->logger->warning('Meter token generation failed: invalid challenge');
                return null;
            }
            
            $token = strtoupper(hash_hmac('sha256', $challenge, $authKey));
            $this->logger->info('Meter token generated', ['deveui' => $deveui]);
            
            return $token;
        } catch (\Exception $e) {
            $this->logger->error('Error generating meter token', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get the appropriate auth key for a user
     */
    private function getAuthKeyForUser(MeterAuth $meter, UserAuth $user): ?string
    {
        // First try the user's deterministic auth key
        $preferredKey = MeterAuth::generateAuthTechKey($user->access, $user->user_id);
        
        if ($meter->hasAuthKey($preferredKey)) {
            return $preferredKey;
        }
        
        // Fall back to first available key
        if ($meter->authkeys !== []) {
            return $meter->authkeys[0];
        }
        
        // Legacy single authkey
        if ($meter->authkey !== null && $meter->authkey !== '') {
            return $meter->authkey;
        }
        
        return null;
    }
    
    /**
     * Find meter by deveui
     */
    public function findByDeveui(string $deveui): ?MeterAuth
    {
        $deveui = strtoupper(trim($deveui));
        
        try {
            $document = $this->collections->meterAuth()->findOne([
                '$or' => [
                    ['deveui' => $deveui],
                    ['meterid' => $deveui],
                ],
            ]);
            
            return $document ? MeterAuth::fromArray($document) : null;
        } catch (\Exception $e) {
            $this->logger->error('Error finding meter', ['deveui' => $deveui, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Create or update meter with users
     */
    public function createOrUpdateMeter(string $deveui, array $usernames, int $validDays = 365): MeterAuth
    {
        $deveui = strtoupper(trim($deveui));
        
        $meter = $this->findByDeveui($deveui);
        if ($meter === null) {
            $meter = new MeterAuth();
            $meter->deveui = $deveui;
        }
        
        // Add users
        foreach ($usernames as $username) {
            $user = $this->userAuthService->findByUsername($username);
            if ($user !== null) {
                $meter->addUserAuthKey($user->access, $user->user_id, $user->user);
            }
        }
        
        $meter->setValidityDays($validDays);
        $meter->updated_at = new \DateTimeImmutable();
        
        if ($meter->_id === null) {
            $meter->created_at = new \DateTimeImmutable();
            $result = $this->collections->meterAuth()->insertOne($meter->toArray());
            $meter->_id = $result->getInsertedId();
            $this->logger->info('Meter created', ['deveui' => $deveui]);
        } else {
            $this->collections->meterAuth()->updateOne(
                ['_id' => $meter->_id],
                ['$set' => $meter->toArray()]
            );
            $this->logger->info('Meter updated', ['deveui' => $deveui]);
        }
        
        return $meter;
    }
    
    /**
     * Assign users to meter
     */
    public function assignUsers(string $deveui, array $usernames): void
    {
        $meter = $this->findByDeveui($deveui);
        if ($meter === null) {
            throw new \InvalidArgumentException("Meter '{$deveui}' not found");
        }
        
        // Reset and re-add all users
        $meter->authkeys = [];
        $meter->assigned_users = [];
        
        foreach ($usernames as $username) {
            $user = $this->userAuthService->findByUsername($username);
            if ($user !== null) {
                $meter->addUserAuthKey($user->access, $user->user_id, $user->user);
            }
        }
        
        $this->collections->meterAuth()->updateOne(
            ['_id' => $meter->_id],
            ['$set' => [
                'authkeys' => $meter->authkeys,
                'assigned_users' => $meter->assigned_users,
                'updated_at' => new \DateTimeImmutable(),
            ]]
        );
        
        $this->logger->info('Meter users assigned', ['deveui' => $deveui, 'users' => $usernames]);
    }
    
    /**
     * Delete meter
     */
    public function deleteMeter(string $deveui): void
    {
        $meter = $this->findByDeveui($deveui);
        if ($meter === null) {
            throw new \InvalidArgumentException("Meter '{$deveui}' not found");
        }
        
        $this->collections->meterAuth()->deleteOne(['_id' => $meter->_id]);
        $this->logger->info('Meter deleted', ['deveui' => $deveui]);
    }
    
    /**
     * Get all meters
     * @return MeterAuth[]
     */
    public function getAllMeters(?int $limit = null): array
    {
        try {
            $options = ['sort' => ['deveui' => 1]];
            if ($limit !== null) {
                $options['limit'] = $limit;
            }
            
            $cursor = $this->collections->meterAuth()->find([], $options);
            $meters = [];
            
            foreach ($cursor as $document) {
                $meters[] = MeterAuth::fromArray($document);
            }
            
            return $meters;
        } catch (\Exception $e) {
            $this->logger->error('Error getting meters', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Count total meters
     */
    public function count(): int
    {
        return $this->collections->meterAuth()->countDocuments();
    }
}

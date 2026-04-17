<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\BSON\ObjectId;

class MeterAuth implements ModelInterface
{
    public ?ObjectId $_id = null;
    public string $deveui = '';
    /** @var string[] */
    public array $authkeys = [];
    public ?\DateTimeImmutable $valid_until = null;
    /** @var string[] */
    public array $assigned_users = [];
    public ?string $authkey = null; // Legacy single authkey
    public ?string $user = null; // Legacy user
    public ?string $meterid = null; // Legacy meterid
    public ?\DateTimeImmutable $created_at = null;
    public ?\DateTimeImmutable $updated_at = null;
    
    public function toArray(): array
    {
        $data = [
            'deveui' => $this->deveui,
            'authkeys' => $this->authkeys,
            'assigned_users' => $this->assigned_users,
        ];
        
        if ($this->_id !== null) {
            $data['_id'] = $this->_id;
        }
        
        if ($this->valid_until !== null) {
            $data['valid_until'] = new \MongoDB\BSON\UTCDateTime($this->valid_until->getTimestamp() * 1000);
        }
        
        return $data;
    }
    
    public static function collectionName(): string
    {
        return 'meter_auth';
    }
    
    public static function fromArray(array $data): static
    {
        $model = new static();
        
        if (isset($data['_id'])) {
            $model->_id = $data['_id'] instanceof ObjectId 
                ? $data['_id'] 
                : new ObjectId($data['_id']);
        }
        
        $model->deveui = strtoupper(trim((string) ($data['deveui'] ?? '')));
        $model->authkeys = isset($data['authkeys']) && is_array($data['authkeys']) 
            ? array_map('strtoupper', array_filter($data['authkeys'], 'is_string')) 
            : [];
        $model->assigned_users = isset($data['assigned_users']) && is_array($data['assigned_users']) 
            ? $data['assigned_users'] 
            : [];
        
        // Legacy fields
        $model->authkey = isset($data['authkey']) ? strtoupper((string) $data['authkey']) : null;
        $model->user = $data['user'] ?? null;
        $model->meterid = isset($data['meterid']) ? strtoupper((string) $data['meterid']) : null;
        
        // Add legacy authkey to array if present
        if ($model->authkey !== null && $model->authkey !== '' && !in_array($model->authkey, $model->authkeys, true)) {
            $model->authkeys[] = $model->authkey;
        }
        
        // Parse valid_until
        if (isset($data['valid_until'])) {
            if ($data['valid_until'] instanceof \MongoDB\BSON\UTCDateTime) {
                $model->valid_until = (new \DateTimeImmutable())->setTimestamp($data['valid_until']->toDateTime()->getTimestamp());
            } elseif ($data['valid_until'] instanceof \DateTimeInterface) {
                $model->valid_until = \DateTimeImmutable::createFromFormat('U', (string) $data['valid_until']->getTimestamp());
            } elseif (is_string($data['valid_until'])) {
                $model->valid_until = new \DateTimeImmutable($data['valid_until']);
            }
        }
        
        if (isset($data['created_at'])) {
            $model->created_at = $data['created_at'] instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromFormat('U', (string) $data['created_at']->getTimestamp())
                : new \DateTimeImmutable($data['created_at']);
        }
        
        return $model;
    }
    
    /**
     * Generate deterministic auth key for a user
     */
    public static function generateAuthTechKey(int $access, int $userId): string
    {
        return strtoupper(substr(hash('sha256', 'AUTH_TECH:' . $access . ':' . $userId), 0, 32));
    }
    
    /**
     * Check if meter is valid (not expired)
     */
    public function isValid(): bool
    {
        if ($this->valid_until === null) {
            return true;
        }
        return $this->valid_until > new \DateTimeImmutable();
    }
    
    /**
     * Check if auth key is valid for this meter
     */
    public function hasAuthKey(string $authKey): bool
    {
        $authKey = strtoupper(trim($authKey));
        return in_array($authKey, $this->authkeys, true);
    }
    
    /**
     * Add auth key for a user
     */
    public function addUserAuthKey(int $access, int $userId, string $username): void
    {
        $authKey = self::generateAuthTechKey($access, $userId);
        if (!in_array($authKey, $this->authkeys, true)) {
            $this->authkeys[] = $authKey;
        }
        if (!in_array($username, $this->assigned_users, true)) {
            $this->assigned_users[] = $username;
        }
    }
    
    /**
     * Set validity period
     */
    public function setValidityDays(int $days): void
    {
        $this->valid_until = (new \DateTimeImmutable())->modify("+{$days} days");
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\BSON\ObjectId;

class UserAuth implements ModelInterface
{
    public ?ObjectId $_id = null;
    public int $access = 0;
    public int $user_id = 0;
    public string $user = '';
    public string $pass = '';
    public string $salt = '';
    public ?string $role = null;
    public ?\DateTimeImmutable $created_at = null;
    public ?\DateTimeImmutable $updated_at = null;
    
    public function toArray(): array
    {
        $data = [
            'access' => $this->access,
            'user_id' => $this->user_id,
            'user' => $this->user,
            'pass' => $this->pass,
            'salt' => $this->salt,
        ];
        
        if ($this->_id !== null) {
            $data['_id'] = $this->_id;
        }
        
        return $data;
    }
    
    public static function collectionName(): string
    {
        return 'user_auth';
    }
    
    public static function fromArray(array $data): static
    {
        $model = new static();
        
        if (isset($data['_id'])) {
            $model->_id = $data['_id'] instanceof ObjectId 
                ? $data['_id'] 
                : new ObjectId($data['_id']);
        }
        
        $model->access = (int) ($data['access'] ?? 0);
        $model->user_id = (int) ($data['user_id'] ?? 0);
        $model->user = (string) ($data['user'] ?? '');
        $model->pass = (string) ($data['pass'] ?? '');
        $model->salt = (string) ($data['salt'] ?? '');
        
        if (isset($data['created_at'])) {
            $model->created_at = $data['created_at'] instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromFormat('U', (string) $data['created_at']->getTimestamp())
                : new \DateTimeImmutable($data['created_at']);
        }
        
        return $model;
    }
    
    /**
     * Build legacy user token from access and user_id
     * Format: 9 bytes -> hex (18 chars)
     */
    public function buildToken(): string
    {
        $tokenBytes = array_fill(0, 9, 0);
        $tokenBytes[0] = max(0, $this->access - 1) & 0xFF;
        
        $userIdString = (string) max(0, $this->user_id);
        $parseLen = min(3, strlen($userIdString));
        for ($i = 0; $i < $parseLen; $i++) {
            $digit = $userIdString[$i];
            if (ctype_digit($digit)) {
                $tokenBytes[$i + 1] = (int) $digit;
            }
        }
        
        return strtoupper(bin2hex(pack('C*', ...$tokenBytes)));
    }
    
    /**
     * Set password with SHA256 hashing
     */
    public function setPassword(string $plainPassword): void
    {
        $this->salt = bin2hex(random_bytes(16));
        $this->pass = hash('sha256', $this->salt . ':' . $plainPassword);
    }
    
    /**
     * Verify password against stored hash
     */
    public function verifyPassword(string $plainPassword, bool $allowLegacyPlain = false): bool
    {
        // Standard SHA256 verification
        if ($this->salt !== '') {
            $hash = hash('sha256', $this->salt . ':' . $plainPassword);
            return hash_equals($this->pass, $hash);
        }
        
        // Legacy plain password (for migration)
        if ($allowLegacyPlain && $this->pass !== '') {
            return hash_equals($this->pass, $plainPassword);
        }
        
        return false;
    }
    
    /**
     * Get role name from access level
     */
    public function getRole(): string
    {
        return match ($this->access) {
            1 => 'TECHNICIAN',
            2 => 'MANAGER',
            3 => 'MANUFACTURER',
            4 => 'FACTORY',
            default => 'UNKNOWN',
        };
    }
    
    /**
     * Set access level from role name
     */
    public function setRole(string $role): void
    {
        $this->access = match (strtoupper($role)) {
            'TECHNICIAN' => 1,
            'MANAGER' => 2,
            'MANUFACTURER' => 3,
            'FACTORY' => 4,
            default => 1,
        };
    }
}

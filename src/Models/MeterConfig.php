<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\BSON\ObjectId;

class MeterConfig implements ModelInterface
{
    public ?ObjectId $_id = null;
    public string $name = '';
    public string $category = 'general';
    public string $file_content = '';
    /** @var string[] */
    public array $assigned_users = [];
    public ?string $deveui = null;
    public ?string $description = null;
    public ?string $diagnostic_for = null;
    public ?string $diagnostic_script = null;
    public ?\DateTimeImmutable $created_at = null;
    public ?\DateTimeImmutable $updated_at = null;
    public ?int $version = null;
    
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'category' => $this->category,
            'file_content' => $this->file_content,
            'assigned_users' => $this->assigned_users,
        ];
        
        if ($this->_id !== null) {
            $data['_id'] = $this->_id;
        }
        
        if ($this->deveui !== null) {
            $data['deveui'] = $this->deveui;
        }
        
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        
        if ($this->diagnostic_for !== null) {
            $data['diagnostic_for'] = $this->diagnostic_for;
        }
        
        if ($this->diagnostic_script !== null) {
            $data['diagnostic_script'] = $this->diagnostic_script;
        }
        
        if ($this->version !== null) {
            $data['version'] = $this->version;
        }
        
        return $data;
    }
    
    public static function collectionName(): string
    {
        return 'meter_config';
    }
    
    public static function fromArray(array $data): static
    {
        $model = new static();
        
        if (isset($data['_id'])) {
            $model->_id = $data['_id'] instanceof ObjectId 
                ? $data['_id'] 
                : new ObjectId($data['_id']);
        }
        
        $model->name = (string) ($data['name'] ?? '');
        $model->category = strtolower((string) ($data['category'] ?? 'general'));
        $model->file_content = (string) ($data['file_content'] ?? $data['content'] ?? '');
        $model->assigned_users = isset($data['assigned_users']) && is_array($data['assigned_users']) 
            ? $data['assigned_users'] 
            : [];
        $model->deveui = isset($data['deveui']) ? strtoupper((string) $data['deveui']) : null;
        $model->description = $data['description'] ?? null;
        $model->diagnostic_for = isset($data['diagnostic_for']) ? strtoupper((string) $data['diagnostic_for']) : null;
        $model->diagnostic_script = $data['diagnostic_script'] ?? null;
        $model->version = isset($data['version']) ? (int) $data['version'] : null;
        
        if (isset($data['created_at'])) {
            $model->created_at = $data['created_at'] instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromFormat('U', (string) $data['created_at']->getTimestamp())
                : new \DateTimeImmutable($data['created_at']);
        }
        
        return $model;
    }
    
    /**
     * Check if config is accessible by user
     */
    public function isAccessibleBy(string $username): bool
    {
        return in_array($username, $this->assigned_users, true) || $this->assigned_users === [];
    }
    
    /**
     * Check if config is for a specific meter
     */
    public function isForMeter(string $deveui): bool
    {
        return $this->deveui !== null && strtoupper($this->deveui) === strtoupper($deveui);
    }
}

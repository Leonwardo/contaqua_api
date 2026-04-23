<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\BSON\ObjectId;

/**
 * Firmware model for OTA updates
 * Matches legacy schema expected by MeterApp
 */
class Firmware implements ModelInterface
{
    public ?ObjectId $_id = null;
    public string $version = '';
    public string $name = '';
    public ?string $description = null;
    public ?string $file_content = null; // Base64 encoded binary
    public ?string $file_path = null; // Alternative to file_content
    public ?string $hw_version = null; // Hardware version compatibility
    public ?string $meter_type = null; // Type of meter
    public int $file_size = 0;
    public ?\DateTimeImmutable $created_at = null;
    public ?\DateTimeImmutable $updated_at = null;
    public bool $is_active = true;
    
    public function toArray(): array
    {
        $data = [
            'version' => $this->version,
            'name' => $this->name,
            'file_size' => $this->file_size,
            'is_active' => $this->is_active,
        ];
        
        if ($this->_id !== null) {
            $data['_id'] = $this->_id;
        }
        
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        
        if ($this->hw_version !== null) {
            $data['hw_version'] = $this->hw_version;
        }
        
        if ($this->meter_type !== null) {
            $data['meter_type'] = $this->meter_type;
        }
        
        if ($this->file_content !== null) {
            $data['file_content'] = $this->file_content;
        }
        
        if ($this->file_path !== null) {
            $data['file_path'] = $this->file_path;
        }
        
        return $data;
    }
    
    public static function collectionName(): string
    {
        return 'firmware';
    }
    
    public static function fromArray(array $data): static
    {
        $model = new static();
        
        if (isset($data['_id'])) {
            $model->_id = $data['_id'] instanceof ObjectId 
                ? $data['_id'] 
                : new ObjectId($data['_id']);
        }
        
        $model->version = (string) ($data['version'] ?? '');
        $model->name = (string) ($data['name'] ?? '');
        $model->description = $data['description'] ?? null;
        $model->file_content = $data['file_content'] ?? null;
        $model->file_path = $data['file_path'] ?? null;
        $model->hw_version = $data['hw_version'] ?? null;
        $model->meter_type = $data['meter_type'] ?? null;
        $model->file_size = (int) ($data['file_size'] ?? 0);
        $model->is_active = (bool) ($data['is_active'] ?? true);
        
        if (isset($data['created_at'])) {
            $model->created_at = $data['created_at'] instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromFormat('U', (string) $data['created_at']->getTimestamp())
                : new \DateTimeImmutable($data['created_at']);
        }
        
        return $model;
    }
    
    /**
     * Get the binary content of the firmware
     */
    public function getBinaryContent(): ?string
    {
        if ($this->file_content !== null) {
            return base64_decode($this->file_content);
        }
        
        if ($this->file_path !== null && file_exists($this->file_path)) {
            return file_get_contents($this->file_path);
        }
        
        return null;
    }
}

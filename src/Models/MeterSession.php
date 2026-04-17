<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\BSON\ObjectId;

class MeterSession implements ModelInterface
{
    public ?ObjectId $_id = null;
    public int $counter = 0;
    public string $sessionkey = '';
    public string $deveui = '';
    public ?string $comment = null;
    public ?\DateTimeImmutable $timestamp = null;
    public ?string $raw_payload = null;
    public ?string $token = null; // User token that submitted the session
    
    public function toArray(): array
    {
        $data = [
            'counter' => $this->counter,
            'sessionkey' => $this->sessionkey,
            'deveui' => $this->deveui,
        ];
        
        if ($this->_id !== null) {
            $data['_id'] = $this->_id;
        }
        
        if ($this->comment !== null && $this->comment !== '') {
            $data['comment'] = $this->comment;
        }
        
        if ($this->timestamp !== null) {
            $data['timestamp'] = new \MongoDB\BSON\UTCDateTime($this->timestamp->getTimestamp() * 1000);
        }
        
        return $data;
    }
    
    public static function collectionName(): string
    {
        return 'meter_session';
    }
    
    public static function fromArray(array $data): static
    {
        $model = new static();
        
        if (isset($data['_id'])) {
            $model->_id = $data['_id'] instanceof ObjectId 
                ? $data['_id'] 
                : new ObjectId($data['_id']);
        }
        
        $model->counter = (int) ($data['counter'] ?? 0);
        $model->sessionkey = strtoupper((string) ($data['sessionkey'] ?? ''));
        $model->deveui = strtoupper(trim((string) ($data['deveui'] ?? '')));
        $model->comment = $data['comment'] ?? null;
        $model->raw_payload = $data['raw_payload'] ?? null;
        $model->token = $data['token'] ?? null;
        
        // Parse timestamp - support various formats
        if (isset($data['timestamp'])) {
            if ($data['timestamp'] instanceof \MongoDB\BSON\UTCDateTime) {
                $model->timestamp = (new \DateTimeImmutable())->setTimestamp($data['timestamp']->toDateTime()->getTimestamp());
            } elseif ($data['timestamp'] instanceof \DateTimeInterface) {
                $model->timestamp = \DateTimeImmutable::createFromFormat('U', (string) $data['timestamp']->getTimestamp());
            } elseif (is_string($data['timestamp'])) {
                // Try to parse as date string
                $model->timestamp = new \DateTimeImmutable($data['timestamp']);
            } elseif (is_int($data['timestamp'])) {
                // Unix timestamp
                $model->timestamp = (new \DateTimeImmutable())->setTimestamp($data['timestamp']);
            }
        } else {
            $model->timestamp = new \DateTimeImmutable();
        }
        
        return $model;
    }
    
    /**
     * Create from Android app payload
     * Supports the format: {counter: int, sessionkey: string, deveui: string, comment?: string}
     */
    public static function fromPayload(array $payload, ?string $userToken = null): static
    {
        $model = new static();
        
        $model->counter = (int) ($payload['counter'] ?? 0);
        $model->sessionkey = strtoupper((string) ($payload['sessionkey'] ?? ''));
        $model->deveui = strtoupper(trim((string) ($payload['deveui'] ?? '')));
        $model->comment = $payload['comment'] ?? null;
        $model->raw_payload = json_encode($payload);
        $model->token = $userToken;
        $model->timestamp = new \DateTimeImmutable();
        
        return $model;
    }
    
    /**
     * Validate the session data
     * @throws \InvalidArgumentException
     */
    public function validate(): void
    {
        if ($this->deveui === '') {
            throw new \InvalidArgumentException('deveui is required');
        }
        
        if ($this->sessionkey === '') {
            throw new \InvalidArgumentException('sessionkey is required');
        }
        
        // Sessionkey should be hex
        if (!ctype_xdigit($this->sessionkey)) {
            throw new \InvalidArgumentException('sessionkey must be hexadecimal');
        }
    }
}

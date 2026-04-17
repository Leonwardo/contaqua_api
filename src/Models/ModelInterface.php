<?php

declare(strict_types=1);

namespace App\Models;

interface ModelInterface
{
    /**
     * Convert model to array for database storage
     */
    public function toArray(): array;
    
    /**
     * Get the collection name for this model
     */
    public static function collectionName(): string;
    
    /**
     * Create instance from database document
     */
    public static function fromArray(array $data): static;
}

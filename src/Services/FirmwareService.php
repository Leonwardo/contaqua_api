<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;
use App\Models\Firmware;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

/**
 * Service for firmware/OTA management
 */
class FirmwareService
{
    public function __construct(
        private MongoCollections $collections,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Get available firmware updates for a device
     * Used by POST /api/firmware endpoint
     * 
     * @return array List of available firmwares
     */
    public function getAvailableFirmware(?string $hwVersion = null, ?string $meterType = null): array
    {
        try {
            $filter = ['is_active' => true];
            
            // Add hardware version filter if provided
            if ($hwVersion !== null && $hwVersion !== '') {
                $filter['$or'] = [
                    ['hw_version' => null],
                    ['hw_version' => $hwVersion],
                ];
            }
            
            // Add meter type filter if provided
            if ($meterType !== null && $meterType !== '') {
                if (!isset($filter['$or'])) {
                    $filter['$or'] = [
                        ['meter_type' => null],
                        ['meter_type' => $meterType],
                    ];
                }
            }
            
            $cursor = $this->collections->firmware()->find(
                $filter,
                ['sort' => ['created_at' => -1]]
            );
            
            $firmwares = [];
            foreach ($cursor as $document) {
                $firmwares[] = Firmware::fromArray($document);
            }
            
            return $firmwares;
        } catch (\Exception $e) {
            $this->logger->error('Error getting firmware list', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get firmware by ID
     */
    public function getById(string $id): ?Firmware
    {
        try {
            $objectId = new ObjectId($id);
            $document = $this->collections->firmware()->findOne(['_id' => $objectId]);
            return $document ? Firmware::fromArray($document) : null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting firmware by id', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get firmware binary content by ID
     */
    public function getFirmwareBinary(string $id): ?string
    {
        $firmware = $this->getById($id);
        
        if ($firmware === null) {
            return null;
        }
        
        return $firmware->getBinaryContent();
    }
    
    /**
     * Create new firmware entry
     */
    public function createFirmware(
        string $version,
        string $name,
        string $binaryContent,
        ?string $description = null,
        ?string $hwVersion = null,
        ?string $meterType = null
    ): Firmware {
        $firmware = new Firmware();
        $firmware->version = $version;
        $firmware->name = $name;
        $firmware->file_content = base64_encode($binaryContent);
        $firmware->file_size = strlen($binaryContent);
        $firmware->description = $description;
        $firmware->hw_version = $hwVersion;
        $firmware->meter_type = $meterType;
        $firmware->created_at = new \DateTimeImmutable();
        $firmware->is_active = true;
        
        $result = $this->collections->firmware()->insertOne($firmware->toArray());
        $firmware->_id = $result->getInsertedId();
        
        $this->logger->info('Firmware created', ['version' => $version, 'name' => $name]);
        
        return $firmware;
    }
    
    /**
     * Get all firmwares for admin
     * @return Firmware[]
     */
    public function getAllFirmwares(?int $limit = null): array
    {
        try {
            $options = ['sort' => ['created_at' => -1]];
            if ($limit !== null) {
                $options['limit'] = $limit;
            }
            
            $cursor = $this->collections->firmware()->find([], $options);
            $firmwares = [];
            
            foreach ($cursor as $document) {
                $firmwares[] = Firmware::fromArray($document);
            }
            
            return $firmwares;
        } catch (\Exception $e) {
            $this->logger->error('Error getting all firmwares', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Delete firmware
     */
    public function deleteFirmware(string $id): void
    {
        try {
            $objectId = new ObjectId($id);
            $result = $this->collections->firmware()->deleteOne(['_id' => $objectId]);
            
            if ($result->getDeletedCount() === 0) {
                throw new \InvalidArgumentException("Firmware '{$id}' not found");
            }
            
            $this->logger->info('Firmware deleted', ['id' => $id]);
        } catch (\Exception $e) {
            $this->logger->error('Error deleting firmware', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Count total firmware entries
     */
    public function count(): int
    {
        return $this->collections->firmware()->countDocuments();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;
use App\Models\MeterConfig;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class MeterConfigService
{
    public function __construct(
        private MongoCollections $collections,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Get configs accessible by user for a specific meter
     * @return MeterConfig[]
     */
    public function getAllowedConfigs(string $username, string $deveui): array
    {
        $deveui = strtoupper(trim($deveui));
        
        try {
            $cursor = $this->collections->meterConfig()->find([
                '$or' => [
                    ['assigned_users' => $username],
                    ['assigned_users' => ['$size' => 0]],
                    ['assigned_users' => ['$exists' => false]],
                ],
            ]);
            
            $configs = [];
            foreach ($cursor as $document) {
                $config = MeterConfig::fromArray($document);
                // Include if no specific deveui or matches
                if ($config->deveui === null || $config->isForMeter($deveui)) {
                    $configs[] = $config;
                }
            }
            
            return $configs;
        } catch (\Exception $e) {
            $this->logger->error('Error getting configs', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get all configs for admin
     * @return MeterConfig[]
     */
    public function getAllConfigs(?string $category = null, ?int $limit = null): array
    {
        try {
            $filter = [];
            if ($category !== null) {
                $filter['category'] = strtolower($category);
            }
            
            $options = ['sort' => ['name' => 1]];
            if ($limit !== null) {
                $options['limit'] = $limit;
            }
            
            $cursor = $this->collections->meterConfig()->find($filter, $options);
            $configs = [];
            
            foreach ($cursor as $document) {
                $configs[] = MeterConfig::fromArray($document);
            }
            
            return $configs;
        } catch (\Exception $e) {
            $this->logger->error('Error getting all configs', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get config by ID
     */
    public function getById(string $id): ?MeterConfig
    {
        try {
            $objectId = new ObjectId($id);
            $document = $this->collections->meterConfig()->findOne(['_id' => $objectId]);
            return $document ? MeterConfig::fromArray($document) : null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting config by id', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get diagnostic script for meter
     */
    public function getDiagnosticScript(string $deveui): ?string
    {
        $deveui = strtoupper(trim($deveui));
        
        try {
            $document = $this->collections->meterConfig()->findOne(['diagnostic_for' => $deveui]);
            return $document ? ($document['diagnostic_script'] ?? null) : null;
        } catch (\Exception $e) {
            $this->logger->error('Error getting diagnostic script', ['deveui' => $deveui, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Create new config
     */
    public function createConfig(
        string $name,
        string $content,
        string $category = 'general',
        array $assignedUsers = [],
        ?string $deveui = null,
        ?string $description = null
    ): MeterConfig {
        $config = new MeterConfig();
        $config->name = $name;
        $config->file_content = $content;
        $config->category = strtolower($category);
        $config->assigned_users = $assignedUsers;
        $config->deveui = $deveui ? strtoupper($deveui) : null;
        $config->description = $description;
        $config->created_at = new \DateTimeImmutable();
        
        $result = $this->collections->meterConfig()->insertOne($config->toArray());
        $config->_id = $result->getInsertedId();
        
        $this->logger->info('Config created', ['name' => $name]);
        
        return $config;
    }
    
    /**
     * Update config
     */
    public function updateConfig(string $id, array $updates): void
    {
        try {
            $objectId = new ObjectId($id);
            $updates['updated_at'] = new \DateTimeImmutable();
            
            $result = $this->collections->meterConfig()->updateOne(
                ['_id' => $objectId],
                ['$set' => $updates]
            );
            
            if ($result->getModifiedCount() === 0) {
                throw new \InvalidArgumentException("Config '{$id}' not found or no changes made");
            }
            
            $this->logger->info('Config updated', ['id' => $id]);
        } catch (\Exception $e) {
            $this->logger->error('Error updating config', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Delete config
     */
    public function deleteConfig(string $id): void
    {
        try {
            $objectId = new ObjectId($id);
            $result = $this->collections->meterConfig()->deleteOne(['_id' => $objectId]);
            
            if ($result->getDeletedCount() === 0) {
                throw new \InvalidArgumentException("Config '{$id}' not found");
            }
            
            $this->logger->info('Config deleted', ['id' => $id]);
        } catch (\Exception $e) {
            $this->logger->error('Error deleting config', ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Count configs
     */
    public function count(): int
    {
        return $this->collections->meterConfig()->countDocuments();
    }
}

<?php

declare(strict_types=1);

namespace App\Database;

use MongoDB\Client;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class MongoConnection
{
    private ?Client $client = null;
    private ?Database $database = null;
    
    public function __construct(
        private array $config,
        private LoggerInterface $logger
    ) {
    }
    
    public function getClient(): Client
    {
        if ($this->client === null) {
            try {
                $this->client = new Client(
                    $this->config['uri'],
                    [],
                    $this->config['options'] ?? []
                );
                $this->logger->info('MongoDB client initialized successfully');
            } catch (\Exception $e) {
                $this->logger->error('Failed to initialize MongoDB client', [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
        
        return $this->client;
    }
    
    public function getDatabase(): Database
    {
        if ($this->database === null) {
            $this->database = $this->getClient()->selectDatabase($this->config['database']);
            $this->logger->debug('Database selected', ['database' => $this->config['database']]);
        }
        
        return $this->database;
    }
    
    /**
     * Get a specific collection
     */
    public function collection(string $name): \MongoDB\Collection
    {
        return $this->getDatabase()->selectCollection($name);
    }
    
    /**
     * Health check - ping the database
     */
    public function ping(): bool
    {
        try {
            $this->getDatabase()->command(['ping' => 1]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('MongoDB ping failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

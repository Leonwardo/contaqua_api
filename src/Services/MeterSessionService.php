<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\MongoCollections;
use App\Models\MeterSession;
use Psr\Log\LoggerInterface;

class MeterSessionService
{
    public function __construct(
        private MongoCollections $collections,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Store a new session from payload
     */
    public function storeSession(array $payload, ?string $userToken = null): array
    {
        $session = MeterSession::fromPayload($payload, $userToken);
        
        try {
            $session->validate();
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Session validation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
        
        // Check for duplicate (same deveui, counter, sessionkey)
        $existing = $this->collections->meterSession()->findOne([
            'deveui' => $session->deveui,
            'counter' => $session->counter,
            'sessionkey' => $session->sessionkey,
        ]);
        
        if ($existing !== null) {
            $this->logger->info('Duplicate session detected, skipping', [
                'deveui' => $session->deveui,
                'counter' => $session->counter,
            ]);
            return [
                'id' => (string) $existing['_id'],
                'deveui' => $session->deveui,
                'counter' => $session->counter,
                'duplicate' => true,
            ];
        }
        
        try {
            $result = $this->collections->meterSession()->insertOne($session->toArray());
            $sessionId = (string) $result->getInsertedId();
            
            $this->logger->info('Session stored', [
                'id' => $sessionId,
                'deveui' => $session->deveui,
                'counter' => $session->counter,
            ]);
            
            return [
                'id' => $sessionId,
                'deveui' => $session->deveui,
                'counter' => $session->counter,
                'sessionkey' => $session->sessionkey,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error storing session', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to store session: ' . $e->getMessage());
        }
    }
    
    /**
     * Get sessions for a meter
     * @return MeterSession[]
     */
    public function getSessionsByMeter(string $deveui, ?int $limit = 100): array
    {
        $deveui = strtoupper(trim($deveui));
        
        try {
            $cursor = $this->collections->meterSession()->find(
                ['deveui' => $deveui],
                [
                    'sort' => ['timestamp' => -1],
                    'limit' => $limit,
                ]
            );
            
            $sessions = [];
            foreach ($cursor as $document) {
                $sessions[] = MeterSession::fromArray($document);
            }
            
            return $sessions;
        } catch (\Exception $e) {
            $this->logger->error('Error getting sessions', ['deveui' => $deveui, 'error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get latest sessions across all meters
     * @return MeterSession[]
     */
    public function getLatestSessions(int $limit = 50): array
    {
        try {
            $cursor = $this->collections->meterSession()->find(
                [],
                [
                    'sort' => ['timestamp' => -1],
                    'limit' => $limit,
                ]
            );
            
            $sessions = [];
            foreach ($cursor as $document) {
                $sessions[] = MeterSession::fromArray($document);
            }
            
            return $sessions;
        } catch (\Exception $e) {
            $this->logger->error('Error getting latest sessions', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get sessions with optional filters
     * @return MeterSession[]
     */
    public function getSessions(array $filters = [], ?int $limit = 100): array
    {
        try {
            $query = [];
            
            if (isset($filters['deveui'])) {
                $query['deveui'] = strtoupper(trim($filters['deveui']));
            }
            
            if (isset($filters['from'])) {
                $query['timestamp']['$gte'] = new \MongoDB\BSON\UTCDateTime(
                    (new \DateTimeImmutable($filters['from']))->getTimestamp() * 1000
                );
            }
            
            if (isset($filters['to'])) {
                $query['timestamp']['$lte'] = new \MongoDB\BSON\UTCDateTime(
                    (new \DateTimeImmutable($filters['to']))->getTimestamp() * 1000
                );
            }
            
            $cursor = $this->collections->meterSession()->find(
                $query,
                [
                    'sort' => ['timestamp' => -1],
                    'limit' => $limit,
                ]
            );
            
            $sessions = [];
            foreach ($cursor as $document) {
                $sessions[] = MeterSession::fromArray($document);
            }
            
            return $sessions;
        } catch (\Exception $e) {
            $this->logger->error('Error getting sessions', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Count total sessions
     */
    public function count(): int
    {
        return $this->collections->meterSession()->countDocuments();
    }
    
    /**
     * Count sessions for a specific meter
     */
    public function countByMeter(string $deveui): int
    {
        return $this->collections->meterSession()->countDocuments([
            'deveui' => strtoupper(trim($deveui)),
        ]);
    }
    
    /**
     * Delete old sessions
     */
    public function deleteOldSessions(int $daysOld): int
    {
        $cutoff = (new \DateTimeImmutable())->modify("-{$daysOld} days");
        
        try {
            $result = $this->collections->meterSession()->deleteMany([
                'timestamp' => [
                    '$lt' => new \MongoDB\BSON\UTCDateTime($cutoff->getTimestamp() * 1000),
                ],
            ]);
            
            $deleted = $result->getDeletedCount();
            $this->logger->info('Old sessions deleted', ['days' => $daysOld, 'deleted' => $deleted]);
            
            return $deleted;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting old sessions', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Database;

use App\Services\Logger;
use MongoDB\Client;
use MongoDB\Database;

final class MongoConnection
{
    private ?Client $client = null;

    public function __construct(
        private string $uri,
        private string $databaseName,
        private Logger $logger
    ) {
    }

    public function database(): Database
    {
        if ($this->client === null) {
            $this->client = new Client($this->uri, [], [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ],
            ]);
            $this->logger->info('MongoDB client initialized');
        }

        return $this->client->selectDatabase($this->databaseName);
    }
}

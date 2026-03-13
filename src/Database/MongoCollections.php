<?php

declare(strict_types=1);

namespace App\Database;

use MongoDB\Collection;

final class MongoCollections
{
    public function __construct(private MongoConnection $connection)
    {
    }

    public function userAuth(): Collection
    {
        return $this->connection->database()->selectCollection('user_auth');
    }

    public function meterAuth(): Collection
    {
        return $this->connection->database()->selectCollection('meter_auth');
    }

    public function meterConfig(): Collection
    {
        return $this->connection->database()->selectCollection('meter_config');
    }

    public function meterSession(): Collection
    {
        return $this->connection->database()->selectCollection('meter_session');
    }
}

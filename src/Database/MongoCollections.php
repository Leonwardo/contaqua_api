<?php

declare(strict_types=1);

namespace App\Database;

use MongoDB\Collection;

/**
 * Centralized collection access for MongoDB
 * Ensures consistent collection names across the application
 */
class MongoCollections
{
    public function __construct(private MongoConnection $connection)
    {
    }
    
    /**
     * User authentication collection
     * Fields: _id, access, user_id, user, pass, salt
     */
    public function userAuth(): Collection
    {
        return $this->connection->collection('user_auth');
    }
    
    /**
     * Meter authentication collection
     * Fields: _id, deveui, authkeys, valid_until, assigned_users
     */
    public function meterAuth(): Collection
    {
        return $this->connection->collection('meter_auth');
    }
    
    /**
     * Meter configuration collection
     * Fields: _id, name, category, file_content, assigned_users, deveui
     */
    public function meterConfig(): Collection
    {
        return $this->connection->collection('meter_config');
    }
    
    /**
     * Meter session collection
     * Fields: _id, counter, sessionkey, deveui, timestamp, comment
     */
    public function meterSession(): Collection
    {
        return $this->connection->collection('meter_session');
    }
    
    /**
     * Firmware collection (for OTA updates)
     */
    public function firmware(): Collection
    {
        return $this->connection->collection('firmware');
    }
    
    /**
     * Android app updates collection
     */
    public function appUpdates(): Collection
    {
        return $this->connection->collection('app_updates');
    }
    
    /**
     * Admin sessions collection
     */
    public function adminSessions(): Collection
    {
        return $this->connection->collection('admin_sessions');
    }
    
    /**
     * System logs collection
     */
    public function logs(): Collection
    {
        return $this->connection->collection('system_logs');
    }
    
    /**
     * Get any collection by name
     */
    public function get(string $name): Collection
    {
        return $this->connection->collection($name);
    }
}

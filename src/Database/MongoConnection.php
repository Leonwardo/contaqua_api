<?php
declare(strict_types=1);

namespace App\Database;

use MongoDB\Database;

/**
 * Wrapper para compatibilidade com código existente
 * Usa o novo MongoManager internamente
 */
final class MongoConnection
{
    private MongoManager $manager;

    public function __construct(
        private string $uri,
        private string $databaseName,
        private ?\App\Services\Logger $logger = null
    ) {
        $this->manager = MongoManager::getInstance();
    }

    public function database(): Database
    {
        return $this->manager->getDatabase();
    }

    /**
     * Obtém o MongoManager para acesso direto
     */
    public function getManager(): MongoManager
    {
        return $this->manager;
    }
}

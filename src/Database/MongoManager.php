<?php
declare(strict_types=1);

namespace App\Database;

use App\Config\AppConfig;
use MongoDB\Client;
use MongoDB\Database;
use Exception;
use Throwable;

/**
 * MongoDB Connection Manager - Singleton Pattern
 * Gerencia a conexão MongoDB para toda a aplicação
 */
final class MongoManager
{
    private static ?self $instance = null;
    private ?Client $client = null;
    private ?Database $database = null;
    private string $uri;
    private string $databaseName;
    private array $logs = [];
    private int $maxRetries = 3;
    private int $retryDelay = 1; // seconds

    private function __construct()
    {
        $this->uri = AppConfig::mongoUri();
        $this->databaseName = AppConfig::mongoDatabase();
        $this->log('MongoManager inicializado');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtém a instância do Client MongoDB com retry logic
     */
    public function getClient(): Client
    {
        if ($this->client === null) {
            $this->connectWithRetry();
        }
        return $this->client;
    }

    /**
     * Obtém a instância da Database
     */
    public function getDatabase(): Database
    {
        if ($this->database === null) {
            $this->database = $this->getClient()->selectDatabase($this->databaseName);
            $this->log("Database '{$this->databaseName}' selecionada");
        }
        return $this->database;
    }

    /**
     * Tenta conectar com retry logic
     */
    private function connectWithRetry(): void
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $this->log("Tentativa de conexão #{$attempt}...");
                
                $this->client = new Client($this->uri, [
                    'connectTimeoutMS' => 10000,
                    'socketTimeoutMS' => 30000,
                    'serverSelectionTimeoutMS' => 10000,
                ], [
                    'typeMap' => [
                        'root' => 'array',
                        'document' => 'array',
                        'array' => 'array',
                    ],
                ]);
                
                // Testa a conexão
                $this->client->admin->command(['ping' => 1]);
                
                $this->log("Conexão MongoDB estabelecida com sucesso!");
                return;
                
            } catch (Throwable $e) {
                $lastException = $e;
                $this->log("Erro na tentativa #{$attempt}: " . $e->getMessage());
                
                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelay);
                }
            }
        }
        
        throw new Exception(
            "Falha ao conectar ao MongoDB após {$this->maxRetries} tentativas: " . $lastException->getMessage()
        );
    }

    /**
     * Obtém uma coleção específica
     */
    public function getCollection(string $name): \MongoDB\Collection
    {
        return $this->getDatabase()->selectCollection($name);
    }

    /**
     * Testa a conexão MongoDB
     */
    public function testConnection(): array
    {
        try {
            $client = $this->getClient();
            $client->admin->command(['ping' => 1]);
            
            $db = $this->getDatabase();
            $collections = iterator_to_array($db->listCollections());
            
            return [
                'success' => true,
                'message' => 'Conexão MongoDB OK',
                'database' => $this->databaseName,
                'collections' => array_map(fn($c) => $c->getName(), $collections),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }

    /**
     * Logging interno
     */
    private function log(string $message): void
    {
        $timestamp = date('[Y-m-d H:i:s]');
        $logEntry = "$timestamp [MongoManager] $message";
        $this->logs[] = $logEntry;
        
        // Também escreve em ficheiro se possível
        $this->writeToFile($logEntry);
    }

    private function writeToFile(string $message): void
    {
        try {
            $logDir = __DIR__ . '/../../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/mongo_connection.log';
            @file_put_contents($logFile, $message . "\n", FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Ignora erros de escrita
        }
    }

    /**
     * Obtém os logs
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Força reconexão
     */
    public function reconnect(): void
    {
        $this->client = null;
        $this->database = null;
        $this->log("Reconexão forçada");
        $this->connectWithRetry();
    }
}

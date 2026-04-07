<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

try {
    echo "Loading autoloader...\n";
    require_once __DIR__ . '/vendor/autoload.php';
    echo "OK\n\n";
    
    echo "Loading Env...\n";
    require_once __DIR__ . '/src/Config/Env.php';
    \App\Config\Env::load(__DIR__);
    echo "OK\n\n";
    
    echo "Creating MongoConnection...\n";
    $mongoUri = \App\Config\AppConfig::mongoUri();
    $mongoDb = \App\Config\AppConfig::mongoDatabase();
    echo "URI: " . substr($mongoUri, 0, 30) . "...\n";
    echo "DB: $mongoDb\n";
    $connection = new \App\Database\MongoConnection($mongoUri, $mongoDb, null);
    echo "OK\n\n";
    
    echo "Creating MongoCollections...\n";
    $collections = new \App\Database\MongoCollections($connection);
    echo "OK\n\n";
    
    echo "Creating AdminService...\n";
    $adminService = new \App\Services\AdminService($collections);
    echo "OK\n\n";
    
    echo "Testing listUsers...\n";
    $users = $adminService->listUsers();
    echo "Users found: " . count($users) . "\n\n";
    
    echo "Creating AdminController...\n";
    $adminToken = \App\Config\AppConfig::adminToken();
    echo "Token: " . substr($adminToken, 0, 20) . "...\n";
    $adminController = new \App\Controllers\AdminController($adminService, $adminToken);
    echo "OK\n\n";
    
    echo "=== ALL TESTS PASSED ===\n";
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

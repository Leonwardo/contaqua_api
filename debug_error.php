<?php
// Capture all errors and display them
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "1. Autoload OK\n";
    
    require_once __DIR__ . '/src/Config/Env.php';
    echo "2. Env file loaded\n";
    
    use App\Config\Env;
    Env::load(__DIR__);
    echo "3. Environment variables loaded\n";
    
    // Test AdminController initialization
    echo "4. Loading AdminController dependencies...\n";
    
    use App\Database\MongoConnection;
    use App\Database\MongoCollections;
    use App\Services\AdminService;
    use App\Config\AppConfig;
    
    echo "5. Creating MongoConnection...\n";
    $mongoUri = AppConfig::mongoUri();
    $mongoDb = AppConfig::mongoDatabase();
    echo "   URI: " . substr($mongoUri, 0, 30) . "...\n";
    echo "   DB: $mongoDb\n";
    
    $connection = new MongoConnection($mongoUri, $mongoDb, null);
    echo "6. MongoConnection created\n";
    
    $collections = new MongoCollections($connection);
    echo "7. MongoCollections created\n";
    
    $adminService = new AdminService($collections);
    echo "8. AdminService created\n";
    
    $adminToken = AppConfig::adminToken();
    echo "9. AdminToken: " . substr($adminToken, 0, 20) . "...\n";
    
    use App\Controllers\AdminController;
    $adminController = new AdminController($adminService, $adminToken);
    echo "10. AdminController created successfully!\n";
    
    // Try to list users
    echo "\n11. Testing listUsers...\n";
    $users = $adminService->listUsers();
    echo "    Users found: " . count($users) . "\n";
    
    echo "\n=== ALL TESTS PASSED ===\n";
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

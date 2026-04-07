<?php
// Debug script for admin panel errors
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Create logs directory if needed
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Custom error handler
set_error_handler(function($severity, $message, $file, $line) {
    $error = sprintf("Error [%d]: %s in %s:%d", $severity, $message, $file, $line);
    file_put_contents(__DIR__ . '/logs/debug_admin.log', date('[Y-m-d H:i:s] ') . $error . "\n", FILE_APPEND);
    echo $error . "\n";
    return true;
});

set_exception_handler(function($e) {
    $error = sprintf("Exception: %s\n%s", $e->getMessage(), $e->getTraceAsString());
    file_put_contents(__DIR__ . '/logs/debug_admin.log', date('[Y-m-d H:i:s] ') . $error . "\n", FILE_APPEND);
    echo "<pre>" . htmlspecialchars($error) . "</pre>";
});

echo "Starting debug...\n\n";

try {
    echo "1. Loading autoloader...\n";
    require_once __DIR__ . '/vendor/autoload.php';
    echo "   OK\n";
    
    echo "2. Loading Env...\n";
    require_once __DIR__ . '/src/Config/Env.php';
    echo "   OK\n";
    
    echo "3. Loading AppConfig...\n";
    require_once __DIR__ . '/src/Config/AppConfig.php';
    echo "   OK\n";
    
    echo "4. Initializing Environment...\n";
    App\Config\Env::load(__DIR__);
    echo "   OK\n";
    
    echo "5. Loading required classes...\n";
    require_once __DIR__ . '/src/Database/MongoConnection.php';
    require_once __DIR__ . '/src/Database/MongoCollections.php';
    require_once __DIR__ . '/src/Services/Logger.php';
    require_once __DIR__ . '/src/Services/AdminService.php';
    require_once __DIR__ . '/src/Admin/AdminView.php';
    require_once __DIR__ . '/src/Http/Request.php';
    require_once __DIR__ . '/src/Http/Response.php';
    require_once __DIR__ . '/src/Controllers/AdminController.php';
    echo "   OK\n";
    
    echo "6. Creating services...\n";
    $basePath = __DIR__;
    $logger = new App\Services\Logger(App\Config\AppConfig::logPath($basePath));
    $mongoUri = App\Config\AppConfig::mongoUri();
    $mongoDb = App\Config\AppConfig::mongoDatabase();
    echo "   Mongo URI: " . substr($mongoUri, 0, 20) . "...\n";
    echo "   Mongo DB: $mongoDb\n";
    $mongoConnection = new App\Database\MongoConnection($mongoUri, $mongoDb, $logger);
    $collections = new App\Database\MongoCollections($mongoConnection);
    $adminService = new App\Services\AdminService($collections);
    echo "   OK\n";
    
    echo "7. Creating AdminController...\n";
    $adminToken = $_ENV['ADMIN_TOKEN'] ?? 'ContaquaAdminSecure2026';
    $adminController = new App\Controllers\AdminController($adminService, $adminToken);
    echo "   OK\n";
    
    echo "8. Creating Request...\n";
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin?admin_token=' . $adminToken;
    $_GET['admin_token'] = $adminToken;
    $request = App\Http\Request::fromGlobals();
    echo "   OK\n";
    
    echo "9. Calling dashboard...\n";
    $response = $adminController->dashboard($request);
    echo "   OK - Response status: " . $response->getStatusCode() . "\n";
    
    echo "\n=== ALL TESTS PASSED ===\n";
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

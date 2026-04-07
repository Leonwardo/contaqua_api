<?php
// Test MongoDB operations with detailed error logging
header('Content-Type: text/plain');

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Config/Env.php';
use App\Config\Env;

Env::load(__DIR__);

$mongoUri = $_ENV['MONGO_URI'] ?? getenv('MONGO_URI');
$mongoDb = $_ENV['MONGO_DB_NAME'] ?? getenv('MONGO_DB_NAME');

echo "URI set: " . ($mongoUri ? 'YES' : 'NO') . "\n";
echo "DB set: " . ($mongoDb ? 'YES' : 'NO') . "\n\n";

try {
    echo "Creating client...\n";
    $client = new MongoDB\Client($mongoUri, ['connectTimeoutMS' => 10000]);
    echo "✓ Client created\n";
    
    echo "Selecting database '$mongoDb'...\n";
    $db = $client->selectDatabase($mongoDb);
    echo "✓ Database selected\n";
    
    echo "Testing insert...\n";
    $result = $db->user_auth->insertOne([
        'test' => true,
        'time' => time(),
        'user' => 'test_' . time()
    ]);
    echo "✓ Insert successful! ID: " . $result->getInsertedId() . "\n";
    
    // Cleanup
    $db->user_auth->deleteOne(['test' => true]);
    echo "✓ Cleanup done\n";
    
} catch (\Throwable $e) {
    echo "\n✗ ERROR: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

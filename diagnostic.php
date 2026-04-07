<?php
// Diagnostic script for MongoDB issues
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 MongoDB Diagnostic</h1>";

// 1. Check PHP MongoDB extension
echo "<h2>1. PHP MongoDB Extension</h2>";
if (extension_loaded('mongodb')) {
    echo "<p style='color:green'>✓ MongoDB extension loaded</p>";
    echo "<p>Version: " . (phpversion('mongodb') ?: 'unknown') . "</p>";
} else {
    echo "<p style='color:red'>✗ MongoDB extension NOT loaded</p>";
}

// 2. Check environment variables
echo "<h2>2. Environment Variables</h2>";
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Config/Env.php';
use App\Config\Env;
Env::load(__DIR__);

$mongoUri = $_ENV['MONGO_URI'] ?? getenv('MONGO_URI') ?: 'NOT SET';
$mongoDb = $_ENV['MONGO_DB_NAME'] ?? getenv('MONGO_DB_NAME') ?: 'NOT SET';

echo "<p>MONGO_URI: " . (strpos($mongoUri, '://') !== false ? '✓ Set (hidden for security)' : '✗ NOT SET') . "</p>";
echo "<p>MONGO_DB_NAME: " . ($mongoDb !== 'NOT SET' ? "✓ $mongoDb" : '✗ NOT SET') . "</p>";

// 3. Test MongoDB Connection
echo "<h2>3. MongoDB Connection Test</h2>";
echo "<p>Connecting to MongoDB...</p>";
flush();

try {
    // Set socket timeout
    $uriOptions = ['connectTimeoutMS' => 5000, 'socketTimeoutMS' => 5000];
    $client = new MongoDB\Client($mongoUri, $uriOptions);
    
    echo "<p style='color:green'>✓ Client created</p>";
    flush();
    
    $db = $client->selectDatabase($mongoDb);
    echo "<p style='color:green'>✓ Database selected: $mongoDb</p>";
    flush();
    
    // Test write with shorter timeout
    $testCollection = $db->selectCollection('test_diagnostic');
    echo "<p>Testing insert...</p>";
    flush();
    
    $insertResult = $testCollection->insertOne(['test' => true, 'time' => time(), 'diagnostic' => true]);
    echo "<p style='color:green'>✓ Insert successful! ID: " . $insertResult->getInsertedId() . "</p>";
    flush();
    
    // Test read
    $doc = $testCollection->findOne(['test' => true]);
    echo "<p style='color:green'>✓ Read successful! Found: " . ($doc ? 'yes' : 'no') . "</p>";
    
    // Cleanup
    $testCollection->deleteOne(['test' => true]);
    echo "<p style='color:green'>✓ Delete successful!</p>";
    
    // List collections (with limit)
    echo "<h3>Collections:</h3><ul>";
    $collections = $db->listCollections();
    $count = 0;
    foreach ($collections as $coll) {
        if ($count++ > 10) break;
        $name = $coll->getName();
        try {
            $docCount = $db->selectCollection($name)->countDocuments([], ['limit' => 1000]);
            echo "<li>$name: $docCount documents</li>";
        } catch (Exception $ce) {
            echo "<li>$name: error counting</li>";
        }
    }
    echo "</ul>";
    
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    echo "<p style='color:red'>✗ Connection Timeout: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Possíveis causas:</p>";
    echo "<ul>";
    echo "<li>IP não está na whitelist do MongoDB Atlas</li>";
    echo "<li>Firewall bloqueando conexão</li>";
    echo "<li>URI incorreto</li>";
    echo "</ul>";
} catch (MongoDB\Driver\Exception\AuthenticationException $e) {
    echo "<p style='color:red'>✗ Authentication Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Verifique usuário/senha na URI do MongoDB</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// 4. Check PHP error logs
echo "<h2>4. Recent PHP Errors</h2>";
$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile) && is_readable($logFile)) {
    $logs = file_get_contents($logFile);
    $lines = explode("\n", $logs);
    $recent = array_slice(array_filter($lines), -50);
    echo "<pre style='background:#f4f4f4;padding:10px;max-height:300px;overflow:auto;'>";
    echo htmlspecialchars(implode("\n", $recent));
    echo "</pre>";
} else {
    echo "<p>Log file not accessible: $logFile</p>";
}

// 5. Check application logs
echo "<h2>5. Application Logs</h2>";
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir)) {
    $files = glob($logsDir . '/*.log');
    if (empty($files)) {
        echo "<p>No log files found in logs/</p>";
    } else {
        foreach ($files as $file) {
            echo "<h3>" . basename($file) . "</h3>";
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $recent = array_slice(array_filter($lines), -30);
            echo "<pre style='background:#f4f4f4;padding:10px;max-height:200px;overflow:auto;'>";
            echo htmlspecialchars(implode("\n", $recent));
            echo "</pre>";
        }
    }
} else {
    echo "<p>Logs directory does not exist</p>";
}

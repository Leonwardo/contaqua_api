<?php
/**
 * Simple test endpoint for MeterApp login troubleshooting
 * Access via: http://<server>/test_login.php
 */

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/plain');

echo "=== Contaqua API Login Test ===\n\n";

// 1. Check MongoDB connection
echo "1. MongoDB Connection:\n";
try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
    
    $mongoUri = $_ENV['MONGO_URI'] ?? '';
    $mongoDb = $_ENV['MONGO_DATABASE'] ?? 'water_meter';
    
    if ($mongoUri === '') {
        echo "   ERROR: MONGO_URI not set in .env\n";
    } else {
        echo "   URI: " . substr($mongoUri, 0, 30) . "...\n";
        echo "   Database: {$mongoDb}\n";
        
        $client = new MongoDB\Client($mongoUri);
        $db = $client->selectDatabase($mongoDb);
        $collections = $db->listCollectionNames();
        echo "   Connected! Collections: " . implode(', ', iterator_to_array($collections)) . "\n";
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n2. Request Simulation:\n";

// Simulate a login request
$testUser = $_GET['user'] ?? 'test';
$testPass = $_GET['pass'] ?? 'test';

// Check if user exists
try {
    $client = new MongoDB\Client($_ENV['MONGO_URI'] ?? '');
    $db = $client->selectDatabase($_ENV['MONGO_DATABASE'] ?? 'water_meter');
    $collection = $db->selectCollection('user_auth');
    
    $user = $collection->findOne(['user' => $testUser]);
    
    if ($user) {
        echo "   User '{$testUser}' found in DB\n";
        echo "   Access level: " . ($user['access'] ?? 'N/A') . "\n";
        echo "   User ID: " . ($user['user_id'] ?? 'N/A') . "\n";
        echo "   Has password hash: " . (isset($user['pass']) ? 'Yes' : 'No') . "\n";
        echo "   Has salt: " . (isset($user['salt']) ? 'Yes' : 'No') . "\n";
    } else {
        echo "   User '{$testUser}' NOT found in DB\n";
        echo "   Available users:\n";
        foreach ($collection->find([], ['limit' => 5]) as $u) {
            echo "      - " . ($u['user'] ?? 'unknown') . "\n";
        }
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n3. Test with credentials (use ?user=xxx&pass=yyy):\n";
echo "   Current: user={$testUser}, pass=" . str_repeat('*', strlen($testPass)) . "\n";

// Simulate login logic
try {
    $client = new MongoDB\Client($_ENV['MONGO_URI'] ?? '');
    $db = $client->selectDatabase($_ENV['MONGO_DATABASE'] ?? 'water_meter');
    $collection = $db->selectCollection('user_auth');
    
    $doc = $collection->findOne(['user' => $testUser]);
    
    if ($doc && isset($doc['salt']) && isset($doc['pass'])) {
        $hash = hash('sha256', $doc['salt'] . ':' . $testPass);
        if (hash_equals($doc['pass'], $hash)) {
            echo "   PASSWORD MATCH!\n";
            // Build token
            $access = (int) ($doc['access'] ?? 0);
            $userId = (int) ($doc['user_id'] ?? 0);
            $tokenBytes = array_fill(0, 9, 0);
            $tokenBytes[0] = max(0, $access - 1) & 0xFF;
            $userIdString = (string) max(0, $userId);
            for ($i = 0; $i < min(3, strlen($userIdString)); $i++) {
                $tokenBytes[$i + 1] = (int) $userIdString[$i];
            }
            $token = strtoupper(bin2hex(pack('C*', ...$tokenBytes)));
            echo "   Generated token: {$token}\n";
        } else {
            echo "   PASSWORD MISMATCH\n";
            echo "   Expected: " . substr($doc['pass'], 0, 20) . "...\n";
            echo "   Got:      " . substr($hash, 0, 20) . "...\n";
        }
    } else {
        echo "   Cannot verify (missing salt/pass or user not found)\n";
    }
} catch (Throwable $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n4. Sample POST test:\n";
echo "   curl -X POST http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/api/user_token \\\n";
echo "        -H \"Content-Type: application/x-www-form-urlencoded\" \\\n";
echo "        -d \"user={$testUser}&pass=YOUR_PASSWORD\"\n";

echo "\n=== End of Test ===\n";

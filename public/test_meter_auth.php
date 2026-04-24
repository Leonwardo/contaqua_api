<?php
/**
 * Test meter authentication and token generation
 * Access via: http://<server>/test_meter_auth.php?deveui=XXX&user=YYY&pass=ZZZ
 */

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/plain');

echo "=== Contaqua API Meter Auth Test ===\n\n";

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$mongoUri = $_ENV['MONGO_URI'] ?? '';
$mongoDb = $_ENV['MONGO_DATABASE'] ?? 'water_meter';

if ($mongoUri === '') {
    echo "ERROR: MONGO_URI not set\n";
    exit;
}

$client = new MongoDB\Client($mongoUri);
$db = $client->selectDatabase($mongoDb);

$deveui = strtoupper(trim($_GET['deveui'] ?? ''));
$username = trim($_GET['user'] ?? '');
$password = trim($_GET['pass'] ?? '');

if ($deveui === '') {
    echo "Usage: ?deveui=DEV_EUI_HEX&user=USERNAME&pass=PASSWORD\n\n";
    echo "Available meters in DB:\n";
    $meters = $db->selectCollection('meter_auth')->find([], ['limit' => 10]);
    foreach ($meters as $m) {
        echo "  - deveui: " . ($m['deveui'] ?? 'N/A') . "\n";
        echo "    authkeys: " . (isset($m['authkeys']) ? implode(', ', (array)$m['authkeys']) : 'N/A') . "\n";
        echo "    valid_until: " . (isset($m['valid_until']) ? $m['valid_until'] : 'N/A') . "\n\n";
    }
    exit;
}

echo "Testing meter: {$deveui}\n";
echo "User: {$username}\n\n";

// 1. Check if meter exists
$meter = $db->selectCollection('meter_auth')->findOne([
    '$or' => [
        ['deveui' => $deveui],
        ['meterid' => $deveui],
    ]
]);

if (!$meter) {
    echo "ERROR: Meter {$deveui} NOT found in meter_auth collection!\n";
    echo "Available meters:\n";
    $meters = $db->selectCollection('meter_auth')->find([], ['limit' => 5]);
    foreach ($meters as $m) {
        echo "  - " . ($m['deveui'] ?? 'unknown') . "\n";
    }
    exit;
}

echo "1. Meter found:\n";
echo "   deveui: " . ($meter['deveui'] ?? 'N/A') . "\n";
echo "   authkeys: " . (isset($meter['authkeys']) ? implode(', ', (array)$meter['authkeys']) : 'N/A') . "\n";
echo "   valid_until: " . (isset($meter['valid_until']) ? (is_object($meter['valid_until']) ? $meter['valid_until']->toDateTime()->format('Y-m-d H:i:s') : $meter['valid_until']) : 'N/A') . "\n\n";

// 2. Check user
if ($username === '' || $password === '') {
    echo "2. Skipping user check (provide user and pass)\n";
} else {
    $user = $db->selectCollection('user_auth')->findOne(['user' => $username]);
    if (!$user) {
        echo "2. ERROR: User {$username} NOT found!\n";
    } else {
        echo "2. User found:\n";
        echo "   access: " . ($user['access'] ?? 'N/A') . "\n";
        echo "   user_id: " . ($user['user_id'] ?? 'N/A') . "\n";
        
        // Verify password
        if (isset($user['salt']) && isset($user['pass'])) {
            $hash = hash('sha256', $user['salt'] . ':' . $password);
            if (hash_equals($user['pass'], $hash)) {
                echo "   Password: CORRECT\n";
                
                // Generate authkey
                $access = (int) ($user['access'] ?? 0);
                $userId = (int) ($user['user_id'] ?? 0);
                $authKey = strtoupper(substr(hash('sha256', 'AUTH_TECH:' . $access . ':' . $userId), 0, 32));
                echo "   Generated authkey: {$authKey}\n";
                
                // Check if authkey is in meter
                $meterAuthKeys = isset($meter['authkeys']) ? (array)$meter['authkeys'] : [];
                if (isset($meter['authkey']) && $meter['authkey']) {
                    $meterAuthKeys[] = $meter['authkey'];
                }
                $meterAuthKeys = array_map('strtoupper', $meterAuthKeys);
                
                if (in_array($authKey, $meterAuthKeys, true)) {
                    echo "   Authkey MATCH: YES - User can authenticate this meter!\n";
                } else {
                    echo "   Authkey MATCH: NO - User CANNOT authenticate this meter!\n";
                    echo "   Meter authkeys: " . implode(', ', $meterAuthKeys) . "\n";
                }
            } else {
                echo "   Password: INCORRECT\n";
            }
        } else {
            echo "   Password: Cannot verify (missing salt or pass)\n";
        }
    }
}

echo "\n=== End of Test ===\n";

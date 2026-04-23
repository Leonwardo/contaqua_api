<?php
/**
 * Test script for MeterApp API compatibility
 * Simulates all API calls made by the Android MeterApp
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== MeterApp API Compatibility Test ===\n\n";

$baseUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'], 2);
$baseUrl = str_replace('\\', '/', $baseUrl);
$baseUrl = rtrim($baseUrl, '/');

echo "Base URL: {$baseUrl}\n\n";

$results = [];

function testEndpoint(string $name, string $method, string $endpoint, array $data = []): array {
    global $baseUrl;
    
    $url = $baseUrl . $endpoint;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'name' => $name,
        'endpoint' => $endpoint,
        'method' => $method,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'success' => $httpCode >= 200 && $httpCode < 300,
    ];
}

// Test 1: Server health check
echo "1. Testing /api/server (health check)...\n";
$result = testEndpoint('Server Health', 'GET', '/api/server');
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['success'] ? '✓' : '✗') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 2: User authentication (this will fail without valid credentials, but tests endpoint exists)
echo "2. Testing /api/user_token (authentication)...\n";
$result = testEndpoint('User Token', 'POST', '/api/user_token', [
    'user' => 'test_user',
    'pass' => 'test_pass'
]);
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 201 ? '✓ (expected for invalid creds)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 3: Auth validate (will fail without token)
echo "3. Testing /api/auth/validate...\n";
$result = testEndpoint('Auth Validate', 'POST', '/api/auth/validate', [
    'token' => 'INVALIDTOKEN123456789'
]);
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 401 ? '✓ (expected)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 4: Meter token (will fail without valid token)
echo "4. Testing /api/meter_token...\n";
$result = testEndpoint('Meter Token', 'POST', '/api/meter_token', [
    'token' => 'INVALIDTOKEN',
    'challenge' => 'ABC123',
    'deveui' => 'A81758FFFE05668E'
]);
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 401 ? '✓ (expected)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 5: Config list (will fail without token)
echo "5. Testing /api/config (legacy)...\n";
$result = testEndpoint('Config Legacy', 'POST', '/api/config', [
    'token' => 'INVALIDTOKEN',
    'deveui' => 'A81758FFFE05668E',
    'category' => 'general'
]);
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 401 ? '✓ (expected)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 6: Session endpoint (will fail without token)
echo "6. Testing /api/meter/session...\n";
$result = testEndpoint('Meter Session', 'POST', '/api/meter/session', [
    'token' => 'INVALIDTOKEN',
    'deveui' => 'A81758FFFE05668E',
    'counter' => 123,
    'sessionkey' => 'AABBCCDDEEFF00112233445566778899'
]);
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 401 ? '✓ (expected)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 7: Diagnostic list (will fail without token)
echo "7. Testing /api/meterdiag_list...\n";
$result = testEndpoint('Diag List', 'POST', '/api/meterdiag_list', [
    'token' => 'INVALIDTOKEN',
    'deveui' => 'A81758FFFE05668E'
]);
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 401 ? '✓ (expected)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 8: Diagnostic report (will fail without token)
echo "8. Testing /api/meterdiag_report...\n";
$result = testEndpoint('Diag Report', 'POST', '/api/meterdiag_report', [
    'token' => 'INVALIDTOKEN',
    'deveui' => 'A81758FFFE05668E',
    'report' => 'test report data'
]);
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 401 ? '✓ (expected)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 9: Firmware list (will fail without token)
echo "9. Testing /api/firmware...\n";
$result = testEndpoint('Firmware List', 'POST', '/api/firmware', [
    'token' => 'INVALIDTOKEN'
]);
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 401 ? '✓ (expected)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Test 10: Config file download (will likely 404)
echo "10. Testing /api/config/{id} (download)...\n";
$result = testEndpoint('Config Download', 'GET', '/api/config/000000000000000000000000');
$results[] = $result;
echo "   Status: {$result['http_code']} " . ($result['http_code'] === 404 ? '✓ (expected)' : '?') . "\n";
echo "   Response: " . substr($result['response'], 0, 100) . "\n\n";

// Summary
echo "=== Summary ===\n\n";
$passed = 0;
$failed = 0;

foreach ($results as $r) {
    $status = $r['http_code'] >= 200 && $r['http_code'] < 500 ? '✓' : '✗';
    if ($r['http_code'] === 0) {
        $status = '✗ CONNECTION ERROR';
        $failed++;
    } else {
        $passed++;
    }
    echo "{$status} {$r['name']}: HTTP {$r['http_code']}\n";
}

echo "\n";
echo "Endpoints tested: " . count($results) . "\n";
echo "Responsive: {$passed}\n";
echo "Failed connections: {$failed}\n";

echo "\n=== Notes ===\n";
echo "- Endpoints returning 401 are working correctly (invalid token)\n";
echo "- Endpoints returning 404 for config/firmware are expected (no data)\n";
echo "- If any endpoint returns 500, check the server logs\n";
echo "- Test with valid credentials to verify full functionality\n";

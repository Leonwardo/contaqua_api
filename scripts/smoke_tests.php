<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script in CLI.\n");
    exit(1);
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "cURL extension is required for smoke tests.\n");
    exit(1);
}

$baseUrl = rtrim((string) ($argv[1] ?? getenv('SMOKE_BASE_URL') ?: ''), '/');
if ($baseUrl === '') {
    fwrite(STDERR, "Usage: php scripts/smoke_tests.php <base_url> [admin_token]\n");
    fwrite(STDERR, "Example: php scripts/smoke_tests.php https://contaqua.rf.gd ContaquaAdminSecure2026\n");
    exit(1);
}

$adminToken = (string) ($argv[2] ?? getenv('ADMIN_TOKEN') ?: '');
$cookieJar = tempnam(sys_get_temp_dir(), 'smoke_cookie_');
if ($cookieJar === false) {
    fwrite(STDERR, "Failed to create temp cookie jar.\n");
    exit(1);
}

register_shutdown_function(static function () use ($cookieJar): void {
    if (is_file($cookieJar)) {
        @unlink($cookieJar);
    }
});

/**
 * @return array{status:int,body:string,error:string}
 */
function httpRequest(string $method, string $url, string $cookieJar, array $postFields = []): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['status' => 0, 'body' => '', 'error' => 'curl_init failed'];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json, text/html;q=0.9,*/*;q=0.8']);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = $body === false ? (string) curl_error($ch) : '';

    curl_close($ch);

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'error' => $error,
    ];
}

$tests = [];

$tests[] = [
    'name' => 'GET /api/server returns OK text',
    'run' => static function () use ($baseUrl, $cookieJar): array {
        $res = httpRequest('GET', $baseUrl . '/api/server', $cookieJar);
        $ok = $res['error'] === '' && $res['status'] === 200 && trim($res['body']) === 'OK';
        return [$ok, "status={$res['status']} body=" . trim($res['body']) . ($res['error'] !== '' ? " error={$res['error']}" : '')];
    },
];

$tests[] = [
    'name' => 'GET /api/health returns JSON ok=true',
    'run' => static function () use ($baseUrl, $cookieJar): array {
        $res = httpRequest('GET', $baseUrl . '/api/health', $cookieJar);
        $json = json_decode($res['body'], true);
        $ok = $res['error'] === ''
            && $res['status'] === 200
            && is_array($json)
            && ($json['ok'] ?? false) === true
            && ($json['service'] ?? '') === 'meter-api';

        return [$ok, "status={$res['status']} body=" . substr(trim($res['body']), 0, 180) . ($res['error'] !== '' ? " error={$res['error']}" : '')];
    },
];

$tests[] = [
    'name' => 'POST /api/auth/validate without token returns 400',
    'run' => static function () use ($baseUrl, $cookieJar): array {
        $res = httpRequest('POST', $baseUrl . '/api/auth/validate', $cookieJar, []);
        $json = json_decode($res['body'], true);
        $ok = $res['error'] === ''
            && $res['status'] === 400
            && is_array($json)
            && ($json['ok'] ?? null) === false;

        return [$ok, "status={$res['status']} body=" . substr(trim($res['body']), 0, 180) . ($res['error'] !== '' ? " error={$res['error']}" : '')];
    },
];

$tests[] = [
    'name' => 'GET / shows login page or admin panel',
    'run' => static function () use ($baseUrl, $cookieJar): array {
        $res = httpRequest('GET', $baseUrl . '/', $cookieJar);
        $body = $res['body'];
        $hasLogin = str_contains($body, 'Entrar no painel') || str_contains($body, 'Credenciais tempor');
        $hasAdmin = str_contains($body, 'Meter API Admin');
        $ok = $res['error'] === '' && $res['status'] === 200 && ($hasLogin || $hasAdmin);

        return [$ok, "status={$res['status']} detected=" . ($hasLogin ? 'login' : ($hasAdmin ? 'admin' : 'unknown')) . ($res['error'] !== '' ? " error={$res['error']}" : '')];
    },
];

$tests[] = [
    'name' => 'POST / login admin/admin reaches admin panel',
    'run' => static function () use ($baseUrl, $cookieJar): array {
        $res = httpRequest('POST', $baseUrl . '/', $cookieJar, [
            'root_action' => 'login',
            'username' => 'admin',
            'password' => 'admin',
        ]);

        $ok = $res['error'] === ''
            && $res['status'] === 200
            && str_contains($res['body'], 'Meter API Admin');

        return [$ok, "status={$res['status']} contains_admin=" . (str_contains($res['body'], 'Meter API Admin') ? 'yes' : 'no') . ($res['error'] !== '' ? " error={$res['error']}" : '')];
    },
];

if ($adminToken !== '') {
    $tests[] = [
        'name' => 'GET /admin with admin_token returns panel',
        'run' => static function () use ($baseUrl, $cookieJar, $adminToken): array {
            $url = $baseUrl . '/admin?admin_token=' . rawurlencode($adminToken);
            $res = httpRequest('GET', $url, $cookieJar);
            $ok = $res['error'] === ''
                && $res['status'] === 200
                && str_contains($res['body'], 'Meter API Admin');

            return [$ok, "status={$res['status']} contains_admin=" . (str_contains($res['body'], 'Meter API Admin') ? 'yes' : 'no') . ($res['error'] !== '' ? " error={$res['error']}" : '')];
        },
    ];
}

$passed = 0;
$failed = 0;

echo "Base URL: {$baseUrl}\n";
if ($adminToken !== '') {
    echo "Admin token: provided\n";
}

echo "----------------------------------------\n";

foreach ($tests as $index => $test) {
    $name = (string) $test['name'];
    $runner = $test['run'];
    [$ok, $detail] = $runner();

    if ($ok) {
        $passed++;
        echo sprintf("[%02d] PASS - %s\n      %s\n", $index + 1, $name, $detail);
    } else {
        $failed++;
        echo sprintf("[%02d] FAIL - %s\n      %s\n", $index + 1, $name, $detail);
    }
}

echo "----------------------------------------\n";
echo "Result: {$passed} passed, {$failed} failed\n";

exit($failed > 0 ? 1 : 0);

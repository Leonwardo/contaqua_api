<?php

declare(strict_types=1);

session_start();

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$basePath = $scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.' ? rtrim($scriptDir, '/') : '';

$relativePath = $uriPath;
if ($basePath !== '' && str_starts_with($relativePath, $basePath . '/')) {
    $relativePath = substr($relativePath, strlen($basePath));
} elseif ($basePath !== '' && $relativePath === $basePath) {
    $relativePath = '/';
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    unset($_SESSION['admin_root_ok']);
    $location = ($basePath !== '' ? $basePath : '') . '/';
    header('Location: ' . $location, true, 302);
    exit;
}

if (!function_exists('readAdminTokenFromEnv')) {
    function readAdminTokenFromEnv(string $rootPath): string
    {
        $token = trim((string) getenv('ADMIN_TOKEN'));
        if ($token !== '') {
            return $token;
        }

        $envFile = $rootPath . '/.env';
        if (!is_file($envFile)) {
            return '';
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return '';
        }

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (!str_starts_with($trimmed, 'ADMIN_TOKEN=')) {
                continue;
            }

            $value = trim(substr($trimmed, strlen('ADMIN_TOKEN=')));
            return trim($value, " \t\n\r\0\x0B\"'");
        }

        return '';
    }

    function renderRootLogin(string $basePath, string $error = ''): void
    {
        $formAction = ($basePath !== '' ? $basePath : '') . '/';
        $errorHtml = $error !== ''
            ? '<div style="background:#fff0f0;border:1px solid #f3b4b4;color:#8a1f1f;padding:10px;border-radius:8px;margin-bottom:12px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>'
            : '';

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Login Admin</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Segoe UI,Arial,sans-serif;background:linear-gradient(140deg,#ebf4ff,#eafcf4)}.card{width:min(420px,92vw);background:#fff;border:1px solid #d9e4ef;border-radius:14px;padding:18px;box-shadow:0 12px 28px rgba(0,0,0,.08)}h1{margin:0 0 12px 0;font-size:22px}label{display:block;font-size:13px;color:#49637e;margin-top:10px;margin-bottom:5px}input{width:100%;padding:10px;border-radius:8px;border:1px solid #b8c8d8}button{margin-top:14px;width:100%;padding:10px;border:none;border-radius:9px;background:#1469ac;color:#fff;font-weight:700;cursor:pointer}.tiny{margin-top:12px;font-size:12px;color:#5b738d}</style>'
            . '</head><body><main class="card"><h1>Entrar no painel</h1>'
            . $errorHtml
            . '<form method="post" action="' . htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="root_action" value="login">'
            . '<label>Utilizador</label><input type="text" name="username" autocomplete="username" required>'
            . '<label>Senha</label><input type="password" name="password" autocomplete="current-password" required>'
            . '<button type="submit">Entrar</button>'
            . '</form>'
            . '<div class="tiny">Credenciais temporárias: admin / admin</div>'
            . '</main></body></html>';
    }

    function dispatchAdminRequest(string $basePath, string $adminToken, string $path = '/admin'): void
    {
        $targetPath = ($basePath !== '' ? $basePath : '') . $path;
        $query = $_GET;
        unset($query['logout']);
        $query['admin_token'] = $adminToken;
        $queryString = http_build_query($query);

        $_GET = $query;
        $_REQUEST = array_merge($_GET, $_POST);
        $_SERVER['REQUEST_URI'] = $targetPath . ($queryString !== '' ? '?' . $queryString : '');
        $_SERVER['SCRIPT_NAME'] = ($basePath !== '' ? $basePath : '') . '/index.php';

        require __DIR__ . '/public/index.php';
        exit;
    }
}

$isRootPath = $relativePath === '' || $relativePath === '/';
if ($isRootPath) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string) ($_POST['root_action'] ?? '') === 'login') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($username === 'admin' && $password === 'admin') {
            session_regenerate_id(true);
            $_SESSION['admin_root_ok'] = true;

            $adminToken = readAdminTokenFromEnv(__DIR__);
            if ($adminToken === '') {
                renderRootLogin($basePath, 'ADMIN_TOKEN não encontrado no .env.');
                exit;
            }

            dispatchAdminRequest($basePath, $adminToken, '/admin');
        }

        renderRootLogin($basePath, 'Credenciais inválidas.');
        exit;
    }

    if (($_SESSION['admin_root_ok'] ?? false) === true) {
        $adminToken = readAdminTokenFromEnv(__DIR__);
        if ($adminToken === '') {
            renderRootLogin($basePath, 'ADMIN_TOKEN não encontrado no .env.');
            exit;
        }

        dispatchAdminRequest($basePath, $adminToken, '/admin');
    }

    renderRootLogin($basePath);
    exit;
}

if ($relativePath === '/admin' && ($_SESSION['admin_root_ok'] ?? false) === true && !isset($_GET['admin_token'])) {
    $adminToken = readAdminTokenFromEnv(__DIR__);
    if ($adminToken !== '') {
        dispatchAdminRequest($basePath, $adminToken, '/admin');
    }
}

if (str_starts_with($relativePath, '/assets/')) {
    $assetFile = __DIR__ . '/public' . $relativePath;
    if (is_file($assetFile)) {
        $extension = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
        $mimeMap = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
        ];

        if (isset($mimeMap[$extension])) {
            header('Content-Type: ' . $mimeMap[$extension]);
        }

        $size = filesize($assetFile);
        if ($size !== false) {
            header('Content-Length: ' . (string) $size);
        }

        readfile($assetFile);
        exit;
    }
}

require __DIR__ . '/public/index.php';

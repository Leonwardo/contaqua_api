<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\Env;
use App\Controllers\AdminController;
use App\Controllers\AppUpdateController;
use App\Controllers\AuthController;
use App\Controllers\EncryptController;
use App\Controllers\FirmwareController;
use App\Controllers\HealthController;
use App\Controllers\MeterController;
use App\Database\MongoCollections;
use App\Database\MongoConnection;
use App\Http\Request;
use App\Http\Router;
use App\Services\AdminService;
use App\Services\Logger;
use App\Services\MeterAuthService;
use App\Services\MeterConfigService;
use App\Services\MeterSessionService;
use App\Services\UserAuthService;

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

// Workaround: Se QUERY_STRING estiver vazio, tentar extrair do REQUEST_URI
// (necessário quando mod_rewrite não passa a query string corretamente)
if (empty($_SERVER['QUERY_STRING']) && !empty($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    if (($pos = strpos($uri, '?')) !== false) {
        $_SERVER['QUERY_STRING'] = substr($uri, $pos + 1);
        parse_str($_SERVER['QUERY_STRING'], $_GET);
    }
}

$basePath = dirname(__DIR__);
Env::load($basePath);
date_default_timezone_set(AppConfig::timezone());

$logger = new Logger(AppConfig::logPath($basePath));
$logger->info('Incoming request', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/',
]);

$mongoUri = AppConfig::mongoUri();
if ($mongoUri === '') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'MONGO_URI not configured']);
    exit;
}

$mongo = new MongoConnection($mongoUri, AppConfig::mongoDatabase(), $logger);
$collections = new MongoCollections($mongo);

$userAuthService = new UserAuthService($collections, $logger);
$meterAuthService = new MeterAuthService($collections, $userAuthService);
$meterConfigService = new MeterConfigService($collections);
$meterSessionService = new MeterSessionService($collections, $logger);
$adminService = new AdminService($collections);

$healthController = new HealthController();
$authController = new AuthController($userAuthService);
$meterController = new MeterController($meterAuthService, $meterConfigService, $meterSessionService, $collections, $userAuthService);
$adminController = new AdminController($adminService, AppConfig::adminToken());
$firmwareController = new FirmwareController($collections, $userAuthService);
$appUpdateController = new AppUpdateController();
$encryptController = new EncryptController($userAuthService);

$router = new Router();

$router->add('GET', '/api/server', static fn(Request $req, array $params) => $healthController->server());
$router->add('GET', '/api/health', static fn(Request $req, array $params) => $healthController->health());

$router->add('POST', '/api/auth/validate', static fn(Request $req, array $params) => $authController->validate($req));
$router->add('POST', '/api/user_token', static fn(Request $req, array $params) => $authController->userToken($req));

$router->add('POST', '/api/meter/authorize', static fn(Request $req, array $params) => $meterController->authorize($req));
$router->add('POST', '/api/meter_token', static fn(Request $req, array $params) => $meterController->meterToken($req));

$router->add('GET', '/api/meter/config', static fn(Request $req, array $params) => $meterController->config($req));
$router->add('POST', '/api/config', static fn(Request $req, array $params) => $meterController->configLegacy($req));
$router->add('GET', '/api/config/{id}', static fn(Request $req, array $params) => $meterController->configFile($params));

$router->add('POST', '/api/meter/session', static fn(Request $req, array $params) => $meterController->session($req));

$router->add('POST', '/api/meterdiag_list', static fn(Request $req, array $params) => $meterController->meterDiagList($req));
$router->add('POST', '/api/meterdiag_report', static fn(Request $req, array $params) => $meterController->meterDiagReport($req));

$router->add('POST', '/api/firmware', static fn(Request $req, array $params) => $firmwareController->checkUpdate($req));
$router->add('GET', '/api/firmware/{id}', static fn(Request $req, array $params) => $firmwareController->download($params));

$router->add('POST', '/api/encrypt', static fn(Request $req, array $params) => $encryptController->encrypt($req));
$router->add('POST', '/api/decrypt', static fn(Request $req, array $params) => $encryptController->decrypt($req));

$router->add('POST', '/api/android', static fn(Request $req, array $params) => $appUpdateController->check($req));
$router->add('GET', '/api/android/{id}', static fn(Request $req, array $params) => $appUpdateController->download($params));

$router->add('GET', '/api/admin/metrics', static fn(Request $req, array $params) => $adminController->metrics($req));
$router->add('GET', '/api/admin/mongo-diagnostics', static fn(Request $req, array $params) => $adminController->mongoDiagnostics($req));

// Admin dashboard route
$router->add('GET', '/admin', static fn(Request $req, array $params) => $adminController->dashboard($req));
$router->add('POST', '/admin', static fn(Request $req, array $params) => $adminController->dashboard($req));

// Verificar conexão MongoDB antes de processar requisição
$mongoStatus = 'OK';
try {
    $testCollection = $mongo->getDatabase()->selectCollection('test');
    $testCollection->findOne([], ['limit' => 1]);
} catch (Throwable $e) {
    $mongoStatus = 'ERRO: ' . $e->getMessage();
    $logger->error('MongoDB connection failed', ['error' => $e->getMessage()]);
}

try {
    $request = Request::fromGlobals();
    $logger->info('Request parsed', ['path' => $request->path, 'method' => $request->method]);
    $response = $router->dispatch($request);
    $response->send();
} catch (Throwable $exception) {
    $logger->error('Unhandled exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ]);

    http_response_code(500);
    
    // Se for requisição para /admin, mostrar erro detalhado em HTML
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    if (str_contains($uri, '/admin')) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html>
<html>
<head><meta charset="utf-8"><title>Erro 500 - Admin</title>
<style>
body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h1 { color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 10px; }
.info-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 15px 0; }
.error-box { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; margin: 15px 0; }
pre { background: #f8f9fa; padding: 20px; overflow: auto; border-radius: 4px; border: 1px solid #dee2e6; font-size: 12px; }
.success { color: #28a745; }
.fail { color: #dc3545; }
</style>
</head>
<body>
<div class="container">
<h1>Erro Interno do Servidor - Admin</h1>

<div class="info-box">
<strong>Status MongoDB:</strong> <span class="' . ($mongoStatus === 'OK' ? 'success' : 'fail') . '">' . htmlspecialchars($mongoStatus) . '</span>
</div>

<div class="error-box">
<p><strong>Mensagem:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>
<p><strong>Arquivo:</strong> ' . htmlspecialchars($exception->getFile()) . ':' . $exception->getLine() . '</p>
</div>

<h3>Stack Trace:</h3>
<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>

<h3>Request Info:</h3>
<pre>URI: ' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . '
Method: ' . htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'N/A') . '
Query: ' . htmlspecialchars($_SERVER['QUERY_STRING'] ?? 'N/A') . '</pre>
</div>
</body>
</html>';
    } else {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Internal server error: ' . $exception->getMessage()]);
    }
}

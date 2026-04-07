<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\Env;
use App\Controllers\AdminController;
use App\Controllers\AppUpdateController;
use App\Controllers\AuthController;
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

$router->add('POST', '/api/android', static fn(Request $req, array $params) => $appUpdateController->check($req));
$router->add('GET', '/api/android/{id}', static fn(Request $req, array $params) => $appUpdateController->download($params));

$router->add('GET', '/admin', static fn(Request $req, array $params) => $adminController->dashboard($req));
$router->add('POST', '/admin', static fn(Request $req, array $params) => $adminController->dashboard($req));
$router->add('GET', '/index.php', static fn(Request $req, array $params) => $adminController->dashboard($req));
$router->add('POST', '/index.php', static fn(Request $req, array $params) => $adminController->dashboard($req));
$router->add('GET', '/api/admin/metrics', static fn(Request $req, array $params) => $adminController->metrics($req));

try {
    $response = $router->dispatch(Request::fromGlobals());
    $response->send();
} catch (Throwable $exception) {
    $logger->error('Unhandled exception', [
        'message' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString(),
    ]);

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}

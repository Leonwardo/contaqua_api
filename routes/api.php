<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\FirmwareController;
use App\Controllers\HealthController;
use App\Controllers\MeterController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Debug endpoint for troubleshooting
    $app->post('/api/debug/login', function ($request, $response) {
        $data = $request->getParsedBody() ?? [];
        $rawBody = (string) $request->getBody();
        
        $result = [
            'method' => $request->getMethod(),
            'content_type' => $request->getHeaderLine('Content-Type'),
            'parsed_body' => $data,
            'raw_body' => $rawBody,
            'body_empty' => empty($rawBody),
        ];
        
        // Try manual parsing
        parse_str($rawBody, $manualParsed);
        $result['manual_parse'] = $manualParsed;
        
        $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Health check endpoints
    $app->get('/api/server', [HealthController::class, 'server']);
    $app->get('/api/health', [HealthController::class, 'health']);
    
    // Legacy root aliases (MeterApp configs without /api in base URL)
    $app->get('/server', [HealthController::class, 'server']);
    $app->get('/health', [HealthController::class, 'health']);
    
    // Authentication endpoints
    $app->group('/api', function (RouteCollectorProxy $group) {
        // User authentication
        $group->post('/auth/validate', [AuthController::class, 'validate']);
        $group->post('/user_token', [AuthController::class, 'userToken']);
        
        // Meter authentication
        $group->post('/meter/authorize', [MeterController::class, 'authorize']);
        $group->post('/meter_token', [MeterController::class, 'meterToken']);
        
        // Config endpoints
        $group->post('/meter/config', [MeterController::class, 'config']);
        $group->post('/config', [MeterController::class, 'configLegacy']);
        $group->get('/config/{id}', [MeterController::class, 'configFile']);
        
        // Session endpoints
        $group->post('/meter/session', [MeterController::class, 'session']);
        
        // Diagnostic endpoints (legacy)
        $group->post('/meterdiag_list', [MeterController::class, 'meterDiagList']);
        $group->post('/meterdiag_report', [MeterController::class, 'meterDiagReport']);
        
        // Firmware/OTA endpoints (legacy compatibility with MeterApp)
        $group->post('/firmware', [FirmwareController::class, 'listFirmware']);
        $group->post('/firmware/check', [FirmwareController::class, 'checkUpdate']);
        $group->post('/firmware/update', [FirmwareController::class, 'checkUpdate']);
        $group->get('/firmware/{id}', [FirmwareController::class, 'downloadFirmware']);
        
        // BLE cipher endpoints (NFC does not use these; 501 stubs)
        $group->post('/encrypt', [MeterController::class, 'encrypt']);
        $group->post('/decrypt', [MeterController::class, 'decrypt']);
        
        // MeterApp self-updater (Android APK)
        $group->post('/android', [FirmwareController::class, 'listAndroid']);
        $group->get('/android/{file}', [FirmwareController::class, 'downloadAndroid']);
    });

    // Legacy root aliases (no /api prefix)
    $app->post('/auth/validate', [AuthController::class, 'validate']);
    $app->post('/user_token', [AuthController::class, 'userToken']);
    $app->post('/meter/authorize', [MeterController::class, 'authorize']);
    $app->post('/meter_token', [MeterController::class, 'meterToken']);
    $app->post('/meter/config', [MeterController::class, 'config']);
    $app->post('/config', [MeterController::class, 'configLegacy']);
    $app->get('/config/{id}', [MeterController::class, 'configFile']);
    $app->post('/meter/session', [MeterController::class, 'session']);
    $app->post('/meterdiag_list', [MeterController::class, 'meterDiagList']);
    $app->post('/meterdiag_report', [MeterController::class, 'meterDiagReport']);
    $app->post('/firmware', [FirmwareController::class, 'listFirmware']);
    $app->post('/firmware/check', [FirmwareController::class, 'checkUpdate']);
    $app->post('/firmware/update', [FirmwareController::class, 'checkUpdate']);
    $app->get('/firmware/{id}', [FirmwareController::class, 'downloadFirmware']);
    $app->post('/encrypt', [MeterController::class, 'encrypt']);
    $app->post('/decrypt', [MeterController::class, 'decrypt']);
    $app->post('/android', [FirmwareController::class, 'listAndroid']);
    $app->get('/android/{file}', [FirmwareController::class, 'downloadAndroid']);
};

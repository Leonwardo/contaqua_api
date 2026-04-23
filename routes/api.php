<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\FirmwareController;
use App\Controllers\HealthController;
use App\Controllers\MeterController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Health check endpoints
    $app->get('/api/server', [HealthController::class, 'server']);
    $app->get('/api/health', [HealthController::class, 'health']);
    
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
        $group->get('/firmware/{id}', [FirmwareController::class, 'downloadFirmware']);
    });
};

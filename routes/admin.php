<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\HomeController;
use App\Controllers\PortalController;
use App\Controllers\StatusController;
use Slim\App;

return function (App $app): void {
    // Página inicial em branco com botão escondido
    $app->get('/', [HomeController::class, 'index']);
    
    // Portal administrativo (após autenticação)
    $app->get('/admin/portal', [PortalController::class, 'portal']);
    
    // Status da API (monitoramento)
    $app->get('/admin/status', [StatusController::class, 'status']);
    
    // Admin dashboard legado (HTML)
    $app->get('/admin/dashboard', [AdminController::class, 'dashboard']);
    $app->post('/admin/dashboard', [AdminController::class, 'dashboard']);
    $app->get('/admin', [AdminController::class, 'dashboard']);
    $app->post('/admin', [AdminController::class, 'dashboard']);
    
    // Admin API endpoints (JSON)
    $app->get('/api/admin/metrics', [AdminController::class, 'metrics']);
    $app->post('/api/admin/metrics', [AdminController::class, 'metrics']);
    $app->get('/api/admin/users', [AdminController::class, 'users']);
    $app->post('/api/admin/users', [AdminController::class, 'users']);
};

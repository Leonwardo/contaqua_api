<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use Slim\App;

return function (App $app): void {
    // Admin dashboard (HTML)
    $app->get('/admin', [AdminController::class, 'dashboard']);
    $app->post('/admin', [AdminController::class, 'dashboard']);
    
    // Admin API endpoints (JSON)
    $app->get('/api/admin/metrics', [AdminController::class, 'metrics']);
    $app->post('/api/admin/metrics', [AdminController::class, 'metrics']);
};

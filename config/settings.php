<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;

return function (array $env): array {
    $basePath = dirname(__DIR__);
    
    return [
        'settings' => [
            'displayErrorDetails' => filter_var($env['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'logErrors' => true,
            'logErrorDetails' => true,
            'logger' => [
                'name' => $env['APP_NAME'] ?? 'ContaquaAPI',
                'path' => $basePath . '/' . ($env['LOG_PATH'] ?? 'logs/app.log'),
                'level' => Logger::toMonologLevel($env['LOG_LEVEL'] ?? 'debug'),
            ],
        ],
        
        'mongodb' => [
            'uri' => $env['MONGO_URI'] ?? 'mongodb://127.0.0.1:27017',
            'database' => $env['MONGO_DATABASE'] ?? 'contaqua',
            'options' => [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                    'array' => 'array',
                ],
            ],
        ],
        
        'admin' => [
            'token' => $env['ADMIN_TOKEN'] ?? 'default_insecure_token',
            'session_timeout' => (int) ($env['ADMIN_SESSION_TIMEOUT'] ?? 3600),
        ],
        
        'security' => [
            'cors_origin' => $env['CORS_ORIGIN'] ?? '*',
            'rate_limit_enabled' => filter_var($env['RATE_LIMIT_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'rate_limit_requests' => (int) ($env['RATE_LIMIT_REQUESTS'] ?? 100),
            'rate_limit_window' => (int) ($env['RATE_LIMIT_WINDOW'] ?? 60),
        ],
        
        'app' => [
            'name' => $env['APP_NAME'] ?? 'ContaquaAPI',
            'env' => $env['APP_ENV'] ?? 'production',
            'url' => $env['APP_URL'] ?? 'http://localhost',
            'timezone' => $env['APP_TIMEZONE'] ?? 'Europe/Lisbon',
            'legacy_auth_mode' => filter_var($env['LEGACY_AUTH_MODE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'allow_legacy_plain_passwords' => filter_var($env['ALLOW_LEGACY_PLAIN_PASSWORDS'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        ],
        
        'uploads' => [
            'max_size' => (int) ($env['UPLOAD_MAX_SIZE'] ?? 10485760),
            'path' => $basePath . '/' . ($env['UPLOAD_PATH'] ?? 'storage/uploads/'),
        ],
    ];
};

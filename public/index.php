<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Set timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Lisbon');

// Build container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    'settings' => [
        'displayErrorDetails' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    ],
]);

// Enable autowiring
$containerBuilder->addDefinitions([
    'settings.displayErrorDetails' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
]);

// Add settings
$settings = require __DIR__ . '/../config/settings.php';
$containerBuilder->addDefinitions($settings($_ENV));

// Add logger
$containerBuilder->addDefinitions([
    LoggerInterface::class => function (ContainerInterface $c) {
        $settings = $c->get('settings')['logger'];
        $logger = new Logger($settings['name']);
        $logger->pushHandler(new RotatingFileHandler(
            $settings['path'],
            30,
            $settings['level']
        ));
        return $logger;
    },
]);

// Add MongoDB connection
$containerBuilder->addDefinitions([
    \App\Database\MongoConnection::class => function (ContainerInterface $c) {
        $config = $c->get('mongodb');
        $logger = $c->get(LoggerInterface::class);
        return new \App\Database\MongoConnection($config, $logger);
    },
    \App\Database\MongoCollections::class => function (ContainerInterface $c) {
        $connection = $c->get(\App\Database\MongoConnection::class);
        return new \App\Database\MongoCollections($connection);
    },
]);

// Add app config for controllers
$containerBuilder->addDefinitions([
    'appConfig' => function (ContainerInterface $c) {
        return $c->get('app') ?? ['name' => 'ContaquaAPI'];
    },
]);

// Add controllers
$containerBuilder->addDefinitions([
    \App\Controllers\HealthController::class => function (ContainerInterface $c) {
        return new \App\Controllers\HealthController(
            $c->get(\App\Database\MongoConnection::class),
            $c->get('appConfig')
        );
    },
    \App\Controllers\AuthController::class => function (ContainerInterface $c) {
        return new \App\Controllers\AuthController(
            $c->get(\App\Services\UserAuthService::class),
            $c->get(LoggerInterface::class)
        );
    },
    \App\Controllers\MeterController::class => function (ContainerInterface $c) {
        return new \App\Controllers\MeterController(
            $c->get(\App\Services\MeterAuthService::class),
            $c->get(\App\Services\MeterConfigService::class),
            $c->get(\App\Services\MeterSessionService::class),
            $c->get(\App\Services\UserAuthService::class),
            $c->get(LoggerInterface::class)
        );
    },
    \App\Controllers\FirmwareController::class => function (ContainerInterface $c) {
        return new \App\Controllers\FirmwareController(
            $c->get(\App\Services\FirmwareService::class),
            $c->get(\App\Services\UserAuthService::class),
            $c->get(LoggerInterface::class)
        );
    },
    \App\Controllers\AdminController::class => function (ContainerInterface $c) {
        return new \App\Controllers\AdminController(
            $c->get(\App\Services\AdminService::class),
            $c->get('admin')['token'] ?? 'default_token',
            $c->get(LoggerInterface::class)
        );
    },
    \App\Controllers\HomeController::class => function (ContainerInterface $c) {
        return new \App\Controllers\HomeController();
    },
    \App\Controllers\PortalController::class => function (ContainerInterface $c) {
        return new \App\Controllers\PortalController(
            $c->get('admin')
        );
    },
    \App\Controllers\StatusController::class => function (ContainerInterface $c) {
        return new \App\Controllers\StatusController(
            $c->get('admin'),
            $c->get(\App\Database\MongoConnection::class),
            $c->get(LoggerInterface::class),
            $c->get('appConfig')
        );
    },
]);

// Add services
$containerBuilder->addDefinitions([
    \App\Services\UserAuthService::class => function (ContainerInterface $c) {
        $collections = $c->get(\App\Database\MongoCollections::class);
        $logger = $c->get(LoggerInterface::class);
        $allowLegacy = $c->get('app')['allow_legacy_plain_passwords'] ?? false;
        return new \App\Services\UserAuthService($collections, $logger, $allowLegacy);
    },
    \App\Services\MeterAuthService::class => function (ContainerInterface $c) {
        $collections = $c->get(\App\Database\MongoCollections::class);
        $userAuthService = $c->get(\App\Services\UserAuthService::class);
        $logger = $c->get(LoggerInterface::class);
        return new \App\Services\MeterAuthService($collections, $userAuthService, $logger);
    },
    \App\Services\MeterConfigService::class => function (ContainerInterface $c) {
        $collections = $c->get(\App\Database\MongoCollections::class);
        $logger = $c->get(LoggerInterface::class);
        return new \App\Services\MeterConfigService($collections, $logger);
    },
    \App\Services\MeterSessionService::class => function (ContainerInterface $c) {
        $collections = $c->get(\App\Database\MongoCollections::class);
        $logger = $c->get(LoggerInterface::class);
        return new \App\Services\MeterSessionService($collections, $logger);
    },
    \App\Services\FirmwareService::class => function (ContainerInterface $c) {
        $collections = $c->get(\App\Database\MongoCollections::class);
        $logger = $c->get(LoggerInterface::class);
        return new \App\Services\FirmwareService($collections, $logger);
    },
    \App\Services\AdminService::class => function (ContainerInterface $c) {
        return new \App\Services\AdminService(
            $c->get(\App\Database\MongoCollections::class),
            $c->get(LoggerInterface::class)
        );
    },
]);

$container = $containerBuilder->build();

// Create App
AppFactory::setContainer($container);
$app = AppFactory::create();

// Detect and set base path for subdirectory installations
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Calculate base path from script location
$basePath = dirname($scriptName);
$basePath = rtrim(str_replace('\\', '/', $basePath), '/');

// Always set base path if we're in a subdirectory
if ($basePath !== '' && $basePath !== '.' && $basePath !== '/') {
    $app->setBasePath($basePath);
}

// Fallback for URLs without mod_rewrite (e.g., /index.php/admin/portal)
if (strpos($requestUri, $basePath . '/index.php') === 0) {
    $app->setBasePath($basePath . '/index.php');
}

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Add CORS middleware
$app->add(new \App\Middleware\CorsMiddleware($container->get('security')['cors_origin'] ?? '*'));

// Add error middleware
$app->add(new \App\Middleware\ErrorMiddleware(
    $container->get(LoggerInterface::class),
    $container->get('settings')['displayErrorDetails'] ?? false
));

// Add routing middleware
$app->addRoutingMiddleware();

// Register routes
(require __DIR__ . '/../routes/api.php')($app);
(require __DIR__ . '/../routes/admin.php')($app);

// Run
$app->run();

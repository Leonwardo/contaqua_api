<?php

declare(strict_types=1);

// Verificar se vendor existe, senão usar fallback
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/index-fallback.php';
    exit;
}

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
    \App\Services\AdminService::class => function (ContainerInterface $c) {
        return new \App\Services\AdminService(
            $c->get(\App\Database\MongoCollections::class),
            $c->get(\App\Services\UserAuthService::class),
            $c->get(\App\Services\MeterAuthService::class),
            $c->get(\App\Services\MeterConfigService::class),
            $c->get(\App\Services\MeterSessionService::class),
            $c->get(LoggerInterface::class)
        );
    },
]);

$container = $containerBuilder->build();

// Create App
AppFactory::setContainer($container);
$app = AppFactory::create();

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

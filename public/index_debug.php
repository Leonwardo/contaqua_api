<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Debug info
echo "=== SERVER VARIABLES ===<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "<br>";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'N/A') . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>";
echo "<br>";

// Calculate base path
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = dirname($scriptName);
$basePath = rtrim(str_replace('\\', '/', $basePath), '/');

echo "Calculated basePath: '" . $basePath . "'<br>";
echo "Will set basePath: " . (($basePath !== '' && $basePath !== '.' && $basePath !== '/') ? 'YES' : 'NO') . "<br>";
echo "<br>";

// Now load the real app
try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
    
    $containerBuilder = new DI\ContainerBuilder();
    $containerBuilder->addDefinitions([
        'settings' => [
            'displayErrorDetails' => true,
        ],
    ]);
    
    $settings = require __DIR__ . '/../config/settings.php';
    $containerBuilder->addDefinitions($settings($_ENV));
    
    $containerBuilder->addDefinitions([
        Psr\Log\LoggerInterface::class => function (Psr\Container\ContainerInterface $c) {
            $settings = $c->get('settings')['logger'];
            $logger = new Monolog\Logger($settings['name']);
            $logger->pushHandler(new Monolog\Handler\RotatingFileHandler(
                $settings['path'],
                30,
                $settings['level']
            ));
            return $logger;
        },
    ]);
    
    $container = $containerBuilder->build();
    
    Slim\Factory\AppFactory::setContainer($container);
    $app = Slim\Factory\AppFactory::create();
    
    // Set base path
    if ($basePath !== '' && $basePath !== '.' && $basePath !== '/') {
        $app->setBasePath($basePath);
        echo "Base path set to: " . $basePath . "<br><br>";
    }
    
    // Add middleware
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    
    // Register routes
    (require __DIR__ . '/../routes/api.php')($app);
    (require __DIR__ . '/../routes/admin.php')($app);
    
    // List registered routes
    echo "=== REGISTERED ROUTES ===<br>";
    $routeCollector = $app->getRouteCollector();
    $routes = $routeCollector->getRoutes();
    foreach ($routes as $route) {
        echo implode('|', $route->getMethods()) . ' ' . $route->getPattern() . "<br>";
    }
    echo "<br>";
    
    // Check current request
    $request = Slim\Psr7\Factory\ServerRequestFactory::createFromGlobals();
    echo "Request URI: " . $request->getUri()->getPath() . "<br>";
    
    $app->run();
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

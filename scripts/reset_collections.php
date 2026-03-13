<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Config\Env;
use App\Database\MongoCollections;
use App\Database\MongoConnection;
use App\Services\Logger;

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$basePath = dirname(__DIR__);
Env::load($basePath);

$logger = new Logger(AppConfig::logPath($basePath));
$mongo = new MongoConnection(AppConfig::mongoUri(), AppConfig::mongoDatabase(), $logger);
$collections = new MongoCollections($mongo);

$targets = [
    'user_auth' => $collections->userAuth(),
    'meter_auth' => $collections->meterAuth(),
    'meter_config' => $collections->meterConfig(),
    'meter_session' => $collections->meterSession(),
];

foreach ($targets as $name => $collection) {
    $result = $collection->deleteMany([]);
    echo $name . ': deleted ' . $result->getDeletedCount() . PHP_EOL;
}

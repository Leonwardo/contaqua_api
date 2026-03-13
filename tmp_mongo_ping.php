<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$uris = [
    'default' => 'mongodb+srv://ruimeskuita_db_user:fXrZ%40wWn99vKAa@cluster0.q338oqb.mongodb.net/?appName=Cluster0',
    'tls_relaxed' => 'mongodb+srv://ruimeskuita_db_user:fXrZ%40wWn99vKAa@cluster0.q338oqb.mongodb.net/?appName=Cluster0&tls=true&tlsAllowInvalidCertificates=true',
];

foreach ($uris as $name => $uri) {
    echo "==== {$name} ====\n";
    try {
        $manager = new MongoDB\Driver\Manager($uri);
        $cursor = $manager->executeCommand('admin', new MongoDB\Driver\Command(['ping' => 1]));
        $rows = $cursor->toArray();
        echo 'OK: ' . json_encode($rows, JSON_UNESCAPED_SLASHES) . "\n";
    } catch (Throwable $e) {
        echo 'ERROR: ' . $e->getMessage() . "\n";
    }
}

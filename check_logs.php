<?php
// Check recent application logs
header('Content-Type: text/plain');

$logsDir = __DIR__ . '/logs';
echo "=== Application Logs ===\n\n";

// Check if logs directory exists and create if needed
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
    echo "Created logs directory\n\n";
}

// Read admin operations log specifically
$adminLog = $logsDir . '/admin_operations.log';
if (file_exists($adminLog)) {
    echo "--- admin_operations.log ---\n";
    $content = file_get_contents($adminLog);
    $lines = explode("\n", $content);
    $recentLines = array_slice(array_filter($lines), -50);
    echo implode("\n", $recentLines) . "\n\n";
} else {
    echo "admin_operations.log not found (will be created when operations occur)\n\n";
}

// Read all other log files
$files = glob($logsDir . '/*.log');
rsort($files);
foreach (array_slice($files, 0, 5) as $file) {
    if (basename($file) === 'admin_operations.log') continue;
    echo "--- " . basename($file) . " ---\n";
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    echo implode("\n", array_slice(array_filter($lines), -20)) . "\n\n";
}

echo "=== PHP Error Log ===\n\n";
$phpErrorLog = ini_get('error_log');
echo "PHP error log path: $phpErrorLog\n";
if ($phpErrorLog && file_exists($phpErrorLog) && is_readable($phpErrorLog)) {
    $content = @file_get_contents($phpErrorLog);
    if ($content !== false) {
        $lines = explode("\n", $content);
        echo implode("\n", array_slice(array_filter($lines), -30)) . "\n";
    } else {
        echo "Cannot read PHP error log (permission denied)\n";
    }
} else {
    echo "PHP error log not accessible\n";
}

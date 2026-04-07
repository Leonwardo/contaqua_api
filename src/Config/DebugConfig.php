<?php
declare(strict_types=1);

namespace App\Config;

class DebugConfig
{
    public static function enableDebugLogging(): void
    {
        $logsDir = __DIR__ . '/../../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        $logFile = $logsDir . '/admin_operations.log';
        
        // Configure error logging
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
        
        // Also create a custom logger function
        if (!function_exists('admin_log')) {
            function admin_log(string $message): void {
                $logFile = __DIR__ . '/../../logs/admin_operations.log';
                $timestamp = date('[Y-m-d H:i:s]');
                file_put_contents($logFile, "$timestamp $message\n", FILE_APPEND | LOCK_EX);
            }
        }
    }
    
    public static function getLogPath(): string
    {
        $logsDir = __DIR__ . '/../../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        return $logsDir . '/admin_operations.log';
    }
}

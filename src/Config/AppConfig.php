<?php

declare(strict_types=1);

namespace App\Config;

final class AppConfig
{
    public static function timezone(): string
    {
        return Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC';
    }

    public static function debug(): bool
    {
        return strtolower((string) Env::get('APP_DEBUG', 'false')) === 'true';
    }

    public static function logPath(string $basePath): string
    {
        $path = Env::get('APP_LOG_PATH', 'storage/logs/api.log') ?? 'storage/logs/api.log';
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1
            ? $path
            : $basePath . '/' . $path;
    }

    public static function mongoUri(): string
    {
        return (string) Env::get('MONGO_URI', '');
    }

    public static function mongoDatabase(): string
    {
        return (string) Env::get('MONGO_DB_NAME', 'contaqua_meter');
    }

    public static function adminToken(): string
    {
        $token = Env::get('ADMIN_TOKEN');
        if ($token === null || $token === '' || $token === 'change_me_admin_token') {
            return 'ContaquaAdminSecure2026';
        }
        return $token;
    }
}

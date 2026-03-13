<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @param array<string, string> $headers */
    /** @param array<string, mixed> $query */
    /** @param array<string, mixed> $body */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers,
        public readonly array $query,
        public readonly array $body,
        public readonly string $rawBody
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $basePath = rtrim((string) (getenv('APP_BASE_PATH') ?: ''), '/');
        if ($basePath === '') {
            $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
            if ($scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.') {
                $basePath = rtrim($scriptDir, '/');
            }
        }

        if (str_ends_with($basePath, '/public')) {
            $basePath = substr($basePath, 0, -7);
        }

        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath));
        } elseif ($basePath !== '' && $path === $basePath) {
            $path = '/';
        }

        if (str_starts_with($path, '/public/')) {
            $path = substr($path, 7);
        } elseif ($path === '/public') {
            $path = '/';
        }

        if ($path === '') {
            $path = '/';
        }
        $headers = function_exists('getallheaders') ? (array) getallheaders() : [];

        $rawBody = file_get_contents('php://input') ?: '';
        $body = self::parseBody($headers, $rawBody);

        return new self($method, $path, $headers, $_GET, $body, $rawBody);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->headers['Authorization'] ?? $this->headers['authorization'] ?? null;
        if ($header === null) {
            return null;
        }

        if (preg_match('/Bearer\s+(.+)/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim((string) $header);
    }

    /** @param array<string, string> $headers */
    /** @return array<string, mixed> */
    private static function parseBody(array $headers, string $rawBody): array
    {
        if ($rawBody === '') {
            return $_POST ?: [];
        }

        $contentType = strtolower((string) ($headers['Content-Type'] ?? $headers['content-type'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            return is_array($decoded) ? $decoded : [];
        }

        parse_str($rawBody, $formData);
        if (is_array($formData) && $formData !== []) {
            return $formData;
        }

        return $_POST ?: [];
    }
}

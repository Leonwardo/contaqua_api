<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserAuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class AuthController
{
    public function __construct(
        private UserAuthService $userAuthService,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Validate user token
     * POST /api/auth/validate
     */
    public function validate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $token = (string) ($data['token'] ?? $this->getBearerToken($request));
        
        if ($token === '') {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'token is required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $user = $this->userAuthService->validateToken($token);
        
        if ($user === null) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'authenticated' => false,
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $response->getBody()->write(json_encode([
            'ok' => true,
            'authenticated' => true,
            'user' => [
                'user' => $user->user,
                'user_id' => $user->user_id,
                'access' => $user->access,
                'role' => $user->getRole(),
            ],
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Legacy user login endpoint
     * POST /api/user_token
     * Returns plain text token for Android app compatibility
     */
    public function userToken(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        
        // Debug: log raw body for troubleshooting
        $rawBody = (string) $request->getBody();
        $this->logger->debug('user_token request', [
            'content_type' => $request->getHeaderLine('Content-Type'),
            'parsed_body' => $data,
            'raw_body_preview' => substr($rawBody, 0, 200),
        ]);
        
        // Support both form-urlencoded and JSON
        $user = (string) ($data['user'] ?? '');
        $pass = (string) ($data['pass'] ?? '');
        
        // Fallback: try to parse raw body if parsedBody is empty
        if ($user === '' && $pass === '' && $rawBody !== '') {
            parse_str($rawBody, $parsed);
            $user = (string) ($parsed['user'] ?? '');
            $pass = (string) ($parsed['pass'] ?? '');
            $this->logger->debug('user_token fallback parsing', ['user' => $user, 'pass_len' => strlen($pass)]);
        }
        
        if ($user === '' || $pass === '') {
            $this->logger->warning('user_token missing credentials', ['has_user' => $user !== '', 'has_pass' => $pass !== '']);
            $response->getBody()->write('Unable to authenticate user');
            return $response->withStatus(201); // Legacy compatibility
        }
        
        $token = $this->userAuthService->login($user, $pass);
        
        if ($token === null) {
            $this->logger->warning('user_token login failed', ['user' => $user]);
            $response->getBody()->write('Unable to authenticate user');
            return $response->withStatus(201); // Legacy compatibility
        }

        $document = $this->userAuthService->validateToken($token);
        if ($document !== null) {
            $response = $response->withHeader('X-User-Role', (string) $document->getRole());
            $response = $response->withHeader('X-User-Access', (string) ($document->access ?? ''));
        }
        
        $this->logger->info('user_token login success', ['user' => $user]);
        $response->getBody()->write($token);
        return $response;
    }
    
    /**
     * Extract Bearer token from Authorization header
     */
    private function getBearerToken(Request $request): string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        return '';
    }
}

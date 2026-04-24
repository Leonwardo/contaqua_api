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
        $user = (string) ($data['user'] ?? '');
        $pass = (string) ($data['pass'] ?? '');
        
        if ($user === '' || $pass === '') {
            $response->getBody()->write('Unable to authenticate user');
            return $response->withStatus(201); // Legacy compatibility
        }
        
        $token = $this->userAuthService->login($user, $pass);
        
        if ($token === null) {
            $response->getBody()->write('Unable to authenticate user');
            return $response->withStatus(201); // Legacy compatibility
        }

        $document = $this->userAuthService->validateToken($token);
        if ($document !== null) {
            $response = $response->withHeader('X-User-Role', (string) $document->getRole());
            $response = $response->withHeader('X-User-Access', (string) ($document->access ?? ''));
        }
        
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

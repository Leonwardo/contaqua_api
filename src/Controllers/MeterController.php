<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MeterAuthService;
use App\Services\MeterConfigService;
use App\Services\MeterSessionService;
use App\Services\UserAuthService;
use MongoDB\BSON\ObjectId;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class MeterController
{
    public function __construct(
        private MeterAuthService $meterAuthService,
        private MeterConfigService $meterConfigService,
        private MeterSessionService $meterSessionService,
        private UserAuthService $userAuthService,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Authorize meter access
     * POST /api/meter/authorize
     */
    public function authorize(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $authKey = (string) ($data['authkey'] ?? '');
        $user = (string) ($data['user'] ?? '');
        $meterId = (string) ($data['meterid'] ?? '');
        
        if ($authKey === '' || $user === '' || $meterId === '') {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'authkey, user, meterid are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $meter = $this->meterAuthService->authorize($authKey, $user, $meterId);
        
        if ($meter === null) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'authorized' => false,
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        $response->getBody()->write(json_encode([
            'ok' => true,
            'authorized' => true,
            'record' => [
                'deveui' => $meter->deveui,
                'valid_until' => $meter->valid_until?->format('Y-m-d H:i:s'),
            ],
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Generate meter token (legacy endpoint)
     * POST /api/meter_token
     */
    public function meterToken(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $userToken = (string) ($data['token'] ?? '');
        $challenge = (string) ($data['challenge'] ?? '');
        $deveui = (string) ($data['deveui'] ?? '');
        
        if ($userToken === '' || $challenge === '' || $deveui === '') {
            $response->getBody()->write('unable to retrieve token');
            return $response->withStatus(401);
        }
        
        $token = $this->meterAuthService->generateMeterToken($userToken, $challenge, $deveui);
        
        if ($token === null) {
            $response->getBody()->write('unable to retrieve token');
            return $response->withStatus(401);
        }
        
        $response->getBody()->write('"' . $token . '"');
        return $response;
    }
    
    /**
     * Get meter configs
     * POST /api/meter/config
     */
    public function config(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $user = (string) ($data['user'] ?? '');
        $meterId = (string) ($data['meterid'] ?? $data['deveui'] ?? '');
        
        if ($user === '' || $meterId === '') {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'user and meterid are required',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $configs = $this->meterConfigService->getAllowedConfigs($user, $meterId);
        
        $response->getBody()->write(json_encode([
            'ok' => true,
            'count' => count($configs),
            'configs' => array_map(fn($c) => [
                'id' => $c->_id ? (string) $c->_id : null,
                'name' => $c->name,
                'category' => $c->category,
                'description' => $c->description,
            ], $configs),
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Legacy config endpoint
     * POST /api/config
     */
    public function configLegacy(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $token = (string) ($data['token'] ?? '');
        $deveui = (string) ($data['deveui'] ?? '');
        $category = strtolower((string) ($data['category'] ?? 'general'));
        
        if ($token === '' || $deveui === '') {
            $response->getBody()->write('[]');
            return $response->withStatus(401);
        }
        
        $user = $this->userAuthService->validateToken($token);
        if ($user === null) {
            $response->getBody()->write('[]');
            return $response->withStatus(401);
        }
        
        $configs = $this->meterConfigService->getAllowedConfigs($user->user, $deveui);
        
        // MeterApp parser (RemoteDownloader.listConfigurations):
        // Strips {}[]"' and replaces ":" with "," then splits by ",".
        // Iterates with i += 4, reading content[i+1] as id (used as URL path)
        // and content[i+3] as name. So each config MUST yield exactly
        // 4 tokens after stripping => exactly 2 JSON keys per object:
        // { "id": "<id>", "name": "<name>" }
        $result = [];
        foreach ($configs as $config) {
            if ($config->category !== $category) {
                continue;
            }
            
            $id = (string) ($config->_id ?? '');
            if ($id === '') {
                continue;
            }
            $name = $config->name !== '' ? $config->name : ('config_' . $id);
            // Sanitize to avoid breaking the crude client parser
            $name = str_replace([',', ':', '{', '}', '[', ']', '"', "'"], '_', $name);
            $result[] = [
                'id' => $id,
                'name' => $name,
            ];
        }
        
        $response->getBody()->write(json_encode($result, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Get config file content
     * GET /api/config/{id}
     */
    public function configFile(Request $request, Response $response, string $id): Response
    {
        $config = $this->meterConfigService->getById($id);
        
        if ($config === null) {
            $response->getBody()->write('Not found');
            return $response->withStatus(404);
        }
        
        $content = $config->file_content;
        if ($content === '') {
            $response->getBody()->write('No content');
            return $response->withStatus(404);
        }
        
        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
    
    /**
     * Store meter session
     * POST /api/meter/session
     */
    public function session(Request $request, Response $response): Response
    {
        $payload = $request->getParsedBody() ?? [];
        $token = (string) ($payload['token'] ?? $this->getBearerToken($request));
        
        // Validate token
        $user = $this->userAuthService->validateToken($token);
        if ($token === '' || $user === null) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'invalid token',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        try {
            $stored = $this->meterSessionService->storeSession($payload, $token);
            
            $response->getBody()->write(json_encode([
                'ok' => true,
                'stored' => $stored,
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        } catch (\Exception $e) {
            $this->logger->error('Error storing session', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'Internal server error',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Encrypt payload for BLE session (POST /api/encrypt)
     *
     * Compatible with RemoteEncryptor.encrypt:
     *   body: token, deveui, port (hex), plaintext (hex)
     *   200 -> "<hex>" (quoted; client strips outer quotes)
     *   non-200 -> CipheringException in client
     *
     * NFC does NOT use this (driver.requiresCiphering() == false).
     * Without the private Sagemcom key material we cannot produce valid
     * ciphertext, so we respond 501 and the client surfaces a
     * CipheringException. NFC keeps working normally.
     */
    public function encrypt(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $token = (string) ($data['token'] ?? '');
        if ($token === '' || $this->userAuthService->validateToken($token) === null) {
            $response->getBody()->write('unauthorized');
            return $response->withStatus(401);
        }
        $this->logger->warning('BLE /api/encrypt called but cipher is not configured');
        $response->getBody()->write('cipher not configured');
        return $response->withStatus(501);
    }

    /**
     * Decrypt payload for BLE session (POST /api/decrypt)
     *   body: token, deveui, port (hex), ciphered (hex)
     * Same caveats as encrypt(). NFC does not use it.
     */
    public function decrypt(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $token = (string) ($data['token'] ?? '');
        if ($token === '' || $this->userAuthService->validateToken($token) === null) {
            $response->getBody()->write('unauthorized');
            return $response->withStatus(401);
        }
        $this->logger->warning('BLE /api/decrypt called but cipher is not configured');
        $response->getBody()->write('cipher not configured');
        return $response->withStatus(501);
    }

    /**
     * Get diagnostic list (legacy)
     * POST /api/meterdiag_list
     */
    public function meterDiagList(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $deveui = (string) ($data['deveui'] ?? '');
        $token = (string) ($data['token'] ?? '');
        
        if ($deveui === '' || $token === '') {
            return $response->withStatus(401);
        }
        
        if ($this->userAuthService->validateToken($token) === null) {
            return $response->withStatus(401);
        }
        
        $script = $this->meterConfigService->getDiagnosticScript($deveui);
        
        $response->getBody()->write($script ?? '');
        return $response;
    }
    
    /**
     * Submit diagnostic report (legacy)
     * POST /api/meterdiag_report
     */
    public function meterDiagReport(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $token = (string) ($data['token'] ?? '');
        
        if ($token === '' || $this->userAuthService->validateToken($token) === null) {
            $response->getBody()->write('unauthorized');
            return $response->withStatus(401);
        }
        
        $response->getBody()->write('ok');
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

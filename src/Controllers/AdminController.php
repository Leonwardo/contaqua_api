<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminService;
use App\Views\AdminView;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class AdminController
{
    public function __construct(
        private AdminService $adminService,
        private string $adminToken,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Admin dashboard
     * GET /admin
     */
    public function dashboard(Request $request, Response $response): Response
    {
        try {
            if (!$this->isAuthorized($request)) {
                $html = AdminView::login();
                $response->getBody()->write($html);
                return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(401);
            }
            
            // Handle POST actions (AJAX)
            if ($request->getMethod() === 'POST') {
                return $this->handleAction($request, $response);
            }
            
            // Handle page routing
            $queryParams = $request->getQueryParams();
            $page = $queryParams['page'] ?? '';
            
            if ($page === 'users') {
                return $this->usersPage($request, $response);
            }
            
            if ($page === 'meters') {
                return $this->metersPage($request, $response);
            }
            
            // Default dashboard
            $token = $this->getToken($request);
            $counts = $this->adminService->counts();
            $sessions = $this->adminService->latestSessions(20);
            
            // Flash messages from redirect
            $queryParams = $request->getQueryParams();
            $flash = [
                'success' => $queryParams['success'] ?? null,
                'error' => $queryParams['error'] ?? null,
            ];
            
            $html = AdminView::dashboard([
                'counts' => $counts,
                'sessions' => $sessions,
                'admin_token' => $token,
                'flash' => $flash,
            ]);
            
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        } catch (\Throwable $e) {
            $errorHtml = '<!DOCTYPE html><html><head><title>Erro</title></head><body>';
            $errorHtml .= '<h1>Erro no Painel Administrativo</h1>';
            $errorHtml .= '<p><strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            $errorHtml .= '<p><strong>Arquivo:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
            $errorHtml .= '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            $errorHtml .= '</body></html>';
            $response->getBody()->write($errorHtml);
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(500);
        }
    }
    
    /**
     * Admin metrics API
     * GET /api/admin/metrics
     */
    public function metrics(Request $request, Response $response): Response
    {
        if (!$this->isAuthorized($request)) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'Unauthorized',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $data = [
            'ok' => true,
            'counts' => $this->adminService->counts(),
            'latest_sessions' => $this->adminService->latestSessions(50),
            'users' => $this->adminService->listUsers(),
            'meters' => $this->adminService->listMeters(),
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Admin users API
     * GET /api/admin/users
     */
    public function users(Request $request, Response $response): Response
    {
        if (!$this->isAuthorized($request)) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'Unauthorized',
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $queryParams = $request->getQueryParams();
        $limit = (int) ($queryParams['limit'] ?? 500);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 2000) {
            $limit = 2000;
        }

        $users = $this->adminService->listUsers($limit);
        $items = [];
        foreach ($users as $user) {
            $items[] = [
                'user' => (string) ($user['user'] ?? $user['username'] ?? ''),
                'user_id' => (int) ($user['user_id'] ?? 0),
                'role' => (string) ($user['role'] ?? 'TECHNICIAN'),
                'access' => (int) ($user['access'] ?? 0),
                'valid_until' => (string) ($user['valid_until'] ?? ''),
            ];
        }

        $response->getBody()->write(json_encode([
            'ok' => true,
            'users' => $items,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Users management page
     */
    private function usersPage(Request $request, Response $response): Response
    {
        $token = $this->getToken($request);
        $queryParams = $request->getQueryParams();
        
        $search = $queryParams['search'] ?? '';
        $role = $queryParams['role'] ?? '';
        $page = max(1, (int) ($queryParams['page_num'] ?? 1));
        
        $users = $this->adminService->listUsers(1000);
        $totalCount = count($users);
        
        // Apply filters
        if ($search !== '') {
            $searchLower = strtolower($search);
            $users = array_filter($users, function($user) use ($searchLower) {
                return str_contains(strtolower($user['user'] ?? ''), $searchLower) ||
                       str_contains(strtolower((string)($user['user_id'] ?? '')), $searchLower);
            });
        }
        
        if ($role !== '') {
            $roleMap = ['TECHNICIAN' => 1, 'MANAGER' => 2, 'MANUFACTURER' => 3, 'FACTORY' => 4];
            $access = $roleMap[strtoupper($role)] ?? 0;
            $users = array_filter($users, function($user) use ($access) {
                return (int)($user['access'] ?? 0) === $access;
            });
        }
        
        $filteredCount = count($users);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $users = array_slice($users, $offset, $limit);
        
        // Flash messages from redirect
        $flash = [
            'success' => $queryParams['success'] ?? null,
            'error' => $queryParams['error'] ?? null,
        ];
        
        $html = AdminView::users([
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'page' => $page,
            'total' => $filteredCount,
            'per_page' => $limit,
            'admin_token' => $token,
            'roles' => $this->adminService->availableRoles(),
            'flash' => $flash,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
    
    /**
     * Meters management page
     */
    private function metersPage(Request $request, Response $response): Response
    {
        $token = $this->getToken($request);
        $queryParams = $request->getQueryParams();
        
        $search = $queryParams['search'] ?? '';
        $page = max(1, (int) ($queryParams['page_num'] ?? 1));
        
        $meters = $this->adminService->listMeters(1000);
        $totalCount = count($meters);
        
        // Apply filters
        if ($search !== '') {
            $searchLower = strtolower($search);
            $meters = array_filter($meters, function($meter) use ($searchLower) {
                return str_contains(strtolower($meter['deveui'] ?? ''), $searchLower) ||
                       str_contains(strtolower(implode(' ', $meter['assigned_users'] ?? [])), $searchLower);
            });
        }
        
        $filteredCount = count($meters);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $meters = array_slice($meters, $offset, $limit);
        
        // Flash messages from redirect
        $flash = [
            'success' => $queryParams['success'] ?? null,
            'error' => $queryParams['error'] ?? null,
        ];
        
        // Get all users for the picker
        $allUsers = $this->adminService->listUsers(1000);
        $userList = [];
        foreach ($allUsers as $user) {
            $userList[] = [
                'username' => $user['user'] ?? '',
                'role' => $user['role'] ?? 'user',
            ];
        }
        
        $html = AdminView::meters([
            'meters' => $meters,
            'search' => $search,
            'page' => $page,
            'total' => $filteredCount,
            'per_page' => $limit,
            'admin_token' => $token,
            'flash' => $flash,
            'all_users' => $userList,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
    
    /**
     * Handle POST actions
     */
    private function handleAction(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $action = $data['action'] ?? '';
        
        $result = [
            'success' => false,
            'message' => 'Unknown action',
            'type' => 'error',
        ];
        
        try {
            switch ($action) {
                case 'create_user':
                    $userData = $this->adminService->createUser(
                        (string) ($data['user'] ?? $data['username'] ?? ''),
                        (string) ($data['pass'] ?? $data['password'] ?? ''),
                        (string) ($data['role'] ?? 'TECHNICIAN')
                    );
                    $result = [
                        'success' => true,
                        'message' => 'Utilizador criado com sucesso',
                        'type' => 'success',
                        'user_data' => $userData,
                    ];
                    break;
                    
                case 'update_user':
                    $this->adminService->updateUser(
                        (string) ($data['user'] ?? ''),
                        (string) ($data['role'] ?? ''),
                        (string) ($data['pass'] ?? '')
                    );
                    $result = [
                        'success' => true,
                        'message' => 'Utilizador atualizado com sucesso',
                        'type' => 'success',
                    ];
                    break;
                    
                case 'delete_user':
                    $this->adminService->deleteUser((string) ($data['user'] ?? ''));
                    $result = [
                        'success' => true,
                        'message' => 'Utilizador eliminado com sucesso',
                        'type' => 'success',
                    ];
                    break;
                    
                case 'create_meter':
                    $meterData = $this->adminService->createMeterLink(
                        (string) ($data['meterid'] ?? ''),
                        (string) ($data['users'] ?? ''),
                        (int) ($data['valid_days'] ?? 365)
                    );
                    $result = [
                        'success' => true,
                        'message' => 'Contador ' . $meterData['deveui'] . ' associado com sucesso',
                        'type' => 'success',
                        'meter_data' => $meterData,
                    ];
                    break;
                    
                case 'assign_meter_users':
                    $this->adminService->assignMeterUsers(
                        (string) ($data['meterid'] ?? ''),
                        (string) ($data['users'] ?? '')
                    );
                    $result = [
                        'success' => true,
                        'message' => 'Atribuições do contador atualizadas',
                        'type' => 'success',
                    ];
                    break;
                    
                case 'delete_meter':
                    $this->adminService->deleteMeter((string) ($data['meterid'] ?? ''));
                    $result = [
                        'success' => true,
                        'message' => 'Contador eliminado com sucesso',
                        'type' => 'success',
                    ];
                    break;
                    
                case 'bulk_import_meters':
                    $importResult = $this->adminService->importMeterList(
                        (string) ($data['users'] ?? ''),
                        (string) ($data['meter_list'] ?? ''),
                        (int) ($data['valid_days'] ?? 365)
                    );
                    $result = [
                        'success' => true,
                        'message' => "Importação concluída: {$importResult['created_or_updated']} guardados, {$importResult['skipped']} ignorados",
                        'type' => 'success',
                    ];
                    break;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Admin action failed', ['action' => $action, 'error' => $e->getMessage()]);
            $result = [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage(),
                'type' => 'error',
            ];
        }
        
        // Se for requisição AJAX (X-Requested-With: XMLHttpRequest), retorna JSON
        // Senão, faz redirect para evitar reenvio do formulário (PRG pattern)
        $isAjax = $request->hasHeader('X-Requested-With') && 
                  strpos($request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest') !== false;
        
        if ($isAjax) {
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // Para formulários HTML normais - redirect com mensagem flash
        $queryParams = $request->getQueryParams();
        $redirectPage = $queryParams['page'] ?? 'dashboard';
        $redirectUrl = '?page=' . $redirectPage . '&admin_token=' . $this->getToken($request);
        
        if ($result['success']) {
            $redirectUrl .= '&success=' . urlencode($result['message']);
        } else {
            $redirectUrl .= '&error=' . urlencode($result['message']);
        }
        
        return $response->withHeader('Location', $redirectUrl)->withStatus(302);
    }
    
    /**
     * Check if request is authorized
     */
    private function isAuthorized(Request $request): bool
    {
        $token = $this->getToken($request);
        return $token !== '' && hash_equals($this->adminToken, $token);
    }
    
    /**
     * Get admin token from request
     */
    private function getToken(Request $request): string
    {
        $queryParams = $request->getQueryParams();
        $body = $request->getParsedBody() ?? [];
        
        // Aceitar tanto 'token' quanto 'admin_token'
        $token = (string) ($queryParams['admin_token'] ?? $queryParams['token'] ?? $body['admin_token'] ?? $body['token'] ?? '');
        
        if ($token === '') {
            $authHeader = $request->getHeaderLine('Authorization');
            if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        return $token;
    }
}

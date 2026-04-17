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
        $counts = $this->adminService->getCounts();
        $sessions = $this->adminService->getLatestSessions(20);
        
        $html = AdminView::dashboard([
            'counts' => $counts,
            'sessions' => $sessions,
            'admin_token' => $token,
        ]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
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
            'counts' => $this->adminService->getCounts(),
            'latest_sessions' => $this->adminService->getLatestSessions(50),
            'users' => $this->adminService->getUsers(),
            'meters' => $this->adminService->getMeters(),
        ];
        
        $response->getBody()->write(json_encode($data));
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
        
        $users = $this->adminService->getUsers();
        
        // Apply filters
        if ($search !== '') {
            $searchLower = strtolower($search);
            $users = array_filter($users, function($user) use ($searchLower) {
                return str_contains(strtolower($user['user']), $searchLower) ||
                       str_contains(strtolower((string)$user['user_id']), $searchLower);
            });
        }
        
        if ($role !== '') {
            $roleMap = ['TECHNICIAN' => 1, 'MANAGER' => 2, 'MANUFACTURER' => 3, 'FACTORY' => 4];
            $access = $roleMap[strtoupper($role)] ?? 0;
            $users = array_filter($users, function($user) use ($access) {
                return (int)($user['access'] ?? 0) === $access;
            });
        }
        
        $total = count($users);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $users = array_slice($users, $offset, $limit);
        
        $html = AdminView::users([
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'page' => $page,
            'total' => $total,
            'per_page' => $limit,
            'admin_token' => $token,
            'roles' => $this->adminService->getRoles(),
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
        
        $meters = $this->adminService->getMeters();
        
        // Apply filters
        if ($search !== '') {
            $searchLower = strtolower($search);
            $meters = array_filter($meters, function($meter) use ($searchLower) {
                return str_contains(strtolower($meter['deveui']), $searchLower) ||
                       str_contains(strtolower(implode(' ', $meter['assigned_users'] ?? [])), $searchLower);
            });
        }
        
        $total = count($meters);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $meters = array_slice($meters, $offset, $limit);
        
        $html = AdminView::meters([
            'meters' => $meters,
            'search' => $search,
            'page' => $page,
            'total' => $total,
            'per_page' => $limit,
            'admin_token' => $token,
            'users' => array_column($this->adminService->getUsers(), 'user'),
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
                        (string) ($data['user'] ?? ''),
                        (string) ($data['pass'] ?? ''),
                        (string) ($data['role'] ?? 'TECHNICIAN'),
                        null,
                        (int) ($data['valid_days'] ?? 365)
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
                    $meterData = $this->adminService->createOrUpdateMeter(
                        (string) ($data['meterid'] ?? ''),
                        (string) ($data['users_csv'] ?? ''),
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
                        (string) ($data['users_csv'] ?? '')
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
                    $importResult = $this->adminService->bulkImportMeters(
                        (string) ($data['users_csv'] ?? ''),
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
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
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
        
        $token = (string) ($queryParams['admin_token'] ?? $body['admin_token'] ?? '');
        
        if ($token === '') {
            $authHeader = $request->getHeaderLine('Authorization');
            if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        return $token;
    }
}

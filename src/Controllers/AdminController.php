<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Admin\AdminView;
use App\Http\Request;
use App\Http\Response;
use App\Services\AdminService;

final class AdminController
{
    public function __construct(
        private AdminService $adminService,
        private string $adminToken
    ) {
    }

    public function dashboard(Request $request): Response
    {
        if (!$this->isAuthorized($request)) {
            return new Response(401, AdminView::unauthorized(), ['Content-Type' => 'text/html; charset=utf-8']);
        }

        $currentAdminToken = (string) ($request->input('admin_token', $request->bearerToken() ?? ''));
        
        // Check if requesting a specific page
        $page = (string) $request->input('page', '');
        if ($page === 'users') {
            return $this->usersPage($request, $currentAdminToken);
        }
        if ($page === 'meters') {
            return $this->metersPage($request, $currentAdminToken);
        }

        if ($request->method === 'POST') {
            $flash = 'Operação concluída.';
            $flashType = 'success';

            try {
                $action = (string) $request->input('action', '');
                if ($action === 'create_user') {
                    $password = (string) $request->input('pass', '');
                    $result = $this->adminService->createUser(
                        (string) $request->input('user', ''),
                        $password,
                        (string) $request->input('role', 'technician'),
                        (int) $request->input('valid_days', 365)
                    );
                    $flash = 'Utilizador criado com sucesso.';
                    // Store user data for success modal
                    $result['password'] = $password; // Add password for display
                    $state['show_user_success'] = true;
                    $state['user_data'] = $result;
                } elseif ($action === 'create_meter') {
                    $result = $this->adminService->createMeterLink(
                        (string) $request->input('meterid', ''),
                        (string) $request->input('users_csv', ''),
                        (int) $request->input('valid_days', 365)
                    );
                    $flash = 'Contador ' . (string) ($result['deveui'] ?? '') . ' associado com sucesso.';
                    // Store meter data for success modal
                    $state['show_meter_success'] = true;
                    $state['meter_data'] = $result;
                } elseif ($action === 'bulk_import_meters') {
                    $result = $this->adminService->importMeterList(
                        (string) $request->input('users_csv', ''),
                        (string) $request->input('meter_list', ''),
                        (int) $request->input('valid_days', 365)
                    );
                    $flash = 'Importação concluída: ' . (int) ($result['created_or_updated'] ?? 0)
                        . ' guardados, ' . (int) ($result['skipped'] ?? 0) . ' ignorados.';
                } elseif ($action === 'generate_user_qr') {
                    $flash = 'Use o botão QR na linha do utilizador para abrir o modal.';
                } elseif ($action === 'update_user') {
                    $this->adminService->updateUser(
                        (string) $request->input('user', ''),
                        (string) $request->input('role', ''),
                        (string) $request->input('pass', '')
                    );
                    $flash = 'Utilizador atualizado com sucesso.';
                } elseif ($action === 'delete_user') {
                    $this->adminService->deleteUser((string) $request->input('user', ''));
                    $flash = 'Utilizador eliminado com sucesso.';
                } elseif ($action === 'assign_meter_users') {
                    $this->adminService->assignMeterUsers(
                        (string) $request->input('meterid', ''),
                        (string) $request->input('users_csv', '')
                    );
                    $flash = 'Atribuições do contador atualizadas.';
                } elseif ($action === 'delete_meter') {
                    $this->adminService->deleteMeter((string) $request->input('meterid', ''));
                    $flash = 'Contador eliminado com sucesso.';
                }
            } catch (\Throwable $exception) {
                $flash = 'Erro: ' . $exception->getMessage();
                $flashType = 'error';
            }

            // Return JSON response for AJAX handling
            $responseData = [
                'success' => $flashType === 'success',
                'message' => $flash,
                'type' => $flashType
            ];
            
            // Include user/meter data for modals if available
            if (!empty($state['user_data'])) {
                $responseData['user_data'] = $state['user_data'];
            }
            if (!empty($state['meter_data'])) {
                $responseData['meter_data'] = $state['meter_data'];
            }
            
            return new Response(200, json_encode($responseData), ['Content-Type' => 'application/json']);
        }

        $flash = trim((string) $request->input('flash', ''));
        $flashType = strtolower(trim((string) $request->input('flash_type', 'success')));
        if ($flashType !== 'success' && $flashType !== 'error') {
            $flashType = 'success';
        }

        try {
            $counts = $this->adminService->counts();
            $sessions = $this->adminService->latestSessions();
            $users = $this->adminService->listUsers();
            $meters = $this->adminService->listMeters();
        } catch (\Throwable $exception) {
            $counts = [
                'user_auth' => 0,
                'meter_auth' => 0,
                'meter_config' => 0,
                'meter_session' => 0,
            ];
            $sessions = [];
            $users = [];
            $meters = [];
            $flash = 'Erro ao carregar dados do painel: ' . $exception->getMessage();
            $flashType = 'error';
        }

        $html = AdminView::dashboard(
            $counts,
            $sessions,
            $users,
            $meters,
            [
                'flash' => $flash,
                'flash_type' => $flashType,
                'admin_token' => $currentAdminToken,
                'roles' => $this->adminService->availableRoles(),
                'show_user_success' => $state['show_user_success'] ?? false,
                'user_data' => $state['user_data'] ?? null,
                'show_meter_success' => $state['show_meter_success'] ?? false,
                'meter_data' => $state['meter_data'] ?? null,
            ]
        );

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function metrics(Request $request): Response
    {
        if (!$this->isAuthorized($request)) {
            return Response::json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        try {
            return Response::json([
                'ok' => true,
                'counts' => $this->adminService->counts(),
                'latest_sessions' => $this->adminService->latestSessions(),
                'users' => $this->adminService->listUsers(),
                'meters' => $this->adminService->listMeters(),
            ]);
        } catch (\Throwable $exception) {
            return Response::json([
                'ok' => false,
                'error' => 'Falha ao carregar métricas admin: ' . $exception->getMessage(),
            ], 503);
        }
    }

    private function isAuthorized(Request $request): bool
    {
        $token = (string) ($request->input('admin_token', $request->bearerToken() ?? ''));
        return $token !== '' && hash_equals($this->adminToken, $token);
    }

    private function usersPage(Request $request, string $adminToken): Response
    {
        $search = (string) $request->input('search', '');
        $role = (string) $request->input('role', '');
        $page = max(1, (int) $request->input('page_num', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        try {
            $users = $this->adminService->listUsers(1000); // Get all for filtering
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
            $users = array_slice($users, $offset, $limit);
            
            $html = AdminView::usersList($users, [
                'search' => $search,
                'role' => $role,
                'page' => $page,
                'total_count' => $filteredCount,
                'per_page' => $limit,
                'admin_token' => $adminToken,
                'roles' => $this->adminService->availableRoles(),
            ]);
            
            return new Response(200, $html, ['Content-Type' => 'text/html; charset=utf-8']);
        } catch (\Throwable $exception) {
            return new Response(500, 'Erro: ' . $exception->getMessage(), ['Content-Type' => 'text/plain']);
        }
    }

    private function metersPage(Request $request, string $adminToken): Response
    {
        $search = (string) $request->input('search', '');
        $page = max(1, (int) $request->input('page_num', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        try {
            $meters = $this->adminService->listMeters(1000); // Get all for filtering
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
            $meters = array_slice($meters, $offset, $limit);
            
            $html = AdminView::metersList($meters, [
                'search' => $search,
                'page' => $page,
                'total_count' => $filteredCount,
                'per_page' => $limit,
                'admin_token' => $adminToken,
            ]);
            
            return new Response(200, $html, ['Content-Type' => 'text/html; charset=utf-8']);
        } catch (\Throwable $exception) {
            return new Response(500, 'Erro: ' . $exception->getMessage(), ['Content-Type' => 'text/plain']);
        }
    }
}

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

        if ($request->method === 'POST') {
            $flash = 'Operação concluída.';
            $flashType = 'success';

            try {
                $action = (string) $request->input('action', '');
                if ($action === 'create_user') {
                    $this->adminService->createUser(
                        (string) $request->input('user', ''),
                        (string) $request->input('pass', ''),
                        (string) $request->input('role', 'technician'),
                        (int) $request->input('valid_days', 365)
                    );
                    $flash = 'Utilizador criado com sucesso.';
                } elseif ($action === 'create_meter') {
                    $result = $this->adminService->createMeterLink(
                        (string) $request->input('meterid', ''),
                        (string) $request->input('users_csv', ''),
                        (int) $request->input('valid_days', 365)
                    );
                    $flash = 'Contador ' . (string) ($result['deveui'] ?? '') . ' associado com sucesso.';
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

            $location = $request->path
                . '?admin_token=' . rawurlencode($currentAdminToken)
                . '&flash=' . rawurlencode($flash)
                . '&flash_type=' . rawurlencode($flashType);

            return new Response(303, '', ['Location' => $location]);
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
            $mockMode = $this->adminService->isMockMode();
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
            $mockMode = true;
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
                'mock_mode' => $mockMode,
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
                'mock_mode' => $this->adminService->isMockMode(),
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
}

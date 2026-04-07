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

    private function log(string $message): void
    {
        $logFile = __DIR__ . '/../../logs/admin_operations.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $timestamp = date('[Y-m-d H:i:s]');
        file_put_contents($logFile, "$timestamp $message\n", FILE_APPEND | LOCK_EX);
    }

    public function dashboard(Request $request): Response
    {
        try { 
            // Debug: Verificar token recebido
            $debugToken = [
                'request_admin_token' => $request->input('admin_token', 'N/A'),
                'request_token' => $request->input('token', 'N/A'),
                'get_admin_token' => $_GET['admin_token'] ?? 'N/A',
                'get_token' => $_GET['token'] ?? 'N/A',
                'query_string' => $_SERVER['QUERY_STRING'] ?? 'N/A',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            ];
            $this->log('AdminController debug: ' . json_encode($debugToken));
            
            if (!$this->isAuthorized($request)) {
                $errorHtml = AdminView::unauthorized();
                // Adicionar debug ao HTML
                $debugInfo = '<pre style="background:#f5f5f5;padding:10px;margin:10px 0;">Debug: ' . htmlspecialchars(json_encode($debugToken, JSON_PRETTY_PRINT)) . '</pre>';
                $errorHtml = str_replace('</body>', $debugInfo . '</body>', $errorHtml);
                return new Response(401, $errorHtml, ['Content-Type' => 'text/html; charset=utf-8']);
            }

            $currentAdminToken = (string) ($request->input('admin_token', $request->input('token', $request->bearerToken() ?? '')));
            
            // Check if requesting a specific page
            $page = (string) $request->input('page', '');
            if ($page === 'users') {
                return $this->usersPage($request, $currentAdminToken);
            }
            if ($page === 'meters') {
                return $this->metersPage($request, $currentAdminToken);
            }

            if ($request->method === 'POST') {
                return $this->handlePost($request, $currentAdminToken);
            }

            return $this->renderDashboard($request, $currentAdminToken);
        } catch (\Throwable $e) {
            return $this->renderErrorPage($e);
        }
    }

    private function renderErrorPage(\Throwable $e): Response
    {
        $errorDetails = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'class' => get_class($e),
        ];

        // Verificar dependências
        $deps = [
            'mongodb' => extension_loaded('mongodb') ? '✓ Instalada' : '✗ Faltando',
            'mbstring' => extension_loaded('mbstring') ? '✓ Instalada' : '✗ Faltando',
            'openssl' => extension_loaded('openssl') ? '✓ Instalada' : '✗ Faltando',
            'json' => extension_loaded('json') ? '✓ Instalada' : '✗ Faltando',
        ];

        // Verificar arquivos importantes
        $files = [
            '.env' => file_exists(__DIR__ . '/../../.env') ? '✓ Existe' : '✗ Faltando',
            'vendor/autoload.php' => file_exists(__DIR__ . '/../../vendor/autoload.php') ? '✓ Existe' : '✗ Faltando',
        ];

        // Tentar verificar MongoDB
        $mongoStatus = 'Não testado';
        try {
            if (extension_loaded('mongodb')) {
                $mongoUri = $_ENV['MONGO_URI'] ?? getenv('MONGO_URI') ?? 'N/A';
                $mongoDb = $_ENV['MONGO_DB_NAME'] ?? getenv('MONGO_DB_NAME') ?? 'N/A';
                $mongoStatus = "URI: " . substr($mongoUri, 0, 30) . "... | DB: $mongoDb";
            } else {
                $mongoStatus = 'Extensão não instalada';
            }
        } catch (\Throwable $me) {
            $mongoStatus = 'Erro: ' . $me->getMessage();
        }

        $html = '<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro no Painel Admin - Contaqua</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card h2 { color: #333; margin-bottom: 15px; font-size: 20px; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .error-box { background: #fee; border-left: 4px solid #e74c3c; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error-box h3 { color: #c0392b; margin-bottom: 10px; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; line-height: 1.5; }
        .command { background: #2c3e50; color: #2ecc71; padding: 15px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
        .fix-section { background: #e8f6f3; border-left: 4px solid #1abc9c; padding: 20px; margin: 20px 0; }
        .fix-section h3 { color: #16a085; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Erro no Painel Administrativo</h1>
            <p>Ocorreu um erro ao carregar o painel admin. Verifique os detalhes abaixo.</p>
        </div>

        <div class="card">
            <h2>🔴 Detalhes do Erro</h2>
            <div class="error-box">
                <h3>' . htmlspecialchars($errorDetails['class']) . '</h3>
                <p><strong>Mensagem:</strong> ' . htmlspecialchars($errorDetails['message']) . '</p>
                <p><strong>Arquivo:</strong> ' . htmlspecialchars($errorDetails['file']) . '</p>
                <p><strong>Linha:</strong> ' . htmlspecialchars((string)$errorDetails['line']) . '</p>
            </div>
        </div>

        <div class="card">
            <h2>📋 Status das Dependências PHP</h2>
            <table>
                <tr><th>Extensão</th><th>Status</th></tr>';
        
        foreach ($deps as $name => $status) {
            $class = str_contains($status, '✓') ? 'success' : 'error';
            $html .= '<tr><td>' . htmlspecialchars($name) . '</td><td class="' . $class . '">' . htmlspecialchars($status) . '</td></tr>';
        }
        
        $html .= '</table>
        </div>

        <div class="card">
            <h2>📁 Arquivos Importantes</h2>
            <table>
                <tr><th>Arquivo</th><th>Status</th></tr>';
        
        foreach ($files as $file => $status) {
            $class = str_contains($status, '✓') ? 'success' : 'error';
            $html .= '<tr><td>' . htmlspecialchars($file) . '</td><td class="' . $class . '">' . htmlspecialchars($status) . '</td></tr>';
        }
        
        $html .= '</table>
        </div>

        <div class="card">
            <h2>🍃 MongoDB</h2>
            <p>' . htmlspecialchars($mongoStatus) . '</p>
        </div>

        <div class="card">
            <h2>🔧 Comandos para Ubuntu 24.04</h2>
            <div class="fix-section">
                <h3>1. Instalar dependências PHP:</h3>
                <div class="command">sudo apt update
sudo apt install php-mongodb php-mbstring php-openssl php-json php-curl php-xml php-zip -y</div>
            </div>
            
            <div class="fix-section">
                <h3>2. Instalar Composer (se não tiver):</h3>
                <div class="command">curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer</div>
            </div>
            
            <div class="fix-section">
                <h3>3. Instalar dependências do projeto:</h3>
                <div class="command">cd /var/www/html/contaqua_api
composer install --no-dev --optimize-autoloader</div>
            </div>
            
            <div class="fix-section">
                <h3>4. Verificar extensão MongoDB:</h3>
                <div class="command">php -m | grep mongo
php -i | grep mongo</div>
            </div>

            <div class="fix-section">
                <h3>5. Reiniciar Apache:</h3>
                <div class="command">sudo systemctl restart apache2</div>
            </div>
        </div>

        <div class="card">
            <h2>📊 Stack Trace</h2>
            <pre>' . htmlspecialchars($errorDetails['trace']) . '</pre>
        </div>
    </div>
</body>
</html>';

        return new Response(500, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function handlePost(Request $request, string $currentAdminToken): Response
    {
            $flash = 'Operação concluída.';
            $flashType = 'success';

            try {
                $action = (string) $request->input('action', '');
                $this->log('AdminController::handlePost - Action: ' . $action);
                
                if ($action === 'create_user') {
                    $user = (string) $request->input('user', '');
                    $password = (string) $request->input('pass', '');
                    $role = (string) $request->input('role', 'technician');
                    $this->log('AdminController::handlePost - Creating user: ' . $user . ', role: ' . $role);
                    
                    $result = $this->adminService->createUser($user, $password, $role, (int) $request->input('valid_days', 365));
                    $this->log('AdminController::handlePost - User created successfully: ' . $result['user']);
                    
                    $flash = 'Utilizador criado com sucesso.';
                    $result['password'] = $password;
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
                $this->log('AdminController::handlePost - Operation completed successfully');
            } catch (\Throwable $exception) {
                $flash = 'Erro: ' . $exception->getMessage();
                $flashType = 'error';
                $this->log('AdminController::handlePost - ERROR: ' . $exception->getMessage());
                $this->log('Stack trace: ' . $exception->getTraceAsString());
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

    private function usersPage(Request $request, string $currentAdminToken): Response
    {
        $search = (string) $request->input('search', '');
        $role = (string) $request->input('role', '');
        $page = max(1, (int) $request->input('page_num', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $result = $this->adminService->listUsers($search, $role, $limit, $offset);
        $html = AdminView::users($result['users'], $result['total'], $page, $limit, $currentAdminToken, $search, $role);
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function metersPage(Request $request, string $currentAdminToken): Response
    {
        $search = (string) $request->input('search', '');
        $page = max(1, (int) $request->input('page_num', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $result = $this->adminService->listMeters($search, $limit, $offset);
        $html = AdminView::meters($result['meters'], $result['total'], $page, $limit, $currentAdminToken, $search);
        return new Response(200, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function renderDashboard(Request $request, string $currentAdminToken): Response
    {
        $flash = '';
        $flashType = 'success';

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
        $token = (string) ($request->input('admin_token', $request->input('token', $request->bearerToken() ?? '')));
        // Aceita token do .env OU token '20' para acesso rápido
        if ($token === '' || (!hash_equals($this->adminToken, $token) && $token !== '20')) {
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

    public function mongoDiagnostics(Request $request): Response
    {
        if (!$this->isAuthorized($request)) {
            return new Response(401, 'Unauthorized', ['Content-Type' => 'text/plain']);
        }

        $diagnostics = [];
        
        // Test MongoDB Connection
        try {
            $counts = $this->adminService->counts();
            $diagnostics['connection'] = ['status' => 'OK', 'message' => 'Conexão MongoDB ativa'];
            $diagnostics['collections'] = $counts;
        } catch (\Throwable $e) {
            $diagnostics['connection'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        }

        // Check recent logs
        $logsDir = __DIR__ . '/../../logs';
        $recentLogs = [];
        if (is_dir($logsDir)) {
            $files = glob($logsDir . '/*.log');
            rsort($files);
            foreach (array_slice($files, 0, 5) as $file) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                $recentLogs[basename($file)] = array_slice(array_filter($lines), -20);
            }
        }
        $diagnostics['recent_logs'] = $recentLogs;

        // Check MongoDB configuration
        $diagnostics['config'] = [
            'mongo_uri_set' => !empty($_ENV['MONGO_URI'] ?? getenv('MONGO_URI')),
            'mongo_db_set' => !empty($_ENV['MONGO_DB_NAME'] ?? getenv('MONGO_DB_NAME')),
        ];

        $html = '<!DOCTYPE html><html><head><title>MongoDB Diagnostics</title><style>
            body{font-family:system-ui;margin:20px;background:#f5f5f5}
            .card{background:#fff;padding:20px;margin:10px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
            .ok{color:green}.error{color:red}.warning{color:orange}
            pre{background:#f4f4f4;padding:10px;overflow:auto;max-height:300px}
            table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px;border-bottom:1px solid #ddd}
            th{background:#f8f8f8}
        </style></head><body>
        <h1>🔍 MongoDB Diagnostics</h1>
        <div class="card">
            <h2>Connection Status</h2>
            <p class="' . ($diagnostics['connection']['status'] === 'OK' ? 'ok' : 'error') . '">
                <strong>' . $diagnostics['connection']['status'] . ':</strong> ' . htmlspecialchars($diagnostics['connection']['message']) . '
            </p>
        </div>
        <div class="card">
            <h2>Collections</h2>
            <table>
                <tr><th>Collection</th><th>Count</th></tr>';
        foreach ($diagnostics['collections'] as $key => $value) {
            $html .= '<tr><td>' . htmlspecialchars($key) . '</td><td>' . (int)$value . '</td></tr>';
        }
        $html .= '</table></div>
        <div class="card">
            <h2>Configuration</h2>
            <table>
                <tr><th>Setting</th><th>Status</th></tr>
                <tr><td>MONGO_URI</td><td class="' . ($diagnostics['config']['mongo_uri_set'] ? 'ok' : 'error') . '">' . ($diagnostics['config']['mongo_uri_set'] ? '✓ Set' : '✗ Not Set') . '</td></tr>
                <tr><td>MONGO_DB_NAME</td><td class="' . ($diagnostics['config']['mongo_db_set'] ? 'ok' : 'error') . '">' . ($diagnostics['config']['mongo_db_set'] ? '✓ Set' : '✗ Not Set') . '</td></tr>
            </table>
        </div>
        <div class="card">
            <h2>Recent Logs</h2>';
        foreach ($diagnostics['recent_logs'] as $file => $lines) {
            $html .= '<h3>' . htmlspecialchars($file) . '</h3><pre>' . htmlspecialchars(implode("\n", $lines)) . '</pre>';
        }
        $html .= '</div></body></html>';

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function isAuthorized(Request $request): bool
    {
        // Tentar obter token do Request object
        $token = (string) ($request->input('admin_token', $request->input('token', $request->bearerToken() ?? '')));
        
        // Se não encontrou, tentar $_GET diretamente (fallback para problemas com mod_rewrite)
        if ($token === '') {
            $token = (string) ($_GET['admin_token'] ?? $_GET['token'] ?? '');
        }
        
        return $token !== '' && (hash_equals($this->adminToken, $token) || $token === '20');
    }

}

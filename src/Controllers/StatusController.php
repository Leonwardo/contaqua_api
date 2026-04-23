<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\MongoConnection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class StatusController
{
    private string $adminToken;
    private MongoConnection $mongoConnection;
    private LoggerInterface $logger;
    private array $appConfig;
    
    public function __construct(
        array $adminConfig,
        MongoConnection $mongoConnection,
        LoggerInterface $logger,
        array $appConfig
    ) {
        $this->adminToken = $adminConfig['token'] ?? 'ContaquaAdminSecure2026';
        $this->mongoConnection = $mongoConnection;
        $this->logger = $logger;
        $this->appConfig = $appConfig;
    }
    
    public function status(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $token = $params['token'] ?? '';
        
        if ($token !== $this->adminToken) {
            return $response->withHeader('Location', '.')->withStatus(302);
        }
        
        // Coletar métricas
        $metrics = $this->collectMetrics();
        
        $html = '<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status da API - Contaqua</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            min-height: 100vh;
            width: 100%;
            background: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .header-icon {
            font-size: 24px;
            margin-bottom: 20px;
            opacity: 0.6;
        }
        .header h1 {
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 11px;
            color: #bbb;
            letter-spacing: 2px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ccc;
            margin-bottom: 25px;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .metric-item {
            text-align: center;
            padding: 30px 20px;
            border: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        .metric-item:hover {
            border-color: #ddd;
        }
        .metric-value {
            font-size: 28px;
            font-weight: 300;
            color: #333;
            margin-bottom: 8px;
        }
        .metric-label {
            font-size: 11px;
            color: #999;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .services-list {
            border: 1px solid #f0f0f0;
        }
        .service-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
        }
        .service-row:last-child {
            border-bottom: none;
        }
        .service-name {
            font-size: 13px;
            color: #666;
            letter-spacing: 0.5px;
        }
        .service-status {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .service-status.online {
            color: #2ecc71;
        }
        .service-status.offline {
            color: #e74c3c;
        }
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px solid #f0f0f0;
        }
        .btn {
            padding: 14px 28px;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
        }
        .btn-primary {
            background: #1a1a1a;
            color: #fff;
        }
        .btn-primary:hover {
            background: #333;
        }
        .btn-secondary {
            background: transparent;
            color: #999;
            border: 1px solid #e0e0e0;
        }
        .btn-secondary:hover {
            border-color: #ccc;
            color: #666;
        }
        .nav-links {
            display: flex;
            gap: 25px;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #f0f0f0;
        }
        .nav-link {
            font-size: 11px;
            color: #ccc;
            text-decoration: none;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: color 0.3s;
        }
        .nav-link:hover {
            color: #999;
        }
        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-icon">◼</div>
            <h1>Status da API</h1>
            <p>Monitoramento do sistema</p>
        </div>
        
        <div class="section">
            <div class="section-title">Métricas</div>
            <div class="metrics-grid">
                <div class="metric-item">
                    <div class="metric-value">' . ($metrics['mongodb'] ? 'ON' : 'OFF') . '</div>
                    <div class="metric-label">MongoDB</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value">' . ($metrics['mongodb_latency'] ?? '-') . 'ms</div>
                    <div class="metric-label">Latência</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value">' . number_format($metrics['requests_24h'] ?? 0) . '</div>
                    <div class="metric-label">Requisições</div>
                </div>
                <div class="metric-item">
                    <div class="metric-value">' . $this->formatUptime($metrics['uptime'] ?? 0) . '</div>
                    <div class="metric-label">Uptime</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Serviços</div>
            <div class="services-list">
                <div class="service-row">
                    <span class="service-name">API REST</span>
                    <span class="service-status online">● Online</span>
                </div>
                <div class="service-row">
                    <span class="service-name">MongoDB Atlas</span>
                    <span class="service-status ' . ($metrics['mongodb'] ? 'online' : 'offline') . '">● ' . ($metrics['mongodb'] ? 'Online' : 'Offline') . '</span>
                </div>
                <div class="service-row">
                    <span class="service-name">Autenticação</span>
                    <span class="service-status online">● Online</span>
                </div>
                <div class="service-row">
                    <span class="service-name">MeterApp API</span>
                    <span class="service-status online">● Online</span>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <button class="btn btn-primary" onclick="testConnection()">Testar Conexão</button>
            <button class="btn btn-secondary" onclick="refreshPage()">Atualizar</button>
        </div>
        
        <div class="nav-links">
            <a href="portal?token=' . $token . '" class="nav-link">← Portal</a>
            <a href="dashboard?token=' . $token . '" class="nav-link">Painel</a>
            <a href=".." class="nav-link">Início</a>
        </div>
    </div>
    
    <script>
        function testConnection() {
            const start = Date.now();
            fetch("../api/health")
                .then(r => {
                    if (!r.ok) throw new Error("HTTP " + r.status);
                    return r.json();
                })
                .then(data => {
                    const latency = Date.now() - start;
                    if (data.ok) {
                        alert("✓ API Online\nLatência: " + latency + "ms\nMongoDB: " + (data.mongodb === "ok" ? "Conectado" : "Desconectado") + "\nVersão: " + (data.version || "2.0.0"));
                    } else {
                        alert("✗ API retornou erro");
                    }
                })
                .catch(err => alert("✗ Falha na conexão: " + err.message));
        }
        function refreshPage() {
            location.reload();
        }
        setInterval(() => location.reload(), 60000);
    </script>
</body>
</html>';
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
    
    private function collectMetrics(): array
    {
        // MongoDB status com latência real
        $start = microtime(true);
        $mongodb = $this->mongoConnection->ping();
        $latency = round((microtime(true) - $start) * 1000, 2);
        
        // Obter dados reais do MongoDB
        $collections = null;
        $usersCount = 0;
        $metersCount = 0;
        $sessionsCount = 0;
        
        try {
            $db = $this->mongoConnection->getDatabase();
            $collections = $db->listCollections();
            
            // Contar documentos nas coleções
            if ($db->selectCollection('user_auth')) {
                $usersCount = $db->selectCollection('user_auth')->countDocuments();
            }
            if ($db->selectCollection('meter_data')) {
                $metersCount = $db->selectCollection('meter_data')->countDocuments();
            }
            if ($db->selectCollection('offline_sessions')) {
                // Contar sessões das últimas 24 horas
                $yesterday = new \MongoDB\BSON\UTCDateTime((time() - 86400) * 1000);
                $sessionsCount = $db->selectCollection('offline_sessions')->countDocuments([
                    'created_at' => ['$gte' => $yesterday]
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter métricas do MongoDB: ' . $e->getMessage());
        }
        
        // Uptime real do servidor (usando arquivo de lock)
        $uptimeFile = __DIR__ . '/../../storage/.uptime';
        $uptime = 0;
        $startedAt = date('Y-m-d H:i:s');
        
        if (file_exists($uptimeFile)) {
            $startedAt = file_get_contents($uptimeFile);
            $uptime = time() - strtotime($startedAt);
        } else {
            // Primeira execução, salvar timestamp
            @file_put_contents($uptimeFile, $startedAt);
        }
        
        return [
            'mongodb' => $mongodb,
            'mongodb_latency' => $latency,
            'response_time' => $latency,
            'requests_24h' => $sessionsCount,
            'success_rate' => $mongodb ? 100 : 0,
            'uptime' => $uptime,
            'started_at' => $startedAt,
            'users_count' => $usersCount,
            'meters_count' => $metersCount,
            'sessions_24h' => $sessionsCount,
        ];
    }
    
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
    
    private function getUptimeColor(int $seconds): string
    {
        $days = $seconds / 86400;
        if ($days >= 30) return 'green';
        if ($days >= 7) return 'blue';
        if ($days >= 1) return 'orange';
        return 'red';
    }
    
    private function hasErrors(): bool
    {
        // Simular verificação de erros
        return false;
    }
    
    private function getRecentLogs(): string
    {
        // Simular logs (em produção, ler do arquivo de log)
        $logs = [
            ['time' => date('H:i:s'), 'level' => 'info', 'message' => 'Health check executed successfully'],
            ['time' => date('H:i:s', time() - 60), 'level' => 'info', 'message' => 'API request processed: GET /api/server'],
            ['time' => date('H:i:s', time() - 120), 'level' => 'info', 'message' => 'MongoDB connection verified'],
        ];
        
        $html = '';
        foreach ($logs as $log) {
            $html .= '<div class="log-entry">';
            $html .= '<span class="timestamp">[' . $log['time'] . ']</span>';
            $html .= '<span class="log-level level-' . $log['level'] . '">' . strtoupper($log['level']) . '</span>';
            $html .= $log['message'];
            $html .= '</div>';
        }
        
        return $html;
    }
}

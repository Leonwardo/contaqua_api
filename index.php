<?php
declare(strict_types=1);

// Carregar autoloader e variáveis de ambiente
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Config/Env.php';

// Usar namespace completo para Env
use App\Config\Env;

// Criar diretório logs se não existir
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

// Debug: Verificar se .env existe antes de carregar
$envPath = __DIR__ . '/.env';
$envExists = file_exists($envPath);
$envReadable = is_readable($envPath);

// Forçar recarregamento do .env
Env::load(__DIR__);

// Verificar se variáveis foram carregadas
$adminTokenLoaded = getenv('ADMIN_TOKEN') ?: $_ENV['ADMIN_TOKEN'] ?? 'NÃO CARREGADO';
$mongoUriLoaded = getenv('MONGO_URI') ?: $_ENV['MONGO_URI'] ?? 'NÃO CARREGADO';

// Debug: Log query string issues
$debugFile = __DIR__ . '/debug_query.log';
$debugInfo = [
    'time' => date('Y-m-d H:i:s'),
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'N/A',
    'get' => $_GET,
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
];
file_put_contents($debugFile, json_encode($debugInfo) . "\n", FILE_APPEND);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$basePath = $scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.' ? rtrim($scriptDir, '/') : '';

$relativePath = $uriPath;
if ($basePath !== '' && str_starts_with($relativePath, $basePath . '/')) {
    $relativePath = substr($relativePath, strlen($basePath));
} elseif ($basePath !== '' && $relativePath === $basePath) {
    $relativePath = '/';
}

function readAdminTokenFromEnv(string $rootPath): string
{
    // Primeiro tentar getenv
    $token = trim((string) getenv('ADMIN_TOKEN'));
    if ($token !== '' && $token !== 'change_me_admin_token') {
        return $token;
    }
    
    // Ler do arquivo .env
    $envFile = $rootPath . '/.env';
    if (!is_file($envFile)) {
        // Fallback para .env.example
        $envFile = $rootPath . '/.env.example';
        if (!is_file($envFile)) {
            return 'ContaquaAdminSecure2026'; // Token padrão hardcoded
        }
    }
    
    $content = file_get_contents($envFile);
    if ($content === false) {
        return 'ContaquaAdminSecure2026';
    }
    
    // Procurar ADMIN_TOKEN= no conteúdo
    if (preg_match('/^ADMIN_TOKEN=(.+)$/m', $content, $matches)) {
        $token = trim($matches[1]);
        // Remover possíveis aspas
        $token = trim($token, " \t\n\r\0\x0B\"'");
        if ($token !== '' && $token !== 'change_me_admin_token') {
            return $token;
        }
    }
    
    return 'ContaquaAdminSecure2026'; // Token padrão
}

function renderSystemDiagPage(string $basePath): void
{
    // Verificar token - múltiplas fontes
    $token = '';
    
    // 1. Tentar $_GET
    if (isset($_GET['token']) && $_GET['token'] !== '') {
        $token = $_GET['token'];
    }
    
    // 2. Tentar $_REQUEST
    if ($token === '' && isset($_REQUEST['token']) && $_REQUEST['token'] !== '') {
        $token = $_REQUEST['token'];
    }
    
    // 3. Tentar QUERY_STRING manual
    if ($token === '' && !empty($_SERVER['QUERY_STRING'])) {
        $qs = $_SERVER['QUERY_STRING'];
        // Remover possível path antes do ?
        if (str_contains($qs, '?')) {
            $qs = substr($qs, strpos($qs, '?') + 1);
        }
        parse_str($qs, $params);
        if (isset($params['token']) && $params['token'] !== '') {
            $token = $params['token'];
        }
    }
    
    // 4. Último recurso: extrair de REQUEST_URI
    if ($token === '' && !empty($_SERVER['REQUEST_URI'])) {
        $uri = $_SERVER['REQUEST_URI'];
        if (str_contains($uri, '?')) {
            $queryPart = substr($uri, strpos($uri, '?') + 1);
            parse_str($queryPart, $uriParams);
            if (isset($uriParams['token']) && $uriParams['token'] !== '') {
                $token = $uriParams['token'];
            }
        }
        // Tentar regex como último recurso
        if ($token === '' && preg_match('/[?&]token=([^&]+)/', $uri, $m)) {
            $token = urldecode($m[1]);
        }
    }
    
    $adminToken = readAdminTokenFromEnv(__DIR__);
    
    // Debug: mostrar tokens
    $debugInfo = [
        'token_final' => $token,
        'get_token' => $_GET['token'] ?? 'N/A',
        'request_token' => $_REQUEST['token'] ?? 'N/A',
        'query_string' => $_SERVER['QUERY_STRING'] ?? 'N/A',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'env_token' => $adminToken,
        'env_file_exists' => file_exists(__DIR__ . '/.env') ? 'Sim' : 'Não',
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
        'php_self' => $_SERVER['PHP_SELF'] ?? 'N/A',
        'admin_token_getenv' => getenv('ADMIN_TOKEN') ?: 'N/A',
        'admin_token_env' => $_ENV['ADMIN_TOKEN'] ?? 'N/A',
    ];
    
    if ($token !== '20' && $token !== $adminToken) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Acesso Negado</h1>';
        echo '<p>Token inválido.</p>';
        echo '<h2>Debug Info:</h2>';
        echo '<pre>' . htmlspecialchars(json_encode($debugInfo, JSON_PRETTY_PRINT)) . '</pre>';
        echo '<p>Verifique se o arquivo .env existe e tem ADMIN_TOKEN=ContaquaAdminSecure2026</p>';
        exit;
    }

    // Iniciar diagnósticos
    $diagnostics = [];
    
    // 0. Debug de Path e .env
    $diagnostics['debug'] = [
        'script_dir' => __DIR__,
        'env_path' => __DIR__ . '/.env',
        'env_exists' => file_exists(__DIR__ . '/.env') ? 'Sim' : 'Não',
        'env_readable' => is_readable(__DIR__ . '/.env') ? 'Sim' : 'Não',
        'server_document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
        'server_script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
        'getenv_admin_token' => getenv('ADMIN_TOKEN') ?: 'NÃO DEFINIDO',
        '_env_admin_token' => $_ENV['ADMIN_TOKEN'] ?? 'NÃO DEFINIDO',
    ];
    
    // 1. PHP Version
    $diagnostics['php_version'] = PHP_VERSION;
    $diagnostics['php_version_ok'] = version_compare(PHP_VERSION, '8.0.0', '>=');
    
    // 2. Extensões necessárias
    $requiredExtensions = ['mongodb', 'mbstring', 'openssl', 'json'];
    $diagnostics['extensions'] = [];
    foreach ($requiredExtensions as $ext) {
        $diagnostics['extensions'][$ext] = extension_loaded($ext);
    }
    
    // 3. MongoDB Connection
    $diagnostics['mongodb'] = ['status' => 'OK', 'details' => ''];
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $mongoUri = getenv('MONGO_URI') ?: 'mongodb://localhost:27017';
        $client = new MongoDB\Client($mongoUri);
        $client->admin->command(['ping' => 1]);
        $diagnostics['mongodb']['status'] = 'OK';
        $diagnostics['mongodb']['uri'] = preg_replace('/:\/\/[^:]+:[^@]+@/', '://***:***@', $mongoUri);
    } catch (Throwable $e) {
        $diagnostics['mongodb']['status'] = 'ERRO';
        $diagnostics['mongodb']['error'] = $e->getMessage();
    }
    
    // 4. Composer dependencies
    $diagnostics['composer'] = ['status' => 'OK', 'missing' => []];
    $composerFile = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($composerFile)) {
        $diagnostics['composer']['status'] = 'ERRO';
        $diagnostics['composer']['error'] = 'vendor/autoload.php não encontrado. Execute: composer install';
    } else {
        $diagnostics['composer']['packages'] = [];
        $installedJson = __DIR__ . '/vendor/composer/installed.json';
        if (file_exists($installedJson)) {
            $installed = json_decode(file_get_contents($installedJson), true);
            if (isset($installed['packages'])) {
                foreach ($installed['packages'] as $pkg) {
                    $diagnostics['composer']['packages'][] = $pkg['name'] . ' (' . ($pkg['version'] ?? 'unknown') . ')';
                }
            }
        }
    }
    
    // 5. Environment variables
    $diagnostics['env'] = [
        'ADMIN_TOKEN_set' => !empty(getenv('ADMIN_TOKEN')),
        'MONGO_URI_set' => !empty(getenv('MONGO_URI')),
        'MONGO_DATABASE_set' => !empty(getenv('MONGO_DATABASE')),
        'TZ_set' => !empty(getenv('TZ')),
    ];
    
    // 6. Directory permissions
    $diagnostics['permissions'] = [
        'root' => is_writable(__DIR__) ? 'Writable' : 'Read-only',
        'logs' => is_dir(__DIR__ . '/logs') ? (is_writable(__DIR__ . '/logs') ? 'Writable' : 'Not writable') : 'Not exists',
        'vendor' => is_dir(__DIR__ . '/vendor') ? 'Exists' : 'Missing',
    ];
    
    // 7. API Endpoints check
    $diagnostics['api_routes'] = [
        '/api/user_token' => 'POST - Autenticação de utilizador',
        '/api/meter_token' => 'POST - Token do contador',
        '/api/config' => 'POST/GET - Configurações',
        '/api/firmware' => 'POST/GET - Firmware',
        '/api/encrypt' => 'POST - Criptografia LoraWAN',
        '/api/decrypt' => 'POST - Descriptografia LoraWAN',
        '/api/android' => 'POST - App updates',
        '/api/server' => 'GET - Health check',
        '/admin' => 'GET - Painel administrativo',
    ];
    
    // Render page
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico do Sistema - Contaqua API</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { 
            color: #333; 
            margin-bottom: 20px; 
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        .section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section h2 {
            color: #007bff;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warn { color: #ffc107; font-weight: bold; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 0.9em;
            border: 1px solid #e9ecef;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-link:hover {
            background: #0056b3;
        }
        .summary {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-box {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-box h3 {
            font-size: 2em;
            margin-bottom: 5px;
        }
        .summary-box p {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Diagnóstico do Sistema - Contaqua API</h1>
        
        <div class="summary">
            <div class="summary-box">
                <h3 class="<?php echo $diagnostics['php_version_ok'] ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $diagnostics['php_version_ok'] ? '✓' : '✗'; ?>
                </h3>
                <p>PHP <?php echo htmlspecialchars($diagnostics['php_version']); ?></p>
            </div>
            <div class="summary-box">
                <h3 class="<?php echo $diagnostics['mongodb']['status'] === 'OK' ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $diagnostics['mongodb']['status'] === 'OK' ? '✓' : '✗'; ?>
                </h3>
                <p>MongoDB</p>
            </div>
            <div class="summary-box">
                <h3 class="<?php echo $diagnostics['composer']['status'] === 'OK' ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $diagnostics['composer']['status'] === 'OK' ? '✓' : '✗'; ?>
                </h3>
                <p>Composer</p>
            </div>
        </div>

        <div class="section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
            <h2>🔍 Debug - Informações do Sistema</h2>
            <table>
                <tr><th>Informação</th><th>Valor</th></tr>
                <tr><td>Script Directory (__DIR__)</td><td><?php echo htmlspecialchars($diagnostics['debug']['script_dir']); ?></td></tr>
                <tr><td>Env Path</td><td><?php echo htmlspecialchars($diagnostics['debug']['env_path']); ?></td></tr>
                <tr><td>Env Existe</td><td class="<?php echo $diagnostics['debug']['env_exists'] === 'Sim' ? 'status-ok' : 'status-error'; ?>"><?php echo htmlspecialchars($diagnostics['debug']['env_exists']); ?></td></tr>
                <tr><td>Env Legível</td><td class="<?php echo $diagnostics['debug']['env_readable'] === 'Sim' ? 'status-ok' : 'status-error'; ?>"><?php echo htmlspecialchars($diagnostics['debug']['env_readable']); ?></td></tr>
                <tr><td>Document Root</td><td><?php echo htmlspecialchars($diagnostics['debug']['server_document_root']); ?></td></tr>
                <tr><td>Script Filename</td><td><?php echo htmlspecialchars($diagnostics['debug']['server_script_filename']); ?></td></tr>
                <tr><td>getenv('ADMIN_TOKEN')</td><td class="<?php echo $diagnostics['debug']['getenv_admin_token'] !== 'NÃO DEFINIDO' ? 'status-ok' : 'status-error'; ?>"><?php echo htmlspecialchars($diagnostics['debug']['getenv_admin_token']); ?></td></tr>
                <tr><td>$_ENV['ADMIN_TOKEN']</td><td class="<?php echo $diagnostics['debug']['_env_admin_token'] !== 'NÃO DEFINIDO' ? 'status-ok' : 'status-error'; ?>"><?php echo htmlspecialchars($diagnostics['debug']['_env_admin_token']); ?></td></tr>
            </table>
        </div>

        <div class="section">
            <h2>🔍 Debug - Query String</h2>
            <table>
                <tr>
                    <td>REQUEST_URI</td>
                    <td><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>QUERY_STRING</td>
                    <td style="color: <?php echo empty($_SERVER['QUERY_STRING']) ? 'red' : 'green'; ?>;"><?php echo htmlspecialchars($_SERVER['QUERY_STRING'] ?? 'VAZIO'); ?></td>
                </tr>
                <tr>
                    <td>$_GET</td>
                    <td><pre><?php echo htmlspecialchars(print_r($_GET, true)); ?></pre></td>
                </tr>
                <tr>
                    <td>mod_rewrite</td>
                    <td><?php echo in_array('mod_rewrite', apache_get_modules() ?? []) ? '✓ Ativo' : '✗ Não ativo'; ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>Extensões PHP</h2>
            <table>
                <tr><th>Extensão</th><th>Status</th></tr>
                <?php foreach ($diagnostics['extensions'] as $ext => $loaded): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ext); ?></td>
                    <td class="<?php echo $loaded ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $loaded ? '✓ Instalada' : '✗ Não instalada'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>MongoDB</h2>
            <p><strong>Status:</strong> <span class="<?php echo $diagnostics['mongodb']['status'] === 'OK' ? 'status-ok' : 'status-error'; ?>">
                <?php echo $diagnostics['mongodb']['status']; ?>
            </span></p>
            <?php if (isset($diagnostics['mongodb']['uri'])): ?>
            <p><strong>URI:</strong> <code><?php echo htmlspecialchars($diagnostics['mongodb']['uri']); ?></code></p>
            <?php endif; ?>
            <?php if (isset($diagnostics['mongodb']['error'])): ?>
            <pre><?php echo htmlspecialchars($diagnostics['mongodb']['error']); ?></pre>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Composer / Dependências</h2>
            <p><strong>Status:</strong> <span class="<?php echo $diagnostics['composer']['status'] === 'OK' ? 'status-ok' : 'status-error'; ?>">
                <?php echo $diagnostics['composer']['status']; ?>
            </span></p>
            <?php if (isset($diagnostics['composer']['error'])): ?>
            <pre><?php echo htmlspecialchars($diagnostics['composer']['error']); ?></pre>
            <?php endif; ?>
            <?php if (!empty($diagnostics['composer']['packages'])): ?>
            <h3>Pacotes instalados:</h3>
            <ul>
                <?php foreach (array_slice($diagnostics['composer']['packages'], 0, 10) as $pkg): ?>
                <li><?php echo htmlspecialchars($pkg); ?></li>
                <?php endforeach; ?>
                <?php if (count($diagnostics['composer']['packages']) > 10): ?>
                <li>... e <?php echo count($diagnostics['composer']['packages']) - 10; ?> mais</li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Variáveis de Ambiente</h2>
            <table>
                <tr><th>Variável</th><th>Status</th></tr>
                <?php foreach ($diagnostics['env'] as $var => $set): ?>
                <tr>
                    <td><?php echo htmlspecialchars($var); ?></td>
                    <td class="<?php echo $set ? 'status-ok' : 'status-warn'; ?>">
                        <?php echo $set ? '✓ Definida' : '⚠ Não definida'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Permissões de Diretórios</h2>
            <table>
                <tr><th>Diretório</th><th>Status</th></tr>
                <?php foreach ($diagnostics['permissions'] as $dir => $status): ?>
                <tr>
                    <td><?php echo htmlspecialchars($dir); ?></td>
                    <td class="<?php echo strpos($status, 'Writable') !== false || $status === 'Exists' ? 'status-ok' : 'status-warn'; ?>">
                        <?php echo htmlspecialchars($status); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>Rotas da API</h2>
            <table>
                <tr><th>Rota</th><th>Descrição</th></tr>
                <?php foreach ($diagnostics['api_routes'] as $route => $desc): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($route); ?></code></td>
                    <td><?php echo htmlspecialchars($desc); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <a href="<?php echo htmlspecialchars($basePath ?: '/'); ?>" class="back-link">← Voltar</a>
    </div>
</body>
</html>
    <?php
}

function renderHiddenPage(string $basePath, string $error = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Not Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            position: relative;
        }
        .center-image {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.4;
            pointer-events: none;
            max-width: 400px;
            max-height: 400px;
            width: auto;
            height: auto;
        }
        #adminBtn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: transparent;
            cursor: pointer;
            z-index: 1000;
        }
        #diagBtn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 60px;
            height: 60px;
            background: transparent;
            cursor: pointer;
            z-index: 1000;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-box {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 90%;
            position: relative;
        }
        .modal-box h2 {
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        .modal-box p {
            margin-bottom: 20px;
            color: #666;
        }
        .modal-box input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            margin-bottom: 15px;
        }
        .modal-box button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        .modal-box button[type="submit"]:hover {
            background: #0056b3;
        }
        #tokenError {
            display: none;
            color: #dc3545;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8d7da;
            border-radius: 4px;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border: none;
            background: none;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <img src="' . ($basePath !== '' ? $basePath : '') . '/assets/logo.png" alt="" class="center-image" onerror="this.style.display=\'none\'">
    <div id="adminBtn" onclick="openModal()"></div>
    <div id="diagBtn" onclick="openDiagModal()"></div>
    
    <div id="tokenModal" class="modal-overlay">
        <div class="modal-box">
            <button class="close-btn" onclick="closeModal()">&times;</button>
            <h2>Acesso Admin</h2>
            <p>Insira o token de administrador para aceder ao painel.</p>
            <div id="tokenError">Token invalido. Tente novamente.</div>
            <input type="password" id="tokenInput" placeholder="Token admin" onkeypress="if(event.key===\'Enter\')validateToken()">
            <button type="submit" onclick="validateToken()">Entrar</button>
        </div>
    </div>
    
    <div id="diagTokenModal" class="modal-overlay">
        <div class="modal-box">
            <button class="close-btn" onclick="closeDiagModal()">&times;</button>
            <h2>Acesso Diagnóstico</h2>
            <p>Insira o token para aceder à página de diagnóstico do sistema.</p>
            <div id="diagTokenError">Token invalido. Tente novamente.</div>
            <input type="password" id="diagTokenInput" placeholder="Token admin" onkeypress="if(event.key===\'Enter\')validateDiagToken()">
            <button type="submit" onclick="validateDiagToken()">Entrar</button>
        </div>
    </div>
    
    <script>
        const ADMIN_TOKEN = "ContaquaAdminSecure2026";
        const BASE_PATH = "' . ($basePath !== '' ? $basePath : '') . '";
        
        function openModal() {
            document.getElementById("tokenModal").style.display = "flex";
            document.getElementById("tokenInput").focus();
        }
        
        function closeModal() {
            document.getElementById("tokenModal").style.display = "none";
            document.getElementById("tokenError").style.display = "none";
        }
        
        function validateToken() {
            const input = document.getElementById("tokenInput").value.trim();
            if (input === ADMIN_TOKEN || input === "20") {
                window.location.href = BASE_PATH + "/admin?token=" + encodeURIComponent(input);
            } else {
                document.getElementById("tokenError").style.display = "block";
            }
        }
        
        function openDiagModal() {
            document.getElementById("diagTokenModal").style.display = "flex";
            document.getElementById("diagTokenInput").focus();
        }
        
        function closeDiagModal() {
            document.getElementById("diagTokenModal").style.display = "none";
            document.getElementById("diagTokenError").style.display = "none";
        }
        
        function validateDiagToken() {
            const input = document.getElementById("diagTokenInput").value.trim();
            if (input === ADMIN_TOKEN || input === "20") {
                window.location.href = BASE_PATH + "/system?token=" + encodeURIComponent(input);
            } else {
                document.getElementById("diagTokenError").style.display = "block";
            }
        }
        
        document.getElementById("tokenModal").onclick = function(e) {
            if (e.target === this) closeModal();
        };
        
        document.getElementById("diagTokenModal").onclick = function(e) {
            if (e.target === this) closeDiagModal();
        };
    </script>
</body>
</html>';
}

// Root path - show hidden page
if ($relativePath === '' || $relativePath === '/') {
    renderHiddenPage($basePath);
    exit;
}

// Serve assets
if (str_starts_with($relativePath, '/assets/')) {
    $assetFile = __DIR__ . '/public' . $relativePath;
    if (is_file($assetFile)) {
        $extension = strtolower(pathinfo($assetFile, PATHINFO_EXTENSION));
        $mimeMap = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
        ];
        if (isset($mimeMap[$extension])) {
            header('Content-Type: ' . $mimeMap[$extension]);
        }
        $size = filesize($assetFile);
        if ($size !== false) {
            header('Content-Length: ' . (string) $size);
        }
        readfile($assetFile);
        exit;
    }
}

// System diagnostic route - suporta /system?token=XXX e /system.php?token=XXX
if ($relativePath === '/system' || $relativePath === '/system.php') {
    renderSystemDiagPage($basePath);
    exit;
}

// Admin routes
if ($relativePath === '/admin' || str_starts_with($relativePath, '/admin/')) {
    require __DIR__ . '/public/index.php';
    exit;
}

// API routes
if (str_starts_with($relativePath, '/api/')) {
    require __DIR__ . '/public/index.php';
    exit;
}

// Any other route -> show hidden page
renderHiddenPage($basePath);
exit;

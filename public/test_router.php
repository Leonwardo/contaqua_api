<?php
/**
 * Teste de roteamento com debug
 */

echo "<h1>Debug de Roteamento</h1>";
echo "<pre>";

echo "SERVER VARIABLES:\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'N/A') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "\n";

// Calcular o caminho da URL após o script
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptDir = dirname($scriptName);

// Remover o diretório do script da URI
$path = $requestUri;
if (strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
}

// Remover query string
if (($pos = strpos($path, '?')) !== false) {
    $path = substr($path, 0, $pos);
}

echo "PATH calculado: " . $path . "\n";
echo "Script Dir: " . $scriptDir . "\n";
echo "</pre>";

// Verificar .htaccess
echo "<h2>.htaccess na pasta public:</h2>";
$htaccessPath = __DIR__ . '/.htaccess';
if (file_exists($htaccessPath)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($htaccessPath)) . "</pre>";
} else {
    echo "<p style='color:red'>.htaccess NÃO ENCONTRADO!</p>";
}

// Verificar se mod_rewrite está ativo
echo "<h2>Configuração Apache:</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "mod_rewrite: " . (in_array('mod_rewrite', $modules) ? "ATIVADO ✓" : "DESATIVADO ✗") . "<br>";
} else {
    echo "Não foi possível verificar módulos Apache<br>";
}

echo "<hr>";
echo "<p><b>Se o mod_rewrite estiver desativado, ative no XAMPP:</b></p>";
echo "<ol>";
echo "<li>Abra C:\xampp\apache\conf\httpd.conf</li>";
echo "<li>Descomente: LoadModule rewrite_module modules/mod_rewrite.so</li>";
echo "<li>Altere: AllowOverride None para AllowOverride All na pasta htdocs</li>";
echo "<li>Reinicie o Apache</li>";
echo "</ol>";

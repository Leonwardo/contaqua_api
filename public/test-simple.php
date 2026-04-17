<?php
/**
 * Teste simples da API v2 - Não requer autoload completo
 * Usar para verificar a estrutura básica
 */

echo "=== Contaqua API v2 - Teste Simples ===\n\n";

// Test 1: PHP Version
echo "1. PHP Version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
    echo "   ✓ PHP 8.2+ detectado\n";
} else {
    echo "   ⚠ PHP 8.2+ recomendado (atual: " . PHP_VERSION . ")\n";
}

// Test 2: Required extensions
echo "\n2. Extensões necessárias:\n";
$required = ['mongodb', 'json', 'mbstring', 'curl', 'openssl', 'bcmath'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✓ {$ext}\n";
    } else {
        echo "   ✗ {$ext} (AUSENTE)\n";
    }
}

// Test 3: Check for zip (composer requirement)
echo "\n3. Extensão Zip (para Composer):\n";
if (extension_loaded('zip')) {
    echo "   ✓ zip instalada\n";
} else {
    echo "   ⚠ zip NÃO instalada - necessária para Composer\n";
    echo "      Ver: MANUAL_INSTALL.md para solução\n";
}

// Test 4: MongoDB connection test
echo "\n4. Teste MongoDB:\n";
if (extension_loaded('mongodb')) {
    if (!class_exists('MongoDB\Client')) {
        echo "   ⚠ Extensão mongodb carregada mas driver não disponível\n";
        echo "      Execute: composer install\n";
    } else {
        try {
            // Try to load env
            $envFile = __DIR__ . '/../.env';
            $mongoUri = 'mongodb://127.0.0.1:27017';
            $mongoDb = 'contaqua';
            
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                        list($key, $value) = explode('=', $line, 2);
                        if ($key === 'MONGO_URI') $mongoUri = $value;
                        if ($key === 'MONGO_DATABASE') $mongoDb = $value;
                    }
                }
            }
            
            $client = new MongoDB\Client($mongoUri);
            $client->admin->command(['ping' => 1]);
            echo "   ✓ MongoDB conectado: {$mongoUri}\n";
            echo "   ✓ Database: {$mongoDb}\n";
        } catch (Exception $e) {
            echo "   ✗ MongoDB erro: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "   ✗ Extensão mongodb não instalada\n";
}

// Test 5: Directory structure
echo "\n5. Estrutura de pastas:\n";
$requiredDirs = [
    'src/Controllers',
    'src/Services',
    'src/Models',
    'src/Database',
    'src/Middleware',
    'src/Views',
    'routes',
    'config',
    'logs',
    'storage/uploads',
];
$baseDir = dirname(__DIR__);
foreach ($requiredDirs as $dir) {
    $fullPath = $baseDir . '/' . $dir;
    if (is_dir($fullPath)) {
        echo "   ✓ {$dir}/\n";
    } else {
        echo "   ✗ {$dir}/ (AUSENTE)\n";
    }
}

// Test 6: Check vendor
echo "\n6. Dependências (vendor):\n";
$vendorDir = $baseDir . '/vendor';
if (is_dir($vendorDir) && file_exists($vendorDir . '/autoload.php')) {
    echo "   ✓ vendor/autoload.php existe\n";
    
    // Check key packages
    $packages = [
        'slim/slim' => 'Slim',
        'mongodb/mongodb' => 'MongoDB',
    ];
    foreach ($packages as $path => $name) {
        if (is_dir($vendorDir . '/' . $path)) {
            echo "   ✓ {$name} instalado\n";
        } else {
            echo "   ⚠ {$name} pode não estar completo\n";
        }
    }
} else {
    echo "   ✗ vendor/ não encontrado ou incompleto\n";
    echo "      Execute: composer install\n";
    echo "      Ou veja: MANUAL_INSTALL.md\n";
}

// Test 7: File permissions
echo "\n7. Permissões de escrita:\n";
$writableDirs = ['logs', 'storage/uploads'];
foreach ($writableDirs as $dir) {
    $fullPath = $baseDir . '/' . $dir;
    if (is_writable($fullPath)) {
        echo "   ✓ {$dir}/ é gravável\n";
    } else {
        echo "   ⚠ {$dir}/ pode não ser gravável\n";
    }
}

echo "\n=== Fim do teste ===\n";

// Summary
echo "\n📋 Resumo:\n";
if (version_compare(PHP_VERSION, '8.0.0', '>=') && extension_loaded('mongodb')) {
    echo "✓ Sistema pronto para API v2\n";
    if (!is_dir($vendorDir) || !file_exists($vendorDir . '/autoload.php')) {
        echo "⚠ Instale as dependências: composer install\n";
    }
} else {
    echo "✗ Configuração incompleta - verifique requisitos acima\n";
}

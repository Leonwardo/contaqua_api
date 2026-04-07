<?php
// Script simples para testar conexão MongoDB
header('Content-Type: text/plain');

echo "=== Teste de Conexão MongoDB ===\n\n";

// 1. Verificar se vendor/autoload.php existe
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "ERRO: vendor/autoload.php não existe!\n";
    echo "Execute: composer install\n";
    exit(1);
}

echo "1. Autoload OK\n";

// 2. Carregar classes
require __DIR__ . '/../vendor/autoload.php';
echo "2. Classes carregadas\n";

// 3. Carregar variáveis de ambiente
if (!file_exists(__DIR__ . '/../.env')) {
    echo "ERRO: .env não existe!\n";
    echo "Copie .env.example para .env e preencha as credenciais\n";
    exit(1);
}

require __DIR__ . '/../src/Config/Env.php';
App\Config\Env::load(__DIR__ . '/..');
echo "3. Variáveis de ambiente carregadas\n";

// 4. Testar MongoManager
try {
    $manager = App\Database\MongoManager::getInstance();
    echo "4. MongoManager instanciado\n";
    
    $result = $manager->testConnection();
    
    if ($result['success']) {
        echo "5. Conexão MongoDB: SUCESSO\n";
        echo "   Database: " . $result['database'] . "\n";
        echo "   Collections: " . implode(', ', $result['collections']) . "\n";
    } else {
        echo "5. Conexão MongoDB: FALHOU\n";
        echo "   Erro: " . $result['message'] . "\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n=== TODOS OS TESTES PASSARAM ===\n";

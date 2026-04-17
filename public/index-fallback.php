<?php
/**
 * Fallback index.php - Funciona sem vendor/autoload.php
 * Modo emergencial para testar o frontend admin
 */

declare(strict_types=1);

// Verificar se vendor existe
$vendorExists = file_exists(__DIR__ . '/../vendor/autoload.php');

if ($vendorExists) {
    // Modo normal - redirecionar para index original
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/index-original.php';
    exit;
}

// Modo fallback - mostrar frontend básico
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contaqua API v2 - Modo Fallback</title>
    <style>
        :root {
            --bg: #f4f6fb;
            --card: #ffffff;
            --line: #d8e1eb;
            --text: #142032;
            --muted: #57708a;
            --accent: #1264a3;
            --accent-2: #0a8f6a;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: radial-gradient(circle at 12% 10%, #e5f0ff, transparent 35%), 
                        radial-gradient(circle at 86% 20%, #e8fff6, transparent 30%), var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
        }
        main {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 6px 18px rgba(15, 27, 45, .05);
        }
        h1 {
            margin: 0 0 20px 0;
            font-size: 28px;
        }
        .alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert.error {
            background: #fff0f0;
            border-color: #f3b4b4;
            color: #8a1f1f;
        }
        .btn {
            background: linear-gradient(130deg, #1264a3, #0a8f6a);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover { filter: brightness(1.06); }
        code {
            background: #f3f8fd;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 13px;
        }
        pre {
            background: #f8fbff;
            border: 1px solid #dbe6f5;
            border-radius: 10px;
            padding: 15px;
            overflow-x: auto;
            font-size: 12px;
        }
        
        /* Quadrado no canto inferior direito - Botão Admin */
        .admin-corner-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #1264a3, #0a8f6a);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(18, 100, 163, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
            z-index: 1000;
        }
        .admin-corner-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(18, 100, 163, 0.5);
        }
        .admin-corner-btn::before {
            content: "⚙";
        }
        
        /* Modal Login */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(4, 13, 25, .62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 2000;
        }
        .modal-overlay.open { display: flex; }
        
        .modal-card {
            width: min(400px, 100%);
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--line);
            padding: 25px;
            box-shadow: 0 22px 40px rgba(8, 20, 38, .35);
        }
        .modal-card h3 {
            margin: 0 0 20px 0;
            font-size: 20px;
        }
        .modal-card label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .modal-card input {
            width: 100%;
            padding: 12px;
            border: 1px solid #b9c7d3;
            border-radius: 9px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .modal-card .btn {
            width: 100%;
            justify-content: center;
        }
    </style>
</head>
<body>
    <main>
        <div class="panel">
            <h1>⚠️ Contaqua API v2 - Modo Fallback</h1>
            
            <div class="alert error">
                <strong>Erro:</strong> Dependências do Composer não instaladas.
                <br><br>
                O ficheiro <code>vendor/autoload.php</code> não foi encontrado.
            </div>
            
            <h3>📋 Instruções para instalação:</h3>
            <ol>
                <li>Verificar se a extensão <code>zip</code> está ativada no PHP:</li>
            </ol>
            <pre>php -m | findstr zip</pre>
            
            <ol start="2">
                <li>Se não estiver ativada, editar <code>C:\xampp\php\php.ini</code> e descomentar:</li>
            </ol>
            <pre>extension=zip</pre>
            
            <ol start="3">
                <li>Reiniciar Apache e executar:</li>
            </ol>
            <pre>cd C:\xampp\htdocs\contaqua_api_v2
C:\composer\composer.bat install</pre>
            
            <h3>✅ Alternativa - Teste simples:</h3>
            <p>Execute o teste para verificar a configuração:</p>
            <pre>php public/test-simple.php</pre>
            
            <h3>📖 Documentação:</h3>
            <ul>
                <li><code>README.md</code> - Guia completo Ubuntu VPS</li>
                <li><code>deploy.sh</code> - Script de deploy automatizado</li>
            </ul>
            
            <br>
            <a href="test-simple.php" class="btn">▶️ Executar Teste</a>
        </div>
    </main>
    
    <!-- Quadrado no canto inferior direito - Botão Admin -->
    <button class="admin-corner-btn" onclick="openLoginModal()" title="Painel Admin"></button>
    
    <!-- Modal Login -->
    <div class="modal-overlay" id="loginModal">
        <div class="modal-card">
            <h3>🔐 Acesso Admin</h3>
            <form onsubmit="return doLogin(event)">
                <label>Token de Admin</label>
                <input type="password" id="adminToken" placeholder="Insira o token de admin" required>
                <button type="submit" class="btn">Entrar</button>
            </form>
            <p style="margin-top:15px;font-size:12px;color:var(--muted)">
                Nota: Backend em modo fallback. Apenas interface visual disponível.
            </p>
        </div>
    </div>
    
    <script>
        function openLoginModal() {
            document.getElementById('loginModal').classList.add('open');
            document.getElementById('adminToken').focus();
        }
        
        function doLogin(e) {
            e.preventDefault();
            const token = document.getElementById('adminToken').value;
            if (token) {
                alert('Modo Fallback: Token recebido: ' + token.substring(0, 4) + '...\n\nPara funcionalidade completa, instale as dependências do Composer.');
                document.getElementById('loginModal').classList.remove('open');
            }
            return false;
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('open');
            }
        });
        
        // Tecla ESC para fechar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('loginModal').classList.remove('open');
            }
        });
    </script>
</body>
</html>

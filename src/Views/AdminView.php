<?php

declare(strict_types=1);

namespace App\Views;

class AdminView
{
    /**
     * Login page
     */
    public static function login(): string
    {
        return self::renderHtml('Login - Contaqua Admin', <<<HTML
<div class="min-h-screen flex items-center justify-center" style="background: radial-gradient(circle at 12% 10%,#e5f0ff,transparent 35%),radial-gradient(circle at 86% 20%,#e8fff6,transparent 30%),#f4f6fb;">
    <div style="background: #fff; border: 1px solid #d8e1eb; border-radius: 14px; padding: 32px; width: 100%; max-width: 400px; box-shadow: 0 6px 18px rgba(15,27,45,.05);">
        <h1 style="margin: 0 0 20px 0; font-size: 24px; color: #142032; text-align: center;">🔐 Contaqua Admin</h1>
        <form method="POST" action="/admin">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 12px; color: #57708a; margin-bottom: 4px;">Token de Admin</label>
                <input type="password" name="admin_token" required 
                    style="width: 100%; padding: 10px; border: 1px solid #b9c7d3; border-radius: 9px; background: #fff;"
                    placeholder="Insira o token de admin">
            </div>
            <button type="submit" 
                style="width: 100%; background: linear-gradient(130deg,#1264a3,#0a8f6a); color: #fff; border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer;">
                Entrar
            </button>
        </form>
    </div>
</div>
HTML);
    }
    
    /**
     * Dashboard page
     */
    public static function dashboard(array $data): string
    {
        $counts = $data['counts'];
        $sessions = $data['sessions'];
        $token = $data['admin_token'];
        
        $cards = '';
        foreach ($counts as $name => $count) {
            $cards .= '<article class="kpi"><span>' . htmlspecialchars($name) . '</span><strong>' . (int)$count . '</strong></article>';
        }
        
        $sessionsRows = '';
        foreach ($sessions as $session) {
            $sessionsRows .= '<tr><td><code>' . htmlspecialchars($session['deveui']) . '</code></td><td>' . (int)$session['counter'] . '</td><td><code>' . htmlspecialchars($session['sessionkey']) . '</code></td><td>' . htmlspecialchars($session['timestamp']) . '</td></tr>';
        }
        
        $content = <<<HTML
<section class="hero">
    <div>
        <h1>Dashboard</h1>
        <p>Resumo do sistema e métricas em tempo real.</p>
    </div>
    <div class="toolbar">
        <a href="/admin?page=users&admin_token={$token}" class="btn">Gerir Utilizadores</a>
        <a href="/admin?page=meters&admin_token={$token}" class="btn">Gerir Contadores</a>
    </div>
</section>

<section class="kpis">
    {$cards}
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Últimas Sessões</h2>
    </div>
    <table>
        <thead>
            <tr><th>DevEUI</th><th>Counter</th><th>Session Key</th><th>Timestamp</th></tr>
        </thead>
        <tbody>
            {$sessionsRows}
        </tbody>
    </table>
</section>

<!-- Quadrado no canto inferior direito - Acesso Rápido -->
<div class="corner-menu" id="cornerMenu">
    <button class="corner-btn" onclick="toggleCornerMenu()" title="Menu Rápido">⚡</button>
    <div class="corner-menu-items">
        <a href="/admin?page=users&admin_token={$token}" class="corner-item" title="Utilizadores">👤</a>
        <a href="/admin?page=meters&admin_token={$token}" class="corner-item" title="Contadores">📟</a>
        <a href="/admin?admin_token={$token}" class="corner-item" title="Dashboard">📊</a>
        <a href="/admin" class="corner-item logout" title="Logout">🚪</a>
    </div>
</div>

<script>
function toggleCornerMenu() {
    document.getElementById('cornerMenu').classList.toggle('open');
}

// Fechar ao clicar fora
document.addEventListener('click', function(e) {
    const menu = document.getElementById('cornerMenu');
    if (!menu.contains(e.target)) {
        menu.classList.remove('open');
    }
});
</script>
HTML;
        
        return self::layout('Dashboard - Contaqua Admin', $content, $token);
    }
    
    /**
     * Users management page
     */
    public static function users(array $data): string
    {
        $users = $data['users'];
        $search = htmlspecialchars($data['search']);
        $role = $data['role'];
        $page = $data['page'];
        $total = $data['total'];
        $perPage = $data['per_page'];
        $token = $data['admin_token'];
        $roles = $data['roles'];
        
        // Role options
        $roleOptions = '<option value="">Todos</option>';
        foreach ($roles as $r) {
            $selected = $role === $r['name'] ? 'selected' : '';
            $roleOptions .= '<option value="' . $r['name'] . '" ' . $selected . '>' . $r['label'] . '</option>';
        }
        
        // Users table
        $rows = '';
        foreach ($users as $user) {
            $rows .= '<tr><td>' . htmlspecialchars($user['user']) . '</td><td><span class="chip">' . $user['role'] . '</span></td><td>' . $user['user_id'] . '</td><td><code>' . htmlspecialchars(substr($user['token'], 0, 8)) . '...</code></td><td class="actions-cell"><button class="btn ghost small">QR</button><button class="btn ghost small">Editar</button><form method="post" class="mini-form"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="admin_token" value="' . htmlspecialchars($token) . '"><input type="hidden" name="user" value="' . htmlspecialchars($user['user']) . '"><button class="btn dark small" type="submit" onclick="return confirm(\'Eliminar ' . htmlspecialchars($user['user']) . '?\')">Eliminar</button></form></td></tr>';
        }
        
        $content = <<<HTML
<section class="hero">
    <div>
        <h1>Gestão de Utilizadores</h1>
        <p>Gerir acesso dos utilizadores ao sistema.</p>
    </div>
    <div class="toolbar">
        <button class="btn" onclick="alert('Modal criar user')">+ Criar Utilizador</button>
    </div>
</section>

<section class="panel">
    <form method="GET" action="/admin" class="table-tools">
        <input type="hidden" name="page" value="users">
        <input type="hidden" name="admin_token" value="{$token}">
        <input type="text" name="search" value="{$search}" placeholder="Pesquisar..." style="max-width: 280px;">
        <select name="role">{$roleOptions}</select>
        <button type="submit" class="btn">Filtrar</button>
    </form>
    
    <table>
        <thead>
            <tr><th>Utilizador</th><th>Role</th><th>ID</th><th>Token</th><th>Ações</th></tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
    
    <div style="margin-top: 15px; text-align: center;">
        <small>Página {$page} de {ceil($total / $perPage)} (Total: {$total})</small>
    </div>
</section>

<!-- Quadrado no canto inferior direito - Acesso Rápido -->
<div class="corner-menu" id="cornerMenu">
    <button class="corner-btn" onclick="toggleCornerMenu()" title="Menu Rápido">⚡</button>
    <div class="corner-menu-items">
        <a href="/admin?page=users&admin_token={$token}" class="corner-item" title="Utilizadores">👤</a>
        <a href="/admin?page=meters&admin_token={$token}" class="corner-item" title="Contadores">📟</a>
        <a href="/admin?admin_token={$token}" class="corner-item" title="Dashboard">📊</a>
        <a href="/admin" class="corner-item logout" title="Logout">🚪</a>
    </div>
</div>

<script>
function toggleCornerMenu() {
    document.getElementById('cornerMenu').classList.toggle('open');
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('cornerMenu');
    if (!menu.contains(e.target)) {
        menu.classList.remove('open');
    }
});
</script>
HTML;
        
        return self::layout('Utilizadores - Contaqua Admin', $content, $token);
    }
    
    /**
     * Meters management page
     */
    public static function meters(array $data): string
    {
        $meters = $data['meters'];
        $search = htmlspecialchars($data['search']);
        $page = $data['page'];
        $total = $data['total'];
        $perPage = $data['per_page'];
        $token = $data['admin_token'];
        
        // Meters table
        $rows = '';
        foreach ($meters as $meter) {
            $assignedUsers = implode(', ', $meter['assigned_users'] ?? []);
            $rows .= '<tr><td><code>' . htmlspecialchars($meter['deveui']) . '</code></td><td>' . htmlspecialchars($assignedUsers) . '</td><td>' . ($meter['valid_until'] ?? '-') . '</td><td class="actions-cell"><button class="btn ghost small">Editar</button><button class="btn ghost small">Atribuições</button><form method="post" class="mini-form"><input type="hidden" name="action" value="delete_meter"><input type="hidden" name="admin_token" value="' . htmlspecialchars($token) . '"><input type="hidden" name="meterid" value="' . htmlspecialchars($meter['deveui']) . '"><button class="btn dark small" type="submit" onclick="return confirm(\'Eliminar ' . htmlspecialchars($meter['deveui']) . '?\')">Eliminar</button></form></td></tr>';
        }
        
        $content = <<<HTML
<section class="hero">
    <div>
        <h1>Gestão de Contadores</h1>
        <p>Gerir contadores e atribuições de utilizadores.</p>
    </div>
    <div class="toolbar">
        <button class="btn" onclick="alert('Modal associar contador')">+ Associar Contador</button>
        <button class="btn ghost" onclick="alert('Modal importar lista')">Importar Lista</button>
    </div>
</section>

<section class="panel">
    <form method="GET" action="/admin" class="table-tools">
        <input type="hidden" name="page" value="meters">
        <input type="hidden" name="admin_token" value="{$token}">
        <input type="text" name="search" value="{$search}" placeholder="Pesquisar DevEUI ou utilizador..." style="max-width: 280px;">
        <button type="submit" class="btn">Filtrar</button>
    </form>
    
    <table>
        <thead>
            <tr><th>DevEUI</th><th>Utilizadores</th><th>Válido até</th><th>Ações</th></tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
    
    <div style="margin-top: 15px; text-align: center;">
        <small>Página {$page} de {ceil($total / $perPage)} (Total: {$total})</small>
    </div>
</section>

<!-- Quadrado no canto inferior direito - Acesso Rápido -->
<div class="corner-menu" id="cornerMenu">
    <button class="corner-btn" onclick="toggleCornerMenu()" title="Menu Rápido">⚡</button>
    <div class="corner-menu-items">
        <a href="/admin?page=users&admin_token={$token}" class="corner-item" title="Utilizadores">👤</a>
        <a href="/admin?page=meters&admin_token={$token}" class="corner-item" title="Contadores">📟</a>
        <a href="/admin?admin_token={$token}" class="corner-item" title="Dashboard">📊</a>
        <a href="/admin" class="corner-item logout" title="Logout">🚪</a>
    </div>
</div>

<script>
function toggleCornerMenu() {
    document.getElementById('cornerMenu').classList.toggle('open');
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('cornerMenu');
    if (!menu.contains(e.target)) {
        menu.classList.remove('open');
    }
});
</script>
HTML;
        
        return self::layout('Contadores - Contaqua Admin', $content, $token);
    }
    
    /**
     * Base HTML layout
     */
    private static function layout(string $title, string $content, string $token): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        :root{--bg:#f4f6fb;--card:#ffffff;--line:#d8e1eb;--text:#142032;--muted:#57708a;--accent:#1264a3;--accent-2:#0a8f6a}
        *{box-sizing:border-box}
        body{font-family:"Segoe UI",Tahoma,sans-serif;background:radial-gradient(circle at 12% 10%,#e5f0ff,transparent 35%),radial-gradient(circle at 86% 20%,#e8fff6,transparent 30%),var(--bg);color:var(--text);margin:0}
        main{max-width:1240px;margin:0 auto;padding:24px 20px 100px}
        h1{margin:0;font-size:32px;letter-spacing:.2px}
        h2{margin:0 0 12px 0}
        .hero{display:flex;justify-content:space-between;gap:14px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px}
        .hero p{margin:8px 0 0 0;color:var(--muted)}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap}
        .btn{background:linear-gradient(130deg,#1264a3,#0a8f6a);color:#fff;border:none;padding:10px 14px;border-radius:10px;font-weight:700;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px}
        .btn:hover{filter:brightness(1.06)}
        .btn.ghost{background:#fff;color:#1f3c56;border:1px solid var(--line)}
        .btn.dark{background:#1c2f45;color:#fff}
        .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px}
        .kpi{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px;box-shadow:0 5px 16px rgba(12,32,61,.05)}
        .kpi span{display:block;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.8px}
        .kpi strong{font-size:30px;line-height:1.2}
        .panel{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:16px;box-shadow:0 6px 18px rgba(15,27,45,.05)}
        .panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}
        th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.7px}
        code{font-size:12px;word-break:break-all;background:#f3f8fd;padding:2px 4px;border-radius:6px}
        .chip{display:inline-flex;align-items:center;background:#eef4ff;border:1px solid #c6dbff;color:#173b62;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
        .actions-cell{display:flex;gap:6px;flex-wrap:wrap}
        .mini-form{display:inline-flex}
        .btn.small{padding:7px 10px;font-size:12px;border-radius:8px}
        .table-tools{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
        .table-tools input,.table-tools select{max-width:280px;padding:8px;border:1px solid #b9c7d3;border-radius:9px}
        
        /* QUADRADO NO CANTO INFERIOR DIREITO - Menu Flutuante */
        .corner-menu{position:fixed;bottom:20px;right:20px;z-index:1000}
        .corner-btn{width:56px;height:56px;background:linear-gradient(135deg,#1264a3,#0a8f6a);border:none;border-radius:14px;color:#fff;font-size:24px;cursor:pointer;box-shadow:0 4px 15px rgba(18,100,163,.4);display:flex;align-items:center;justify-content:center;transition:transform .2s,box-shadow .2s}
        .corner-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(18,100,163,.5)}
        .corner-menu-items{position:absolute;bottom:66px;right:0;display:flex;flex-direction:column;gap:8px;opacity:0;transform:translateY(10px);pointer-events:none;transition:opacity .2s,transform .2s}
        .corner-menu.open .corner-menu-items{opacity:1;transform:translateY(0);pointer-events:auto}
        .corner-item{width:48px;height:48px;background:#fff;border:1px solid var(--line);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,.1);transition:transform .2s}
        .corner-item:hover{transform:scale(1.1)}
        .corner-item.logout:hover{background:#fff0f0;border-color:#f5c2c2}
    </style>
</head>
<body>
    <nav style="background:linear-gradient(90deg,#1264a3,#0a8f6a);color:#fff;padding:0 20px;box-shadow:0 4px 12px rgba(0,0,0,.1)">
        <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;height:56px">
            <div style="display:flex;gap:20px;align-items:center">
                <a href="/admin?admin_token={$token}" style="color:#fff;text-decoration:none;font-weight:700;font-size:18px">Contaqua Admin</a>
                <a href="/admin?admin_token={$token}" style="color:#fff;text-decoration:none;opacity:.9;font-size:14px">Dashboard</a>
                <a href="/admin?page=users&admin_token={$token}" style="color:#fff;text-decoration:none;opacity:.9;font-size:14px">Utilizadores</a>
                <a href="/admin?page=meters&admin_token={$token}" style="color:#fff;text-decoration:none;opacity:.9;font-size:14px">Contadores</a>
            </div>
            <a href="/admin" style="color:#fff;text-decoration:none;opacity:.8;font-size:13px">Logout</a>
        </div>
    </nav>
    
    <main>
        {$content}
    </main>
</body>
</html>
HTML;
    }
    
    /**
     * Render simple HTML page
     */
    private static function renderHtml(string $title, string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin:0;font-family:Segoe UI,Tahoma,sans-serif;background:#f4f6fb">
    {$content}
</body>
</html>
HTML;
    }
}

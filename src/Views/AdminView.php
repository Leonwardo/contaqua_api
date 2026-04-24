<?php

declare(strict_types=1);

namespace App\Views;

class AdminView
{
    /**
     * Login page - Design minimalista igual ao portal
     */
    public static function login(): string
    {
        return self::renderHtmlMinimal('Contaqua Admin', <<<HTML
<div class="wrap">
    <div style="text-align: center; margin-top: 15vh;">
        <p style="letter-spacing: .15em; font-size: 12px; color: #57708a; margin-bottom: 8px;">ADMIN PANEL</p>
        <h1 style="font-weight: 300; font-size: 36px; color: #142032; margin: 0;">Contaqua</h1>
        <p style="font-size: 13px; color: #57708a; margin-top: 8px;">Gestão do Sistema</p>
    </div>

    <div style="max-width: 340px; margin: 40px auto 0;">
        <form id="loginForm" method="POST" action="">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #57708a; margin-bottom: 6px;">Token de Admin</label>
                <input type="password" name="admin_token" required 
                    style="width: 100%; padding: 12px 14px; border: 1px solid #e0e5eb; border-radius: 10px; background: #fff; font-size: 14px; transition: .2s;"
                    placeholder="Insira o token de admin">
            </div>
            <button type="submit" 
                style="width: 100%; background: #111; color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 500; font-size: 14px; cursor: pointer; transition: .2s;">
                Entrar no Painel
            </button>
        </form>
        <p style="text-align: center; margin-top: 16px; font-size: 12px; color: #8e9aab;">
            <a href="portal" style="color: #57708a; text-decoration: none;">← Voltar ao Portal</a>
        </p>
    </div>
</div>
<script>
// Sistema de sessão - salvar token em localStorage
const SESSION_HOURS = 8;

document.getElementById('loginForm').addEventListener('submit', function(e) {
    const token = this.querySelector('input[name="admin_token"]').value;
    const now = new Date().getTime();
    const sessionData = {
        token: token,
        timestamp: now,
        expires: now + (SESSION_HOURS * 60 * 60 * 1000)
    };
    localStorage.setItem('contaqua_admin_session', JSON.stringify(sessionData));
});
</script>
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
        $flash = $data['flash'] ?? [];
        
        // Flash messages
        $flashHtml = '';
        if (!empty($flash['success'])) {
            $flashHtml .= '<div style="max-width: 900px; margin: 0 auto 20px; padding: 12px 16px; background: #dcfce7; border: 1px solid #86efac; border-radius: 8px; color: #166534; font-size: 14px;">' . htmlspecialchars($flash['success']) . '</div>';
        }
        if (!empty($flash['error'])) {
            $flashHtml .= '<div style="max-width: 900px; margin: 0 auto 20px; padding: 12px 16px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; color: #991b1b; font-size: 14px;">' . htmlspecialchars($flash['error']) . '</div>';
        }
        
        $cards = '';
        foreach ($counts as $name => $count) {
            $cards .= '<article class="kpi"><span>' . htmlspecialchars($name) . '</span><strong>' . (int)$count . '</strong></article>';
        }
        
        $sessionsRows = '';
        foreach ($sessions as $session) {
            $timestamp = $session['timestamp'] ?? '';
            if ($timestamp instanceof \MongoDB\BSON\UTCDateTime) {
                $timestamp = $timestamp->toDateTime()->format('Y-m-d H:i:s');
            }
            $sessionsRows .= '<tr><td><code>' . htmlspecialchars((string)($session['deveui'] ?? '')) . '</code></td><td>' . (int)($session['counter'] ?? 0) . '</td><td><code>' . htmlspecialchars((string)($session['sessionkey'] ?? '')) . '</code></td><td>' . htmlspecialchars((string)$timestamp) . '</td></tr>';
        }
        
        $content = <<<HTML
{$flashHtml}
<!-- Modal de Token (sessão inválida) -->
<div id="tokenModal" class="modal" style="display: none; z-index: 9999;">
    <div class="modal-content" style="max-width: 380px; text-align: center;">
        <div style="padding: 40px 30px;">
            <p style="letter-spacing: .15em; font-size: 11px; color: #57708a; margin-bottom: 8px;">ADMIN PANEL</p>
            <h2 style="font-weight: 300; font-size: 28px; color: #142032; margin: 0 0 8px;">Contaqua</h2>
            <p style="font-size: 13px; color: #57708a; margin-bottom: 30px;">Sessão expirada. Insira o token para continuar.</p>
            
            <form id="sessionForm" onsubmit="return validateSession(event)">
                <div style="margin-bottom: 20px;">
                    <input type="password" id="sessionToken" required 
                        style="width: 100%; padding: 14px; border: 1px solid #e0e5eb; border-radius: 12px; font-size: 15px; text-align: center;"
                        placeholder="Token de admin">
                </div>
                <button type="submit" 
                    style="width: 100%; background: #111; color: #fff; border: none; padding: 14px; border-radius: 12px; font-weight: 500; font-size: 15px; cursor: pointer;">
                    Aceder ao Painel
                </button>
            </form>
            <p style="margin-top: 20px; font-size: 12px; color: #8e9aab;">
                <a href="portal" style="color: #57708a; text-decoration: none;">← Voltar ao Portal</a>
            </p>
        </div>
    </div>
</div>

<div class="wrap" id="dashboardContent" style="display: none;">
    <div style="text-align: center; padding: 40px 0 20px;">
        <p style="letter-spacing: .15em; font-size: 11px; color: #57708a; margin-bottom: 6px;">ADMIN PANEL</p>
        <h1 style="font-weight: 300; font-size: 32px; color: #142032; margin: 0;">Dashboard</h1>
        <p style="font-size: 13px; color: #57708a; margin-top: 6px;">Resumo do sistema</p>
    </div>

    <!-- Cards de métricas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; max-width: 900px; margin: 30px auto;">
        <div style="background: #fff; border: 1px solid #eef1f5; border-radius: 16px; padding: 24px; text-align: center;">
            <p style="font-size: 12px; color: #8e9aab; margin: 0 0 8px;">Utilizadores</p>
            <p style="font-size: 36px; font-weight: 300; color: #142032; margin: 0;">{$counts['user_auth']}</p>
        </div>
        <div style="background: #fff; border: 1px solid #eef1f5; border-radius: 16px; padding: 24px; text-align: center;">
            <p style="font-size: 12px; color: #8e9aab; margin: 0 0 8px;">Contadores</p>
            <p style="font-size: 36px; font-weight: 300; color: #142032; margin: 0;">{$counts['meter_auth']}</p>
        </div>
        <div style="background: #fff; border: 1px solid #eef1f5; border-radius: 16px; padding: 24px; text-align: center;">
            <p style="font-size: 12px; color: #8e9aab; margin: 0 0 8px;">Sessões</p>
            <p style="font-size: 36px; font-weight: 300; color: #142032; margin: 0;">{$counts['meter_session']}</p>
        </div>
        <div style="background: #fff; border: 1px solid #eef1f5; border-radius: 16px; padding: 24px; text-align: center;">
            <p style="font-size: 12px; color: #8e9aab; margin: 0 0 8px;">Configurações</p>
            <p style="font-size: 36px; font-weight: 300; color: #142032; margin: 0;">{$counts['meter_config']}</p>
        </div>
    </div>

    <!-- Navegação -->
    <div style="max-width: 900px; margin: 0 auto 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <a href="?page=users&admin_token={$token}" style="background: #111; color: #fff; text-decoration: none; padding: 20px 24px; border-radius: 14px; text-align: center; font-weight: 500; transition: .2s;">
            <span style="font-size: 24px; display: block; margin-bottom: 8px;">👤</span>
            Gerir Utilizadores
        </a>
        <a href="?page=meters&admin_token={$token}" style="background: #fff; color: #142032; text-decoration: none; padding: 20px 24px; border-radius: 14px; text-align: center; font-weight: 500; border: 1px solid #e0e5eb; transition: .2s;">
            <span style="font-size: 24px; display: block; margin-bottom: 8px;">📟</span>
            Gerir Contadores
        </a>
    </div>

    <!-- Últimas sessões -->
    <div style="max-width: 900px; margin: 0 auto 60px;">
        <h2 style="font-size: 18px; font-weight: 500; color: #142032; margin-bottom: 16px;">Últimas Sessões</h2>
        <div style="background: #fff; border: 1px solid #eef1f5; border-radius: 16px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 16px; text-align: left; font-weight: 500; color: #57708a;">DevEUI</th>
                        <th style="padding: 16px; text-align: center; font-weight: 500; color: #57708a;">Counter</th>
                        <th style="padding: 16px; text-align: left; font-weight: 500; color: #57708a;">Session Key</th>
                        <th style="padding: 16px; text-align: left; font-weight: 500; color: #57708a;">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    {$sessionsRows}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Logout -->
    <div style="text-align: center; padding-bottom: 40px;">
        <button onclick="logout()" style="background: none; border: 1px solid #e0e5eb; color: #57708a; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-size: 13px; transition: .2s;">
            🚪 Terminar Sessão
        </button>
    </div>
</div>

<script>
const SESSION_HOURS = 8;
const ADMIN_TOKEN = '{$token}';

function checkSession() {
    const sessionData = localStorage.getItem('contaqua_admin_session');
    
    if (sessionData) {
        const session = JSON.parse(sessionData);
        const now = new Date().getTime();
        
        // Verificar se a sessão ainda é válida
        if (session.token === ADMIN_TOKEN && session.expires > now) {
            // Sessão válida - mostrar dashboard
            document.getElementById('dashboardContent').style.display = 'block';
            document.getElementById('tokenModal').style.display = 'none';
            return;
        }
    }
    
    // Sessão inválida ou expirada - mostrar modal de token
    document.getElementById('dashboardContent').style.display = 'none';
    document.getElementById('tokenModal').style.display = 'flex';
}

function validateSession(e) {
    e.preventDefault();
    const token = document.getElementById('sessionToken').value;
    
    if (token === ADMIN_TOKEN) {
        // Guardar sessão
        const now = new Date().getTime();
        const sessionData = {
            token: token,
            timestamp: now,
            expires: now + (SESSION_HOURS * 60 * 60 * 1000)
        };
        localStorage.setItem('contaqua_admin_session', JSON.stringify(sessionData));
        
        // Mostrar dashboard
        document.getElementById('dashboardContent').style.display = 'block';
        document.getElementById('tokenModal').style.display = 'none';
        
        // Atualizar URL com token
        const url = new URL(window.location.href);
        url.searchParams.set('admin_token', token);
        window.history.replaceState({}, '', url);
    } else {
        alert('Token inválido!');
    }
    return false;
}

function logout() {
    localStorage.removeItem('contaqua_admin_session');
    window.location.href = 'portal';
}

// Verificar sessão ao carregar
checkSession();

// Verificar a cada minuto se a sessão ainda é válida
setInterval(checkSession, 60000);
</script>
HTML;
        
        return self::renderHtmlMinimal('Dashboard - Contaqua Admin', $content);
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
        $flash = $data['flash'] ?? [];
        
        // Flash messages
        $flashHtml = '';
        if (!empty($flash['success'])) {
            $flashHtml .= '<div style="max-width: 900px; margin: 0 auto 20px; padding: 12px 16px; background: #dcfce7; border: 1px solid #86efac; border-radius: 8px; color: #166534; font-size: 14px;">' . htmlspecialchars($flash['success']) . '</div>';
        }
        if (!empty($flash['error'])) {
            $flashHtml .= '<div style="max-width: 900px; margin: 0 auto 20px; padding: 12px 16px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; color: #991b1b; font-size: 14px;">' . htmlspecialchars($flash['error']) . '</div>';
        }
        
        // Role options - roles é array de strings
        $roleOptions = '<option value="">Todos</option>';
        foreach ($roles as $r) {
            $selected = $role === $r ? 'selected' : '';
            $roleOptions .= '<option value="' . htmlspecialchars($r) . '" ' . $selected . '>' . htmlspecialchars($r) . '</option>';
        }
        
        // Users table
        $rows = '';
        foreach ($users as $user) {
            $username = htmlspecialchars((string) ($user['user'] ?? ''), ENT_QUOTES);
            $rows .= '<tr><td>' . $username . '</td><td><span class="chip">' . htmlspecialchars((string) ($user['role'] ?? ''), ENT_QUOTES) . '</span></td><td>' . ((int) ($user['user_id'] ?? 0)) . '</td><td><code>' . htmlspecialchars(substr((string) ($user['token'] ?? ''), 0, 8), ENT_QUOTES) . '...</code></td><td class="actions-cell"><button class="btn ghost small" onclick="openMeterAppQrModal(\'' . $username . '\')">QR</button><button class="btn ghost small" onclick="openEditModal(\'' . $username . '\')">Editar</button><form method="post" class="mini-form"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="admin_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '"><input type="hidden" name="user" value="' . $username . '"><button class="btn dark small" type="submit" onclick="return confirm(\'Eliminar ' . $username . '?\')">Eliminar</button></form></td></tr>';
        }
        
        // Roles para dropdowns de criação/edição
        $roleOptionsCreate = '';
        foreach ($roles as $r) {
            $roleOptionsCreate .= '<option value="' . htmlspecialchars($r) . '">' . htmlspecialchars($r) . '</option>';
        }
        
        $content = <<<HTML
{$flashHtml}
<div class="wrap">
    <div style="text-align: center; padding: 30px 0 20px;">
        <p style="letter-spacing: .15em; font-size: 11px; color: #57708a; margin-bottom: 6px;">ADMIN</p>
        <h1 style="font-weight: 300; font-size: 28px; color: #142032; margin: 0;">Utilizadores</h1>
        <p style="font-size: 13px; color: #57708a; margin-top: 6px;">{$total} utilizadores no sistema</p>
    </div>

    <!-- Ações -->
    <div style="max-width: 900px; margin: 0 auto 20px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <button onclick="openModal('createUserModal')" style="background: #111; color: #fff; border: none; padding: 12px 20px; border-radius: 10px; font-weight: 500; cursor: pointer; transition: .2s;">
            + Novo Utilizador
        </button>
        <a href="?admin_token={$token}" style="background: #fff; color: #142032; border: 1px solid #e0e5eb; padding: 12px 20px; border-radius: 10px; font-weight: 500; text-decoration: none; transition: .2s;">
            ← Dashboard
        </a>
    </div>

    <!-- Filtros -->
    <div style="max-width: 900px; margin: 0 auto 20px;">
        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
            <input type="hidden" name="page" value="users">
            <input type="hidden" name="admin_token" value="{$token}">
            <input type="text" name="search" value="{$search}" placeholder="Pesquisar..." 
                style="padding: 10px 14px; border: 1px solid #e0e5eb; border-radius: 10px; font-size: 14px; min-width: 200px;">
            <select name="role" style="padding: 10px 14px; border: 1px solid #e0e5eb; border-radius: 10px; font-size: 14px; background: #fff;">
                {$roleOptions}
            </select>
            <button type="submit" style="background: #111; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 500; cursor: pointer;">
                Filtrar
            </button>
        </form>
    </div>

    <!-- Tabela -->
    <div style="max-width: 900px; margin: 0 auto 40px;">
        <div style="background: #fff; border: 1px solid #eef1f5; border-radius: 16px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 16px; text-align: left; font-weight: 500; color: #57708a;">Utilizador</th>
                        <th style="padding: 16px; text-align: center; font-weight: 500; color: #57708a;">Role</th>
                        <th style="padding: 16px; text-align: center; font-weight: 500; color: #57708a;">ID</th>
                        <th style="padding: 16px; text-align: left; font-weight: 500; color: #57708a;">Token</th>
                        <th style="padding: 16px; text-align: center; font-weight: 500; color: #57708a;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                </tbody>
            </table>
        </div>
        <p style="text-align: center; margin-top: 16px; font-size: 12px; color: #8e9aab;">
            Página {$page} • Total: {$total} utilizadores
        </p>
    </div>
</div>

<!-- Modal Criar Utilizador -->
<div id="createUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Criar Novo Utilizador</h2>
            <button class="modal-close" onclick="closeModal('createUserModal')">&times;</button>
        </div>
        <form method="POST" action="" class="modal-form">
            <input type="hidden" name="action" value="create_user">
            <input type="hidden" name="admin_token" value="{$token}">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="ex: joao.silva">
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-group">
                    <input type="text" name="password" id="newPassword" required>
                    <button type="button" class="btn small" onclick="generatePassword()">Gerar</button>
                </div>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role">{$roleOptionsCreate}</select>
            </div>
            <div class="form-group">
                <label>Dias válidos</label>
                <input type="number" name="valid_days" value="365" min="1">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn ghost" onclick="closeModal('createUserModal')">Cancelar</button>
                <button type="submit" class="btn">Criar Utilizador</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Utilizador -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Utilizador</h2>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST" action="" class="modal-form">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="admin_token" value="{$token}">
            <input type="hidden" name="user" id="editUserName">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="editUserDisplay" disabled style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>Nova Role (deixe em branco para manter)</label>
                <select name="role"><option value="">Manter atual</option>{$roleOptionsCreate}</select>
            </div>
            <div class="form-group">
                <label>Nova Password (deixe em branco para manter)</label>
                <div class="input-group">
                    <input type="text" name="password">
                    <button type="button" class="btn small" onclick="generateEditPassword()">Gerar</button>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn ghost" onclick="closeModal('editUserModal')">Cancelar</button>
                <button type="submit" class="btn">Guardar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal QR MeterApp -->
<div id="qrModal" class="modal">
    <div class="modal-content" style="max-width: 760px;">
        <div class="modal-header">
            <h2>QR Code MeterApp</h2>
            <button class="modal-close" onclick="closeModal('qrModal')">&times;</button>
        </div>
        <div class="modal-body" style="display:grid; grid-template-columns: minmax(320px, 1fr) 300px; gap:20px; align-items:start;">
            <div>
                <div class="form-group">
                    <label>Utilizador</label>
                    <input type="text" id="qrUserName" placeholder="ex: joao">
                </div>
                <div class="form-group">
                    <label>Password do utilizador</label>
                    <input type="text" id="qrPassword" placeholder="password do utilizador">
                </div>
                <div style="display:grid; grid-template-columns: 1fr 120px; gap:10px;">
                    <div class="form-group">
                        <label>Servidor API</label>
                        <input type="text" id="qrServer" placeholder="213.63.236.100">
                    </div>
                    <div class="form-group">
                        <label>Porta</label>
                        <input type="text" id="qrPort" placeholder="80">
                    </div>
                </div>
                <div style="margin-top: 10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="btn" onclick="generateMeterAppQr()">Gerar QR</button>
                    <button type="button" class="btn ghost" onclick="copyMeterAppPayload()">Copiar payload</button>
                </div>
                <div class="form-group" style="margin-top:10px;">
                    <label>Payload</label>
                    <textarea id="qrPayload" rows="3" style="width:100%; padding:10px; border:1px solid #e0e5eb; border-radius:8px; font-size:12px; resize:vertical;" readonly></textarea>
                </div>
            </div>
            <div style="text-align: center;">
                <div style="padding: 12px; background: white; border-radius: 10px; display:inline-block; border:1px solid #e0e5eb;">
                    <canvas id="qrCanvas" width="280" height="280"></canvas>
                </div>
                <p id="qrHint" style="font-size:12px; color:#666; margin-top:10px;">Preencha os dados e clique em Gerar QR.</p>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn ghost" onclick="closeModal('qrModal')">Fechar</button>
            <button type="button" class="btn" onclick="downloadQR()">Download QR</button>
        </div>
    </div>
</div>

<script>
// Modal functions
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close on backdrop click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal(modal.id);
    });
});

function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let pass = '';
    for (let i = 0; i < 12; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('newPassword').value = pass;
}

function generateEditPassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let pass = '';
    for (let i = 0; i < 12; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.querySelector('#editUserModal input[name="password"]').value = pass;
}

function openEditModal(user, role) {
    document.getElementById('editUserName').value = user;
    document.getElementById('editUserDisplay').value = user;
    openModal('editUserModal');
}

function openMeterAppQrModal(user) {
    const protocol = window.location.protocol === 'https:' ? 'https' : 'http';
    const server = window.location.hostname || '213.63.236.100';
    const fallbackPort = protocol === 'https' ? '443' : '80';
    const currentPort = window.location.port || fallbackPort;

    document.getElementById('qrUserName').value = user || '';
    document.getElementById('qrPassword').value = '';
    document.getElementById('qrServer').value = server;
    document.getElementById('qrPort').value = currentPort;
    updateMeterAppPayloadPreview();
    document.getElementById('qrHint').textContent = user
        ? ('Gerar QR para ' + user + '. Falta preencher a password para gerar o QR.')
        : 'Preencha os dados e clique em Gerar QR.';
    clearQrCanvas();
    openModal('qrModal');
}

function clearQrCanvas() {
    const canvas = document.getElementById('qrCanvas');
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
}

function sanitizePort(port) {
    const cleaned = String(port || '').trim();
    if (!/^\d+$/.test(cleaned)) {
        return '';
    }

    const numeric = Number(cleaned);
    if (numeric < 1 || numeric > 65535) {
        return '';
    }

    return String(numeric);
}

function buildMeterAppPayload(user, pass, server, port) {
    return [
        'user=' + encodeURIComponent(user),
        'pass=' + encodeURIComponent(pass),
        'server=' + encodeURIComponent(server),
        'port=' + encodeURIComponent(port)
    ].join('&');
}

function updateMeterAppPayloadPreview() {
    const user = document.getElementById('qrUserName').value.trim();
    const pass = document.getElementById('qrPassword').value.trim();
    const server = document.getElementById('qrServer').value.trim();
    const port = sanitizePort(document.getElementById('qrPort').value) || String(document.getElementById('qrPort').value || '').trim();

    if (!user && !pass && !server && !port) {
        document.getElementById('qrPayload').value = '';
        return;
    }

    document.getElementById('qrPayload').value = buildMeterAppPayload(user, pass, server, port);
}

function ensureQrLibrary() {
    return new Promise((resolve, reject) => {
        if (typeof QRCode !== 'undefined' && typeof QRCode.toCanvas === 'function') {
            resolve();
            return;
        }

        const existing = document.getElementById('meterapp-qr-lib');
        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('Falha ao carregar biblioteca QR')));
            return;
        }

        const script = document.createElement('script');
        script.id = 'meterapp-qr-lib';
        script.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js';
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Falha ao carregar biblioteca QR'));
        document.head.appendChild(script);
    });
}

async function generateMeterAppQr() {
    const user = document.getElementById('qrUserName').value.trim();
    const pass = document.getElementById('qrPassword').value.trim();
    const server = document.getElementById('qrServer').value.trim();
    const port = sanitizePort(document.getElementById('qrPort').value);

    updateMeterAppPayloadPreview();

    if (!user || !pass || !server || !port) {
        document.getElementById('qrHint').textContent = 'Preencha utilizador, password, servidor e porta válida (1-65535).';
        alert('Preencha utilizador, password, servidor e porta válida (1-65535).');
        return;
    }

    try {
        await ensureQrLibrary();
    } catch (e) {
        alert('Não foi possível carregar a biblioteca de QR.');
        return;
    }

    const payload = buildMeterAppPayload(user, pass, server, port);
    const canvas = document.getElementById('qrCanvas');
    document.getElementById('qrPayload').value = payload;

    QRCode.toCanvas(canvas, payload, {
        width: 260,
        margin: 2,
        color: { dark: '#111827', light: '#ffffff' }
    }, function(error) {
        if (error) {
            alert('Não foi possível gerar o QR Code.');
            return;
        }
        document.getElementById('qrHint').textContent = 'QR gerado com sucesso.';
    });
}

function copyMeterAppPayload() {
    updateMeterAppPayloadPreview();
    const payload = document.getElementById('qrPayload').value.trim();
    if (!payload) {
        alert('Gera primeiro o QR para copiar o payload.');
        return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(payload).catch(() => {});
    }
}

['qrUserName', 'qrPassword', 'qrServer', 'qrPort'].forEach(function(id) {
    const element = document.getElementById(id);
    if (!element) {
        return;
    }

    element.addEventListener('input', updateMeterAppPayloadPreview);
});

function downloadQR() {
    const payload = document.getElementById('qrPayload').value.trim();
    if (!payload) {
        alert('Gera primeiro o QR antes de fazer download.');
        return;
    }

    const canvas = document.getElementById('qrCanvas');
    const link = document.createElement('a');
    const user = document.getElementById('qrUserName').value.trim() || 'user';
    link.download = 'meterapp-' + user + '-login-qr.png';
    link.href = canvas.toDataURL();
    link.click();
}
</script>
HTML;
        
        return self::renderHtmlMinimal('Utilizadores - Contaqua Admin', $content);
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
        $flash = $data['flash'] ?? [];
        $allUsers = $data['all_users'] ?? [];
        $usersJson = json_encode($allUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        
        // Flash messages
        $flashHtml = '';
        if (!empty($flash['success'])) {
            $flashHtml .= '<div style="max-width: 900px; margin: 0 auto 20px; padding: 12px 16px; background: #dcfce7; border: 1px solid #86efac; border-radius: 8px; color: #166534; font-size: 14px;">' . htmlspecialchars($flash['success']) . '</div>';
        }
        if (!empty($flash['error'])) {
            $flashHtml .= '<div style="max-width: 900px; margin: 0 auto 20px; padding: 12px 16px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; color: #991b1b; font-size: 14px;">' . htmlspecialchars($flash['error']) . '</div>';
        }
        
        // Meters table
        $rows = '';
        foreach ($meters as $meter) {
            $assignedUsers = implode(', ', $meter['assigned_users'] ?? []);
            $deveui = htmlspecialchars($meter['deveui'] ?? '');
            $usersEscaped = htmlspecialchars($assignedUsers);
            $rows .= '<tr><td><code>' . $deveui . '</code></td><td>' . $usersEscaped . '</td><td>' . ($meter['valid_until'] ?? '-') . '</td><td class="actions-cell"><button class="btn ghost small" onclick="openEditMeterModal(\'' . $deveui . '\', \'' . $usersEscaped . '\')">Editar</button><form method="post" class="mini-form"><input type="hidden" name="action" value="delete_meter"><input type="hidden" name="admin_token" value="' . htmlspecialchars($token) . '"><input type="hidden" name="meterid" value="' . $deveui . '"><button class="btn dark small" type="submit" onclick="return confirm(\'Eliminar ' . $deveui . '?\')">Eliminar</button></form></td></tr>';
        }
        
        $content = <<<HTML
{$flashHtml}
<div class="wrap">
    <div style="text-align: center; padding: 30px 0 20px;">
        <p style="letter-spacing: .15em; font-size: 11px; color: #57708a; margin-bottom: 6px;">ADMIN</p>
        <h1 style="font-weight: 300; font-size: 28px; color: #142032; margin: 0;">Contadores</h1>
        <p style="font-size: 13px; color: #57708a; margin-top: 6px;">{$total} contadores no sistema</p>
    </div>

    <!-- Ações -->
    <div style="max-width: 900px; margin: 0 auto 20px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <button onclick="openModal('createMeterModal')" style="background: #111; color: #fff; border: none; padding: 12px 20px; border-radius: 10px; font-weight: 500; cursor: pointer; transition: .2s;">
            + Associar Contador
        </button>
        <button onclick="openModal('importMetersModal')" style="background: #fff; color: #142032; border: 1px solid #e0e5eb; padding: 12px 20px; border-radius: 10px; font-weight: 500; cursor: pointer; transition: .2s;">
            Importar Lista
        </button>
        <a href="?admin_token={$token}" style="background: #fff; color: #142032; border: 1px solid #e0e5eb; padding: 12px 20px; border-radius: 10px; font-weight: 500; text-decoration: none; transition: .2s;">
            ← Dashboard
        </a>
    </div>

    <!-- Filtros -->
    <div style="max-width: 900px; margin: 0 auto 20px;">
        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
            <input type="hidden" name="page" value="meters">
            <input type="hidden" name="admin_token" value="{$token}">
            <input type="text" name="search" value="{$search}" placeholder="Pesquisar DevEUI ou utilizador..." 
                style="padding: 10px 14px; border: 1px solid #e0e5eb; border-radius: 10px; font-size: 14px; min-width: 280px;">
            <button type="submit" style="background: #111; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 500; cursor: pointer;">
                Filtrar
            </button>
        </form>
    </div>

    <!-- Tabela -->
    <div style="max-width: 900px; margin: 0 auto 40px;">
        <div style="background: #fff; border: 1px solid #eef1f5; border-radius: 16px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 16px; text-align: left; font-weight: 500; color: #57708a;">DevEUI</th>
                        <th style="padding: 16px; text-align: left; font-weight: 500; color: #57708a;">Utilizadores</th>
                        <th style="padding: 16px; text-align: center; font-weight: 500; color: #57708a;">Válido até</th>
                        <th style="padding: 16px; text-align: center; font-weight: 500; color: #57708a;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                </tbody>
            </table>
        </div>
        <p style="text-align: center; margin-top: 16px; font-size: 12px; color: #8e9aab;">
            Página {$page} • Total: {$total} contadores
        </p>
    </div>
</div>

<!-- Modal Associar Contador -->
<div id="createMeterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Associar Novo Contador</h2>
            <button class="modal-close" onclick="closeModal('createMeterModal')">&times;</button>
        </div>
        <form method="POST" action="" class="modal-form">
            <input type="hidden" name="action" value="create_meter">
            <input type="hidden" name="admin_token" value="{$token}">
            <input type="hidden" name="users" id="createMeterSelectedUsers">
            <div class="form-group">
                <label>DevEUI (16 caracteres hexadecimais)</label>
                <input type="text" name="meterid" required pattern="[0-9A-Fa-f]{16}" placeholder="ex: 02F8CCFFFE483203">
            </div>
            <div class="form-group">
                <label>Utilizadores</label>
                <div class="user-picker" id="createMeterUserPicker">
                    <input type="search" class="picker-input" id="createMeterUserSearch" placeholder="Pesquisar por username..." autocomplete="off">
                    <div class="picker-results" id="createMeterUserResults"></div>
                    <div class="picker-selected" id="createMeterUserSelected">
                        <span class="picker-empty">Nenhum utilizador selecionado</span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Dias válidos</label>
                <input type="number" name="valid_days" value="365" min="1">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn ghost" onclick="closeModal('createMeterModal')">Cancelar</button>
                <button type="submit" class="btn">Associar Contador</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Importar Lista de Contadores -->
<div id="importMetersModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Importar Lista de Contadores</h2>
            <button class="modal-close" onclick="closeModal('importMetersModal')">&times;</button>
        </div>
        <form method="POST" action="" class="modal-form">
            <input type="hidden" name="action" value="bulk_import_meters">
            <input type="hidden" name="admin_token" value="{$token}">
            <input type="hidden" name="users" id="importMeterSelectedUsers">
            <div class="form-group">
                <label>Utilizadores</label>
                <div class="user-picker" id="importMeterUserPicker">
                    <input type="search" class="picker-input" id="importMeterUserSearch" placeholder="Pesquisar por username..." autocomplete="off">
                    <div class="picker-results" id="importMeterUserResults"></div>
                    <div class="picker-selected" id="importMeterUserSelected">
                        <span class="picker-empty">Nenhum utilizador selecionado</span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Lista de DevEUIs (um por linha)</label>
                <textarea name="meter_list" required rows="10" placeholder="02F8CCFFFE483203&#10;02F8CCFFFE483204&#10;02F8CCFFFE483205"></textarea>
            </div>
            <div class="form-group">
                <label>Dias válidos</label>
                <input type="number" name="valid_days" value="365" min="1">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn ghost" onclick="closeModal('importMetersModal')">Cancelar</button>
                <button type="submit" class="btn">Importar Lista</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Atribuições -->
<div id="editMeterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editar Atribuições do Contador</h2>
            <button class="modal-close" onclick="closeModal('editMeterModal')">&times;</button>
        </div>
        <form method="POST" action="" class="modal-form">
            <input type="hidden" name="action" value="assign_meter_users">
            <input type="hidden" name="admin_token" value="{$token}">
            <input type="hidden" name="meterid" id="editMeterId">
            <input type="hidden" name="users" id="editMeterSelectedUsers">
            <div class="form-group">
                <label>DevEUI</label>
                <input type="text" id="editMeterDeveui" disabled style="background:#f5f5f5">
            </div>
            <div class="form-group">
                <label>Utilizadores</label>
                <div class="user-picker" id="editMeterUserPicker">
                    <input type="search" class="picker-input" id="editMeterUserSearch" placeholder="Pesquisar por username..." autocomplete="off">
                    <div class="picker-results" id="editMeterUserResults"></div>
                    <div class="picker-selected" id="editMeterUserSelected">
                        <span class="picker-empty">Nenhum utilizador selecionado</span>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn ghost" onclick="closeModal('editMeterModal')">Cancelar</button>
                <button type="submit" class="btn">Guardar Atribuições</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close on backdrop click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal(modal.id);
    });
});

function openEditMeterModal(deveui, users) {
    document.getElementById('editMeterId').value = deveui;
    document.getElementById('editMeterDeveui').value = deveui;
    // Pre-carregar utilizadores no picker de edição
    const userArray = users ? users.split(',').map(u => u.trim()).filter(u => u) : [];
    initUserPicker('editMeter', allUsers, userArray);
    openModal('editMeterModal');
}

// User Picker System
const allUsers = {$usersJson} || [];

// Initialize user picker for a modal
function initUserPicker(prefix, users, selectedUsernames) {
    if (!users || users.length === 0) {
        console.warn('No users available for picker ' + prefix);
    }
    
    const searchInput = document.getElementById(prefix + 'UserSearch');
    const resultsDiv = document.getElementById(prefix + 'UserResults');
    const selectedDiv = document.getElementById(prefix + 'UserSelected');
    const hiddenInput = document.getElementById(prefix + 'SelectedUsers');
    
    if (!searchInput || !resultsDiv || !selectedDiv || !hiddenInput) {
        console.error('Picker elements not found for prefix: ' + prefix);
        return;
    }
    
    selectedUsernames = selectedUsernames || [];
    let selected = selectedUsernames.map(function(u) {
        const found = users.find(function(x) { return x.username === u; });
        return found || { username: u, role: 'user' };
    });
    
    function updateSelected() {
        selectedDiv.innerHTML = '';
        if (selected.length === 0) {
            selectedDiv.innerHTML = '<span class="picker-empty">Nenhum utilizador selecionado</span>';
        } else {
            selected.forEach(function(user, idx) {
                const chip = document.createElement('span');
                chip.className = 'picker-chip';
                chip.innerHTML = user.username + ' <span class="remove" data-idx="' + idx + '">×</span>';
                selectedDiv.appendChild(chip);
            });
            
            // Add click handlers for remove buttons
            selectedDiv.querySelectorAll('.remove').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const idx = parseInt(this.getAttribute('data-idx'));
                    selected.splice(idx, 1);
                    updateSelected();
                });
            });
        }
        hiddenInput.value = selected.map(function(u) { return u.username; }).join(',');
    }
    
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        resultsDiv.innerHTML = '';
        
        if (query.length < 1) {
            resultsDiv.classList.remove('active');
            return;
        }
        
        if (!users || users.length === 0) {
            resultsDiv.innerHTML = '<div class="picker-result">Nenhum utilizador disponivel</div>';
            resultsDiv.classList.add('active');
            return;
        }
        
        const filtered = users.filter(function(u) {
            return u.username.toLowerCase().includes(query) && 
                !selected.some(function(s) { return s.username === u.username; });
        });
        
        if (filtered.length === 0) {
            resultsDiv.innerHTML = '<div class="picker-result">Nenhum utilizador encontrado</div>';
        } else {
            filtered.forEach(function(user) {
                const div = document.createElement('div');
                div.className = 'picker-result';
                div.textContent = user.username + ' (' + user.role + ')';
                div.addEventListener('click', function() {
                    selected.push(user);
                    updateSelected();
                    searchInput.value = '';
                    resultsDiv.classList.remove('active');
                });
                resultsDiv.appendChild(div);
            });
        }
        resultsDiv.classList.add('active');
    });
    
    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.classList.remove('active');
        }
    });
    
    updateSelected();
}

// Initialize pickers on page load
document.addEventListener('DOMContentLoaded', function() {
    if (allUsers && allUsers.length > 0) {
        initUserPicker('createMeter', allUsers, []);
        initUserPicker('importMeter', allUsers, []);
    } else {
        console.warn('No users available for pickers');
    }
});
</script>
</body>
</html>
HTML;
        
        return self::renderHtmlMinimal('Contadores - Contaqua Admin', $content);
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
        
        /* MODALS */
        .modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:2000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
        .modal.active{display:flex}
        .modal-content{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-width:500px;width:90%;max-height:90vh;overflow-y:auto;animation:modalSlide .3s}
        @keyframes modalSlide{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
        .modal-header{padding:20px 24px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}
        .modal-header h2{margin:0;font-size:20px}
        .modal-close{background:none;border:none;font-size:28px;cursor:pointer;color:#888;line-height:1;padding:0;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:.2s}
        .modal-close:hover{background:#f5f5f5;color:#333}
        .modal-form{padding:24px}
        .modal-body{padding:24px}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:6px;font-weight:500;font-size:14px;color:var(--text)}
        .form-group input,.form-group select{width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;font-size:14px;transition:.2s}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--accent)}
        .input-group{display:flex;gap:8px}
        .input-group input{flex:1}
        .modal-actions{padding:20px 24px;border-top:1px solid var(--line);display:flex;gap:10px;justify-content:flex-end}
        .modal-actions .btn{padding:10px 20px}
        
        textarea{width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;font-size:14px;min-height:120px;resize:vertical;font-family:inherit}
        textarea:focus{outline:none;border-color:var(--accent)}
    </style>
</head>
<body>
    <nav style="background:linear-gradient(90deg,#1264a3,#0a8f6a);color:#fff;padding:0 20px;box-shadow:0 4px 12px rgba(0,0,0,.1)">
        <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;height:56px">
            <div style="display:flex;gap:20px;align-items:center">
                <a href="?admin_token={$token}" style="color:#fff;text-decoration:none;font-weight:700;font-size:18px">Contaqua Admin</a>
                <a href="?admin_token={$token}" style="color:#fff;text-decoration:none;opacity:.9;font-size:14px">Dashboard</a>
                <a href="?page=users&admin_token={$token}" style="color:#fff;text-decoration:none;opacity:.9;font-size:14px">Utilizadores</a>
                <a href="?page=meters&admin_token={$token}" style="color:#fff;text-decoration:none;opacity:.9;font-size:14px">Contadores</a>
            </div>
            <a href="." style="color:#fff;text-decoration:none;opacity:.8;font-size:13px">Logout</a>
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
    
    /**
     * Render minimal HTML page - igual ao portal
     */
    private static function renderHtmlMinimal(string $title, string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        *{box-sizing:border-box}
        body{font-family:"Segoe UI",Tahoma,sans-serif;background:radial-gradient(circle at 12% 10%,#e5f0ff,transparent 35%),radial-gradient(circle at 86% 20%,#e8fff6,transparent 30%),#f4f6fb;color:#142032;margin:0;min-height:100vh}
        .wrap{max-width:1240px;margin:0 auto;padding:20px}
        
        /* Modal styles */
        .modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:2000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
        .modal.active{display:flex}
        .modal-content{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-width:500px;width:90%;max-height:90vh;overflow-y:auto;animation:modalSlide .3s}
        @keyframes modalSlide{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
        .modal-header{padding:20px 24px;border-bottom:1px solid #e0e5eb;display:flex;justify-content:space-between;align-items:center}
        .modal-header h2{margin:0;font-size:20px}
        .modal-close{background:none;border:none;font-size:28px;cursor:pointer;color:#888;line-height:1;padding:0;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:.2s}
        .modal-close:hover{background:#f5f5f5;color:#333}
        .modal-form{padding:24px}
        .modal-body{padding:24px}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:6px;font-weight:500;font-size:14px;color:#142032}
        .form-group input,.form-group select{width:100%;padding:12px;border:1px solid #e0e5eb;border-radius:10px;font-size:14px;transition:.2s}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:#1264a3}
        .input-group{display:flex;gap:8px}
        .input-group input{flex:1}
        .modal-actions{padding:20px 24px;border-top:1px solid #e0e5eb;display:flex;gap:10px;justify-content:flex-end}
        .modal-actions .btn{padding:10px 20px}
        
        /* Button styles */
        .btn{background:#111;color:#fff;border:none;padding:10px 14px;border-radius:10px;font-weight:500;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:.2s}
        .btn:hover{background:#333}
        .btn.ghost{background:#fff;color:#142032;border:1px solid #e0e5eb}
        .btn.ghost:hover{background:#f8fafc}
        .btn.small{padding:7px 10px;font-size:12px;border-radius:8px}
        
        /* Form elements */
        textarea{width:100%;padding:12px;border:1px solid #e0e5eb;border-radius:10px;font-size:14px;min-height:120px;resize:vertical;font-family:inherit}
        textarea:focus{outline:none;border-color:#1264a3}
        
        /* Table styles */
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;border-bottom:1px solid #e0e5eb;text-align:left;vertical-align:top;font-size:13px}
        th{font-size:11px;color:#57708a;text-transform:uppercase;letter-spacing:.7px;background:#f8fafc}
        code{font-size:12px;word-break:break-all;background:#f3f8fd;padding:2px 6px;border-radius:6px}
        .chip{display:inline-flex;align-items:center;background:#eef4ff;border:1px solid #c6dbff;color:#173b62;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
        
        /* Menu flutuante */
        .corner-menu{position:fixed;bottom:20px;right:20px;z-index:1000}
        .corner-btn{width:56px;height:56px;background:#111;border:none;border-radius:14px;color:#fff;font-size:24px;cursor:pointer;box-shadow:0 4px 15px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;transition:transform .2s,box-shadow .2s}
        .corner-btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.4)}
        .corner-menu-items{position:absolute;bottom:66px;right:0;display:flex;flex-direction:column;gap:8px;opacity:0;transform:translateY(10px);pointer-events:none;transition:opacity .2s,transform .2s}
        .corner-menu.open .corner-menu-items{opacity:1;transform:translateY(0);pointer-events:auto}
        .corner-item{width:48px;height:48px;background:#fff;border:1px solid #e0e5eb;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,.1);transition:transform .2s}
        .corner-item:hover{transform:scale(1.1)}
        .corner-item.logout:hover{background:#fff0f0;border-color:#f5c2c2}
        
        /* Panel e cards */
        .panel{background:#fff;border:1px solid #e0e5eb;border-radius:14px;padding:20px;margin-bottom:20px}
        .hero{display:flex;justify-content:space-between;gap:14px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px}
        .table-tools{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:15px}
        .table-tools input,.table-tools select{max-width:280px;padding:8px 12px;border:1px solid #e0e5eb;border-radius:8px;font-size:13px}
        .actions-cell{display:flex;gap:6px;flex-wrap:wrap}
        .mini-form{display:inline-flex}
        
        /* User Picker */
        .user-picker{position:relative;margin-bottom:16px;z-index:50}
        .picker-input{width:100%;padding:10px 14px;border:1px solid #e0e5eb;border-radius:10px;font-size:14px;background:#fff;position:relative;z-index:51}
        .picker-input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
        .picker-results{position:absolute;top:100%;left:0;right:0;max-height:200px;overflow-y:auto;background:#fff;border:1px solid #e0e5eb;border-radius:10px;margin-top:4px;box-shadow:0 10px 40px rgba(0,0,0,.12);z-index:100;display:none;pointer-events:none}
        .picker-results.active{display:block;pointer-events:auto}
        .picker-result{padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:.15s;background:#fff}
        .picker-result:hover{background:#f8fafc}
        .picker-result:last-child{border-bottom:none}
        .picker-selected{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;min-height:32px;position:relative;z-index:52}
        .picker-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#e0e7ff;border-radius:20px;font-size:13px;color:#3730a3;border:1px solid #c7d2fe}
        .picker-chip .remove{width:18px;height:18px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#a5b4fc;color:#312e81;cursor:pointer;font-size:12px;transition:.15s;border:none}
        .picker-chip .remove:hover{background:#818cf8;color:#fff}
        .picker-empty{color:#94a3b8;font-size:13px;font-style:italic}
    </style>
</head>
<body>
    {$content}
</body>
</html>
HTML;
    }
}

<?php

declare(strict_types=1);

namespace App\Admin;

final class AdminView
{
    public static function unauthorized(): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>Unauthorized</title></head><body><h1>401 Unauthorized</h1><p>Missing admin_token.</p></body></html>';
    }

    /** @param array<string, int> $counts */
    /** @param array<int, array<string, mixed>> $sessions */
    /** @param array<int, array<string, mixed>> $users */
    /** @param array<int, array<string, mixed>> $meters */
    /** @param array<string, mixed> $state */
    public static function dashboard(array $counts, array $sessions, array $users, array $meters, array $state = []): string
    {
        $flash = (string) ($state['flash'] ?? '');
        $flashType = strtolower((string) ($state['flash_type'] ?? 'success'));
        $adminToken = (string) ($state['admin_token'] ?? '');
        $roles = is_array($state['roles'] ?? null) ? $state['roles'] : ['TECHNICIAN', 'MANAGER', 'MANUFACTURER', 'FACTORY'];
        $mockMode = (bool) ($state['mock_mode'] ?? false);

        $roleOptions = '';
        foreach ($roles as $role) {
            $roleString = strtoupper((string) $role);
            $selected = $roleString === 'TECHNICIAN' ? ' selected' : '';
            $roleOptions .= '<option value="' . htmlspecialchars($roleString, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                . htmlspecialchars($roleString, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }

        $compactValue = static function (string $value): string {
            if ($value === '') {
                return '-';
            }

            if (strlen($value) <= 16) {
                return $value;
            }

            return substr($value, 0, 6) . '...' . substr($value, -4);
        };

        $roleLabelFromAccess = static function (int $access): string {
            if ($access === 2) {
                return 'MANAGER';
            }
            if ($access === 3) {
                return 'MANUFACTURER';
            }
            if ($access === 4) {
                return 'FACTORY';
            }

            return 'TECHNICIAN';
        };

        $cards = '';
        foreach ($counts as $name => $count) {
            $cards .= '<article class="kpi"><span>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span><strong>' . (int) $count . '</strong></article>';
        }

        $userRows = '';
        $userDirectory = [];
        foreach ($users as $user) {
            $userName = (string) ($user['user'] ?? '');
            $access = (int) ($user['access'] ?? 0);
            $token = (string) ($user['token'] ?? '');
            $roleLabel = $roleLabelFromAccess($access);

            $userSafe = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
            $roleSafe = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');
            $salt = (string) ($user['salt'] ?? '');
            $tokenSafeValue = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
            $saltSafeValue = htmlspecialchars($salt, ENT_QUOTES, 'UTF-8');

            $authTechValue = (string) ($user['auth_tech'] ?? '');
            $authTechSafeValue = htmlspecialchars($authTechValue, ENT_QUOTES, 'UTF-8');
            $accessSafeValue = (int) ($user['access'] ?? 0);

            $userRows .= '<tr data-filter-row="1" data-user="' . htmlspecialchars(strtolower($userName), ENT_QUOTES, 'UTF-8') . '" data-role="' . htmlspecialchars(strtolower($roleLabel), ENT_QUOTES, 'UTF-8') . '" data-userid="' . (int) ($user['user_id'] ?? 0) . '">'
                . '<td>' . $userSafe . '</td>'
                . '<td><span class="chip">' . $roleSafe . '</span> <small>#' . $access . '</small></td>'
                . '<td>' . (int) ($user['user_id'] ?? 0) . '</td>'
                . '<td><small>Salt:</small> <code title="' . $saltSafeValue . '">' . htmlspecialchars($compactValue($salt), ENT_QUOTES, 'UTF-8') . '</code><br><small>Token:</small> <code title="' . $tokenSafeValue . '">' . htmlspecialchars($compactValue($token), ENT_QUOTES, 'UTF-8') . '</code></td>'
                . '<td><code>********</code></td>'
                . '<td class="actions-cell">'
                . '<button type="button" class="btn ghost small" data-open-modal="modal-user-qr" data-qr-user="' . $userSafe . '" data-qr-token="' . $tokenSafeValue . '" data-qr-authtech="' . $authTechSafeValue . '" data-qr-access="' . $accessSafeValue . '">QR</button>'
                . '<button type="button" class="btn ghost small" data-open-modal="modal-edit-user" data-edit-user="' . $userSafe . '" data-edit-role="' . $roleSafe . '">Editar</button>'
                . '<form method="post" class="mini-form" onsubmit="return confirm(\'Eliminar utilizador ' . $userSafe . '?\');">'
                . '<input type="hidden" name="action" value="delete_user">'
                . '<input type="hidden" name="admin_token" value="' . htmlspecialchars($adminToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="user" value="' . $userSafe . '">'
                . '<button class="btn dark small" type="submit">Eliminar</button>'
                . '</form>'
                . '</td>'
                . '</tr>';

            if ($userName !== '') {
                $userDirectory[$userName] = [
                    'user' => $userName,
                    'role' => $roleLabel,
                    'access' => $access,
                    'user_id' => (int) ($user['user_id'] ?? 0),
                    'auth_tech' => (string) ($user['auth_tech'] ?? ''),
                    'token' => $token,
                ];
            }
        }
        if ($userRows === '') {
            $userRows = '<tr><td colspan="6">Sem utilizadores registados.</td></tr>';
        }

        $userDirectoryJson = json_encode(array_values($userDirectory), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($userDirectoryJson)) {
            $userDirectoryJson = '[]';
        }

        $meterRows = '';
        foreach ($meters as $meter) {
            $deveui = strtoupper((string) ($meter['deveui'] ?? $meter['meterid'] ?? ''));
            $authKeys = [];
            if (isset($meter['authkeys']) && is_array($meter['authkeys'])) {
                foreach ($meter['authkeys'] as $authKey) {
                    if (is_string($authKey) && $authKey !== '') {
                        $authKeys[] = '<code>' . htmlspecialchars($authKey, ENT_QUOTES, 'UTF-8') . '</code>';
                    }
                }
            }

            $assignedUsers = [];
            if (isset($meter['assigned_users']) && is_array($meter['assigned_users'])) {
                foreach ($meter['assigned_users'] as $assignedUser) {
                    if (is_string($assignedUser) && trim($assignedUser) !== '') {
                        $assignedUsers[] = trim($assignedUser);
                    }
                }
            }

            $assignedUsersCsv = htmlspecialchars(implode(', ', $assignedUsers), ENT_QUOTES, 'UTF-8');
            $assignedUsersHtml = '-';
            if ($assignedUsers !== []) {
                $chips = [];
                foreach ($assignedUsers as $assignedUser) {
                    $chips[] = '<span class="chip">' . htmlspecialchars($assignedUser, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                $assignedUsersHtml = implode(' ', $chips);
            }

            $deveuiSafe = htmlspecialchars($deveui, ENT_QUOTES, 'UTF-8');

            $meterRows .= '<tr data-filter-row="1" data-deveui="' . htmlspecialchars(strtolower($deveui), ENT_QUOTES, 'UTF-8') . '" data-users="' . htmlspecialchars(strtolower(implode(' ', $assignedUsers)), ENT_QUOTES, 'UTF-8') . '">'
                . '<td><code>' . $deveuiSafe . '</code></td>'
                . '<td>' . ($authKeys === [] ? '-' : implode('<br>', $authKeys)) . '</td>'
                . '<td>' . $assignedUsersHtml . '</td>'
                . '<td class="actions-cell">'
                . '<button type="button" class="btn ghost small" data-open-modal="modal-assign-meter" data-meterid="' . $deveuiSafe . '" data-users-csv="' . $assignedUsersCsv . '">Editar</button>'
                . '<button type="button" class="btn ghost small" data-open-modal="modal-assign-meter" data-meterid="' . $deveuiSafe . '" data-users-csv="' . $assignedUsersCsv . '">Atribuições</button>'
                . '<form method="post" class="mini-form" onsubmit="return confirm(\'Eliminar contador ' . $deveuiSafe . '?\');">'
                . '<input type="hidden" name="action" value="delete_meter">'
                . '<input type="hidden" name="admin_token" value="' . htmlspecialchars($adminToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="meterid" value="' . $deveuiSafe . '">'
                . '<button class="btn dark small" type="submit">Eliminar</button>'
                . '</form>'
                . '</td>'
                . '</tr>';
        }
        if ($meterRows === '') {
            $meterRows = '<tr><td colspan="4">Sem contadores registados.</td></tr>';
        }

        $flashHtml = '';
        if ($flash !== '') {
            $flashClass = $flashType === 'error' ? 'alert error' : 'alert';
            $flashHtml = '<div class="' . $flashClass . '">' . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $modeNotice = $mockMode
            ? '<div class="warn">MOCK_MODE ativo: os registos abaixo são simulados e não persistem no MongoDB.</div>'
            : '';

        $tokenSafe = htmlspecialchars($adminToken, ENT_QUOTES, 'UTF-8');

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meter API Admin</title>
<style>
:root{--bg:#f4f6fb;--card:#ffffff;--line:#d8e1eb;--text:#142032;--muted:#57708a;--accent:#1264a3;--accent-2:#0a8f6a}
*{box-sizing:border-box}
body{font-family:"Trebuchet MS","Segoe UI",Tahoma,sans-serif;background:radial-gradient(circle at 12% 10%,#e5f0ff,transparent 35%),radial-gradient(circle at 86% 20%,#e8fff6,transparent 30%),var(--bg);color:var(--text);margin:0}
main{max-width:1240px;margin:0 auto;padding:24px 20px 40px}
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
.tables{display:grid;grid-template-columns:1fr;gap:12px}
.table-tools{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.table-tools input,.table-tools select{max-width:280px}
.table-empty{display:none}
.user-picker{border:1px solid var(--line);border-radius:10px;padding:10px;background:#f8fbff}
.picker-results{display:grid;gap:6px;max-height:190px;overflow:auto;margin-top:8px}
.picker-option{display:flex;justify-content:space-between;gap:8px;align-items:center;padding:8px;border:1px solid #dbe6f5;border-radius:8px;background:#fff;cursor:pointer;text-align:left}
.picker-option:hover{border-color:#9dc2ea;background:#f4f9ff}
.picker-option small{display:block;color:var(--muted)}
.picker-selected{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.picker-chip{display:inline-flex;align-items:center;gap:6px;background:#e6f2ff;border:1px solid #bfdcff;padding:4px 8px;border-radius:999px;font-size:12px}
.picker-chip button{border:none;background:transparent;color:#174a79;font-weight:700;cursor:pointer;padding:0 2px}
.verify-status{padding:8px 10px;border-radius:8px;border:1px solid #d9e5f2;background:#f7fbff;color:#23415f;font-size:12px}
.verify-status.ok{border-color:#9fd7b8;background:#edf9f2;color:#0d5c35}
.verify-status.error{border-color:#f2b2b2;background:#fff0f0;color:#8a1f1f}
table{width:100%;border-collapse:collapse}
th,td{padding:9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}
th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.7px}
code{font-size:12px;word-break:break-all;background:#f3f8fd;padding:2px 4px;border-radius:6px}
.alert{background:#e8f4ff;border:1px solid #afd3ff;color:#084c8d;padding:10px 12px;border-radius:10px;margin-bottom:12px}
.alert.error{background:#fff0f0;border-color:#f3b4b4;color:#8a1f1f}
.warn{background:#fff4e5;border:1px solid #ffd199;color:#7a4600;padding:10px 12px;border-radius:10px;margin-bottom:12px}
.result-grid{display:grid;grid-template-columns:minmax(280px,1fr) 220px;gap:14px;align-items:flex-start}
.actions-inline{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.qr{width:220px;height:220px;border:1px solid var(--line);border-radius:10px;background:#fff;padding:8px}
.panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
.qr-card{display:flex;flex-direction:column;align-items:center;gap:10px;background:linear-gradient(180deg,#e9f3ff,#f8fbff);padding:14px;border-radius:14px;border:1px solid #b8d4f5;box-shadow:inset 0 0 0 1px #d8e9ff}
.qr-brand{max-width:130px;max-height:34px;object-fit:contain}
.qr-frame{background:#ffffff;border:3px solid #1f78d1;border-radius:14px;padding:10px;box-shadow:0 8px 18px rgba(31,120,209,.18)}
.errors{margin:8px 0 0 18px;padding:0}

.modal{position:fixed;inset:0;background:rgba(4,13,25,.62);display:none;align-items:center;justify-content:center;padding:18px;z-index:50}
.modal.open{display:flex}
.modal-card{width:min(700px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:16px;border:1px solid var(--line);padding:18px;box-shadow:0 22px 40px rgba(8,20,38,.35)}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.modal-head h3{margin:0}
.close{border:none;background:#e8eff5;color:#1f3c56;border-radius:8px;padding:8px 10px;cursor:pointer;font-weight:700}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.full{grid-column:1/-1}
label{display:block;font-size:12px;color:var(--muted);margin-bottom:4px}
input,select,textarea{width:100%;padding:10px;border:1px solid #b9c7d3;border-radius:9px;background:#fff}
textarea{min-height:150px;font-family:Consolas,monospace;font-size:12px}
.tiny{font-size:12px;color:var(--muted)}
.stack{display:flex;gap:8px;flex-wrap:wrap}
.chip{display:inline-flex;align-items:center;background:#eef4ff;border:1px solid #c6dbff;color:#173b62;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.actions-cell{display:flex;gap:6px;flex-wrap:wrap}
.mini-form{display:inline-flex}
.btn.small{padding:7px 10px;font-size:12px;border-radius:8px}

@media (max-width:980px){.result-grid{grid-template-columns:1fr}.qr{width:190px;height:190px}}
</style>
</head>
<body>
<main>
<header class="hero">
  <div>
    <h1>Meter API Admin</h1>
    <p>Gestão de utilizadores, QR e contadores com schema estrito MongoDB.</p>
  </div>
  <div class="toolbar">
    <button type="button" class="btn" data-open-modal="modal-create-user">+ Utilizador</button>
    <button type="button" class="btn dark" data-open-modal="modal-create-meter">+ Contador</button>
    <button type="button" class="btn ghost" data-open-modal="modal-import">Importar lista</button>
  </div>
</header>
' . $modeNotice . '
' . $flashHtml . '
<section class="kpis">' . $cards . '</section>

<section class="tables">
  <section class="panel">
    <h2>Users</h2>
    <div class="table-tools">
      <input type="search" id="users-search" placeholder="Pesquisar user, role ou user id">
      <select id="users-role-filter">
        <option value="">Todas as roles</option>
        <option value="technician">TECHNICIAN</option>
        <option value="manager">MANAGER</option>
        <option value="manufacturer">MANUFACTURER</option>
        <option value="factory">FACTORY</option>
      </select>
    </div>
    <table id="users-table">
      <thead><tr><th>User</th><th>Role</th><th>User ID</th><th>Salt / Token</th><th>Pass (hash)</th><th>Ações</th></tr></thead>
      <tbody>' . $userRows . '<tr id="users-empty-filter" class="table-empty"><td colspan="6">Sem resultados para o filtro aplicado.</td></tr></tbody>
    </table>
  </section>
  <section class="panel">
    <h2>Meters</h2>
    <div class="table-tools">
      <input type="search" id="meters-search" placeholder="Pesquisar DevEUI ou utilizador atribuído">
    </div>
    <table id="meters-table">
      <thead><tr><th>DevEUI</th><th>Auth Keys</th><th>Atribuído a</th><th>Ações</th></tr></thead>
      <tbody>' . $meterRows . '<tr id="meters-empty-filter" class="table-empty"><td colspan="4">Sem resultados para o filtro aplicado.</td></tr></tbody>
    </table>
  </section>
</section>

<div class="modal" id="modal-create-user">
  <div class="modal-card">
    <div class="modal-head"><h3>Criar Utilizador</h3><button class="close" type="button" data-close-modal>&times;</button></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="admin_token" value="' . $tokenSafe . '">
      <label>User<input type="text" name="user" placeholder="ex: 003422" required></label>
      <label>Role<select name="role">' . $roleOptions . '</select></label>
      <label class="full">Password
        <div class="stack">
          <input id="password-field" type="text" name="pass" placeholder="Gerar password forte" required>
          <button type="button" class="btn ghost" id="generate-password-btn">Gerar Password Forte</button>
          <button type="button" class="btn ghost" data-copy-target="password-field">Copiar</button>
        </div>
      </label>
      <div class="full tiny">Gerador estilo LastPass: letras maiúsculas/minúsculas, números e símbolos.</div>
      <label>Comprimento<input id="password-length" type="number" min="12" max="64" value="20"></label>
      <label>Dias de validade<input type="number" name="valid_days" value="365" min="1"></label>
      <div class="full"><button class="btn" type="submit">Criar user + gerar QR</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-create-meter">
  <div class="modal-card">
    <div class="modal-head"><h3>Associar Contador</h3><button class="close" type="button" data-close-modal>&times;</button></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="create_meter">
      <input type="hidden" name="admin_token" value="' . $tokenSafe . '">
      <label>DevEUI<input type="text" name="meterid" placeholder="ex: A1B2C3D4E5F60708" required></label>
      <label>Dias de validade<input type="number" name="valid_days" value="365" min="1"></label>
      <input type="hidden" id="create-meter-users-csv" name="users_csv" value="">
      <label class="full">Selecionar users com acesso
        <div class="user-picker" data-user-picker="create-meter">
          <input type="search" id="create-meter-user-search" placeholder="Pesquisar por user, role, id ou auth_tech" autocomplete="off">
          <div class="picker-results" id="create-meter-user-results"></div>
          <div class="picker-selected" id="create-meter-user-selected"></div>
        </div>
      </label>
      <div class="full tiny">Cada utilizador selecionado gera automaticamente uma authkey determinística para este DevEUI.</div>
      <div class="full"><button class="btn dark" type="submit">Guardar ligação</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-import">
  <div class="modal-card">
    <div class="modal-head"><h3>Importar Lista de Contadores</h3><button class="close" type="button" data-close-modal>&times;</button></div>
    <form method="post" class="form-grid" id="import-meter-form">
      <input type="hidden" name="action" value="bulk_import_meters">
      <input type="hidden" name="admin_token" value="' . $tokenSafe . '">
      <label>Dias de validade<input type="number" name="valid_days" value="365" min="1"></label>
      <input type="hidden" id="import-users-csv" name="users_csv" value="">
      <label class="full">Selecionar users para todos os DevEUI importados
        <div class="user-picker" data-user-picker="import-meter">
          <input type="search" id="import-user-search" placeholder="Pesquisar por user, role, id ou auth_tech" autocomplete="off">
          <div class="picker-results" id="import-user-results"></div>
          <div class="picker-selected" id="import-user-selected"></div>
        </div>
      </label>
      <label class="full">Lista de contadores
        <textarea id="import-meter-list" name="meter_list" placeholder="Um DevEUI por linha&#10;Exemplo:&#10;02F8CCFFFE483203&#10;02F8CCFFFE668451"></textarea>
        <small>Formato aceite: 16 caracteres hexadecimais por linha (sem vírgulas).</small>
      </label>
      <div class="full stack">
        <button class="btn ghost" type="button" id="verify-meter-list-btn">Verificar lista</button>
        <div class="verify-status" id="import-verify-status">Valide a lista e os users antes de importar.</div>
      </div>
      <div class="full"><button class="btn" type="submit" id="import-meter-submit" disabled>Importar lista</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-edit-user">
  <div class="modal-card">
    <div class="modal-head"><h3>Editar Utilizador</h3><button class="close" type="button" data-close-modal>&times;</button></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="update_user">
      <input type="hidden" name="admin_token" value="' . $tokenSafe . '">
      <label>User<input id="edit-user-name" type="text" name="user" readonly></label>
      <label>Novo Role<select id="edit-user-role" name="role">' . $roleOptions . '</select></label>
      <label class="full">Nova password (opcional)<input id="edit-user-pass" type="text" name="pass" placeholder="deixe vazio para manter"></label>
      <div class="full stack">
        <button type="button" class="btn ghost" id="generate-edit-password-btn">Gerar Password Forte</button>
        <button type="button" class="btn ghost" data-copy-target="edit-user-pass">Copiar</button>
      </div>
      <div class="full"><button class="btn" type="submit">Guardar alterações</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-user-qr">
  <div class="modal-card">
    <div class="modal-head"><h3>QR do utilizador</h3><button class="close" type="button" data-close-modal>&times;</button></div>
    <div class="result-grid">
      <div>
        <p><strong>User:</strong> <span id="modal-qr-user">-</span></p>
        <p><strong>Token API:</strong> <code id="modal-qr-token">-</code></p>
        <p><strong>Auth Tech:</strong> <code id="modal-qr-authtech">-</code></p>
        <p><strong>QR payload:</strong> <code id="qr-payload-value">-</code></p>
        <div class="actions-inline">
          <button type="button" class="btn ghost" data-copy-target="qr-payload-value">Copiar payload</button>
          <button type="button" class="btn ghost" id="copy-qr-image-btn">Copiar QR</button>
          <button type="button" class="btn" id="download-branded-qr-btn" data-qr-file="qr-user.png">Baixar QR elegante</button>
        </div>
      </div>
      <div class="qr-card" id="qr-card-wrap">
        <img class="qr-brand" id="qr-brand-logo" src="assets/contaqualg.png" alt="Contaqua">
        <div class="qr-frame">
          <img class="qr" id="new-user-qr" src="" alt="QR user" crossorigin="anonymous" />
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="modal-assign-meter">
  <div class="modal-card">
    <div class="modal-head"><h3>Gerir Atribuições do Contador</h3><button class="close" type="button" data-close-modal>&times;</button></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="assign_meter_users">
      <input type="hidden" name="admin_token" value="' . $tokenSafe . '">
      <label>DevEUI<input id="assign-meter-id" type="text" name="meterid" readonly></label>
      <input type="hidden" id="assign-meter-users-csv" name="users_csv" value="">
      <label class="full">Users atribuídos
        <div class="user-picker" data-user-picker="assign-meter">
          <input type="search" id="assign-meter-user-search" placeholder="Pesquisar por user, role, id ou auth_tech" autocomplete="off">
          <div class="picker-results" id="assign-meter-user-results"></div>
          <div class="picker-selected" id="assign-meter-user-selected"></div>
        </div>
      </label>
      <div class="full tiny">Ao guardar, o sistema calcula automaticamente as auth keys dos utilizadores e atualiza o registo do contador sem duplicar o DevEUI.</div>
      <div class="full"><button class="btn dark" type="submit">Guardar atribuições</button></div>
    </form>
  </div>
</div>

<script>
(function(){
  var userDirectory = ' . $userDirectoryJson . ';

  var modals = document.querySelectorAll(".modal");
  document.querySelectorAll("[data-open-modal]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var modal = document.getElementById(btn.getAttribute("data-open-modal"));
      if (modal) { modal.classList.add("open"); }
    });
  });
  document.querySelectorAll("[data-close-modal]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var modal = btn.closest(".modal");
      if (modal) { modal.classList.remove("open"); }
    });
  });
  modals.forEach(function(modal){
    modal.addEventListener("click", function(ev){
      if (ev.target === modal) { modal.classList.remove("open"); }
    });
  });

  function randomFrom(chars){
    var arr = new Uint32Array(1);
    crypto.getRandomValues(arr);
    return chars[arr[0] % chars.length];
  }
  function generatePassword(len){
    var upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    var lower = "abcdefghijkmnopqrstuvwxyz";
    var num = "23456789";
    var sym = "!@#$%^&*()-_=+[]{};:,./?";
    var all = upper + lower + num + sym;
    var out = [randomFrom(upper), randomFrom(lower), randomFrom(num), randomFrom(sym)];
    while (out.length < len) { out.push(randomFrom(all)); }
    for (var i = out.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var t = out[i]; out[i] = out[j]; out[j] = t;
    }
    return out.join("");
  }
  var genBtn = document.getElementById("generate-password-btn");
  if (genBtn) {
    genBtn.addEventListener("click", function(){
      var lenInput = document.getElementById("password-length");
      var passInput = document.getElementById("password-field");
      var length = 20;
      if (lenInput) {
        var parsed = parseInt(lenInput.value || "20", 10);
        if (!isNaN(parsed)) { length = Math.max(12, Math.min(64, parsed)); }
      }
      if (passInput) { passInput.value = generatePassword(length); }
    });
  }

  var genEditBtn = document.getElementById("generate-edit-password-btn");
  if (genEditBtn) {
    genEditBtn.addEventListener("click", function(){
      var passInput = document.getElementById("edit-user-pass");
      if (passInput) { passInput.value = generatePassword(20); }
    });
  }

  document.querySelectorAll("[data-copy-target]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var id = btn.getAttribute("data-copy-target");
      var target = document.getElementById(id);
      if (!target) { return; }
      var text = target.value || target.textContent || "";
      navigator.clipboard.writeText(text).then(function(){ btn.textContent = "Copiado"; setTimeout(function(){ btn.textContent = "Copiar"; }, 1400); });
    });
  });

  var copyQrBtn = document.getElementById("copy-qr-image-btn");
  if (copyQrBtn) {
    copyQrBtn.addEventListener("click", async function(){
      var img = document.getElementById("new-user-qr");
      if (!img) { return; }
      try {
        var response = await fetch(img.src);
        var blob = await response.blob();
        if (navigator.clipboard && window.ClipboardItem) {
          await navigator.clipboard.write([new ClipboardItem({ [blob.type]: blob })]);
          copyQrBtn.textContent = "QR Copiado";
        } else {
          await navigator.clipboard.writeText(img.src);
          copyQrBtn.textContent = "URL Copiada";
        }
      } catch (e) {
        await navigator.clipboard.writeText(img.src);
        copyQrBtn.textContent = "URL Copiada";
      }
      setTimeout(function(){ copyQrBtn.textContent = "Copiar QR"; }, 1500);
    });
  }

  document.querySelectorAll("[data-dismiss-target]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var targetId = btn.getAttribute("data-dismiss-target") || "";
      var panel = document.getElementById(targetId);
      if (panel) { panel.style.display = "none"; }
    });
  });

  function normalizeFilterText(value){
    return (value || "")
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim();
  }

  function escapeHtml(value){
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/\x27/g, "&#39;");
  }

  function initUserPicker(config){
    var searchInput = document.getElementById(config.searchId);
    var resultsWrap = document.getElementById(config.resultsId);
    var selectedWrap = document.getElementById(config.selectedId);
    var hiddenInput = document.getElementById(config.hiddenId);
    if (!searchInput || !resultsWrap || !selectedWrap || !hiddenInput) { return; }

    var selectedUsers = [];
    function parseSelectedFromHidden(){
      return (hiddenInput.value || "")
        .split(",")
        .map(function(item){ return item.trim(); })
        .filter(function(item, index, arr){ return item !== "" && arr.indexOf(item) === index; });
    }
    selectedUsers = parseSelectedFromHidden();

    function syncHidden(){
      hiddenInput.value = selectedUsers.join(",");
    }

    function renderSelected(){
      if (selectedUsers.length === 0) {
        selectedWrap.innerHTML = "<small class=\"tiny\">Nenhum user selecionado.</small>";
        return;
      }

      selectedWrap.innerHTML = selectedUsers.map(function(user){
        return "<span class=\"picker-chip\">" + escapeHtml(user)
          + " <button type=\"button\" data-remove-user=\"" + escapeHtml(user) + "\">×</button></span>";
      }).join("");
    }

    function renderResults(){
      if (!Array.isArray(userDirectory) || userDirectory.length === 0) {
        resultsWrap.innerHTML = "<small class=\"tiny\">Sem users disponíveis.</small>";
        return;
      }

      var query = normalizeFilterText(searchInput.value || "");
      var matches = userDirectory.filter(function(item){
        if (!item || !item.user) { return false; }
        if (selectedUsers.indexOf(item.user) !== -1) { return false; }
        if (query === "") { return true; }

        var bag = normalizeFilterText([item.user, item.role, item.user_id, item.auth_tech].join(" "));
        return bag.indexOf(query) !== -1;
      }).slice(0, 20);

      if (matches.length === 0) {
        resultsWrap.innerHTML = "<small class=\"tiny\">Sem resultados para esta pesquisa.</small>";
        return;
      }

      resultsWrap.innerHTML = matches.map(function(item){
        var authTech = item.auth_tech ? String(item.auth_tech) : "-";
        return "<button type=\"button\" class=\"picker-option\" data-add-user=\"" + escapeHtml(item.user) + "\">"
          + "<span><strong>" + escapeHtml(item.user) + "</strong><small>Role: " + escapeHtml(item.role || "TECHNICIAN")
          + " · ID: " + escapeHtml(item.user_id) + "</small></span>"
          + "<small>auth_tech: " + escapeHtml(authTech) + "</small>"
          + "</button>";
      }).join("");
    }

    resultsWrap.addEventListener("click", function(event){
      var target = event.target;
      if (!target) { return; }
      var button = target.closest("[data-add-user]");
      if (!button) { return; }

      var user = button.getAttribute("data-add-user") || "";
      if (user === "" || selectedUsers.indexOf(user) !== -1) { return; }
      selectedUsers.push(user);
      syncHidden();
      renderSelected();
      renderResults();
    });

    selectedWrap.addEventListener("click", function(event){
      var target = event.target;
      if (!target) { return; }
      var button = target.closest("[data-remove-user]");
      if (!button) { return; }

      var user = button.getAttribute("data-remove-user") || "";
      selectedUsers = selectedUsers.filter(function(value){ return value !== user; });
      syncHidden();
      renderSelected();
      renderResults();
    });

    searchInput.addEventListener("input", renderResults);

    function refreshFromHidden(){
      selectedUsers = parseSelectedFromHidden();
      renderSelected();
      renderResults();
    }

    refreshFromHidden();

    return {
      refreshFromHidden: refreshFromHidden,
      getSelectedUsers: function(){ return selectedUsers.slice(); }
    };
  }

  function parseDeveuiList(rawText){
    var lines = (rawText || "").split(/\r\n|\r|\n/);
    var valid = [];
    var invalid = [];
    var seen = {};

    lines.forEach(function(line, index){
      var value = (line || "").trim().toUpperCase();
      if (value === "") { return; }
      if (value.indexOf(",") !== -1 || value.indexOf(";") !== -1) {
        invalid.push("Linha " + (index + 1) + ": não use vírgulas/ponto e vírgula.");
        return;
      }
      if (!/^[0-9A-F]{16}$/.test(value)) {
        invalid.push("Linha " + (index + 1) + ": DevEUI inválido (" + value + ").");
        return;
      }
      if (!seen[value]) {
        seen[value] = true;
        valid.push(value);
      }
    });

    return { valid: valid, invalid: invalid };
  }

  function bindTableFilter(config){
    var searchInput = document.getElementById(config.searchId);
    var selectInput = config.selectId ? document.getElementById(config.selectId) : null;
    var table = document.getElementById(config.tableId);
    var emptyRow = document.getElementById(config.emptyRowId);
    if (!searchInput || !table || !emptyRow) { return; }

    var rows = table.querySelectorAll("tbody tr[data-filter-row]");
    if (rows.length === 0) {
      emptyRow.style.display = "none";
      return;
    }

    function apply(){
      var searchValue = normalizeFilterText(searchInput.value || "");
      var selectValue = selectInput ? normalizeFilterText(selectInput.value || "") : "";
      var visibleCount = 0;

      rows.forEach(function(row){
        var matchesSearch = true;
        var matchesSelect = true;

        if (searchValue !== "") {
          var rowText = normalizeFilterText(config.searchFields.map(function(field){
            return row.getAttribute(field) || "";
          }).join(" "));
          matchesSearch = rowText.indexOf(searchValue) !== -1;
        }

        if (selectInput && selectValue !== "") {
          var selectedFieldValue = normalizeFilterText(row.getAttribute(config.selectField || "") || "");
          matchesSelect = selectedFieldValue === selectValue;
        }

        var isVisible = matchesSearch && matchesSelect;
        row.style.display = isVisible ? "" : "none";
        if (isVisible) { visibleCount += 1; }
      });

      emptyRow.style.display = visibleCount === 0 ? "" : "none";
    }

    searchInput.addEventListener("input", apply);
    if (selectInput) {
      selectInput.addEventListener("change", apply);
    }
    apply();
  }

  bindTableFilter({
    searchId: "users-search",
    selectId: "users-role-filter",
    selectField: "data-role",
    tableId: "users-table",
    emptyRowId: "users-empty-filter",
    searchFields: ["data-user", "data-role", "data-userid"]
  });

  bindTableFilter({
    searchId: "meters-search",
    tableId: "meters-table",
    emptyRowId: "meters-empty-filter",
    searchFields: ["data-deveui", "data-users"]
  });

  var createMeterPicker = initUserPicker({
    searchId: "create-meter-user-search",
    resultsId: "create-meter-user-results",
    selectedId: "create-meter-user-selected",
    hiddenId: "create-meter-users-csv"
  });

  var importMeterPicker = initUserPicker({
    searchId: "import-user-search",
    resultsId: "import-user-results",
    selectedId: "import-user-selected",
    hiddenId: "import-users-csv"
  });

  var assignMeterPicker = initUserPicker({
    searchId: "assign-meter-user-search",
    resultsId: "assign-meter-user-results",
    selectedId: "assign-meter-user-selected",
    hiddenId: "assign-meter-users-csv"
  });

  var importForm = document.getElementById("import-meter-form");
  var importListInput = document.getElementById("import-meter-list");
  var importUsersHidden = document.getElementById("import-users-csv");
  var verifyBtn = document.getElementById("verify-meter-list-btn");
  var verifyStatus = document.getElementById("import-verify-status");
  var importSubmit = document.getElementById("import-meter-submit");
  var verifiedSignature = "";

  document.querySelectorAll("[data-open-modal=modal-user-qr]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var user = btn.getAttribute("data-qr-user") || "";
      var token = btn.getAttribute("data-qr-token") || "";
      var authTech = btn.getAttribute("data-qr-authtech") || "";
      var access = parseInt(btn.getAttribute("data-qr-access") || "1", 10);
      if (isNaN(access) || access < 1) { access = 1; }

      var rights = String(Math.max(0, access - 1));
      var payload = "userid=" + encodeURIComponent(user) + "&rights=" + rights + "&auth_tech=" + authTech;
      var qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" + encodeURIComponent(payload);

      var userEl = document.getElementById("modal-qr-user");
      var tokenEl = document.getElementById("modal-qr-token");
      var authTechEl = document.getElementById("modal-qr-authtech");
      var payloadEl = document.getElementById("qr-payload-value");
      var qrImgEl = document.getElementById("new-user-qr");
      var downloadBtn = document.getElementById("download-branded-qr-btn");

      if (userEl) { userEl.textContent = user || "-"; }
      if (tokenEl) { tokenEl.textContent = token || "-"; }
      if (authTechEl) { authTechEl.textContent = authTech || "-"; }
      if (payloadEl) { payloadEl.textContent = payload; }
      if (qrImgEl) { qrImgEl.src = qrUrl; }
      if (downloadBtn) {
        downloadBtn.setAttribute("data-qr-file", "qr-" + (user || "user") + ".png");
      }
    });
  });

  function buildImportSignature(parsed){
    var usersCsv = importUsersHidden ? (importUsersHidden.value || "") : "";
    return parsed.valid.join("\n") + "||" + usersCsv;
  }

  function markImportUnverified(){
    verifiedSignature = "";
    if (importSubmit) { importSubmit.disabled = true; }
    if (verifyStatus) {
      verifyStatus.classList.remove("ok");
      verifyStatus.classList.remove("error");
      verifyStatus.textContent = "Lista alterada. Clique em Verificar lista.";
    }
  }

  if (importListInput) {
    importListInput.addEventListener("input", markImportUnverified);
  }
  if (importUsersHidden) {
    importUsersHidden.addEventListener("change", markImportUnverified);
  }

  if (verifyBtn) {
    verifyBtn.addEventListener("click", function(){
      var parsed = parseDeveuiList(importListInput ? importListInput.value : "");
      var selectedUsers = importMeterPicker && importMeterPicker.getSelectedUsers ? importMeterPicker.getSelectedUsers() : [];

      if (selectedUsers.length === 0) {
        verifiedSignature = "";
        if (importSubmit) { importSubmit.disabled = true; }
        if (verifyStatus) {
          verifyStatus.classList.remove("ok");
          verifyStatus.classList.add("error");
          verifyStatus.textContent = "Selecione pelo menos um user antes de importar.";
        }
        return;
      }

      if (parsed.valid.length === 0) {
        verifiedSignature = "";
        if (importSubmit) { importSubmit.disabled = true; }
        if (verifyStatus) {
          verifyStatus.classList.remove("ok");
          verifyStatus.classList.add("error");
          verifyStatus.textContent = "Nenhum DevEUI válido identificado.";
        }
        return;
      }

      if (parsed.invalid.length > 0) {
        verifiedSignature = "";
        if (importSubmit) { importSubmit.disabled = true; }
        if (verifyStatus) {
          verifyStatus.classList.remove("ok");
          verifyStatus.classList.add("error");
          verifyStatus.textContent = "Identificados " + parsed.valid.length + " DevEUI válidos e " + parsed.invalid.length + " inválidos. Corrija antes de importar.";
        }
        return;
      }

      verifiedSignature = buildImportSignature(parsed);
      if (importSubmit) { importSubmit.disabled = false; }
      if (verifyStatus) {
        verifyStatus.classList.remove("error");
        verifyStatus.classList.add("ok");
        verifyStatus.textContent = "Verificado com sucesso: " + parsed.valid.length + " DevEUI prontos para importação.";
      }
    });
  }

  if (importForm) {
    importForm.addEventListener("submit", function(event){
      var parsed = parseDeveuiList(importListInput ? importListInput.value : "");
      var currentSignature = buildImportSignature(parsed);
      if (verifiedSignature === "" || verifiedSignature !== currentSignature || parsed.invalid.length > 0 || parsed.valid.length === 0) {
        event.preventDefault();
        if (verifyStatus) {
          verifyStatus.classList.remove("ok");
          verifyStatus.classList.add("error");
          verifyStatus.textContent = "Confirme novamente em Verificar lista antes de importar.";
        }
      }
    });
  }

  var downloadBrandedBtn = document.getElementById("download-branded-qr-btn");
  if (downloadBrandedBtn) {
    downloadBrandedBtn.addEventListener("click", async function(){
      var qrImg = document.getElementById("new-user-qr");
      var logoImg = document.getElementById("qr-brand-logo");
      if (!qrImg || !logoImg) { return; }

      var fileName = downloadBrandedBtn.getAttribute("data-qr-file") || "qr-contaqua.png";
      var canvas = document.createElement("canvas");
      canvas.width = 420;
      canvas.height = 560;
      var ctx = canvas.getContext("2d");
      if (!ctx) { return; }

      function loadImage(src){
        return new Promise(function(resolve, reject){
          var image = new Image();
          image.crossOrigin = "anonymous";
          image.onload = function(){ resolve(image); };
          image.onerror = reject;
          image.src = src;
        });
      }

      try {
        var qrSource = qrImg.currentSrc || qrImg.src;
        var logoSource = logoImg.currentSrc || logoImg.src;
        var qrLoaded = await loadImage(qrSource);
        var logoLoaded = await loadImage(logoSource);

        ctx.fillStyle = "#f5faff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = "#e8f2ff";
        ctx.strokeStyle = "#b9d4f8";
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.roundRect(20, 20, canvas.width - 40, canvas.height - 40, 20);
        ctx.fill();
        ctx.stroke();

        var logoW = 170;
        var logoH = 42;
        ctx.drawImage(logoLoaded, (canvas.width - logoW) / 2, 54, logoW, logoH);

        ctx.fillStyle = "#ffffff";
        ctx.strokeStyle = "#1f78d1";
        ctx.lineWidth = 6;
        ctx.beginPath();
        ctx.roundRect(84, 126, 252, 252, 18);
        ctx.fill();
        ctx.stroke();

        ctx.drawImage(qrLoaded, 100, 142, 220, 220);

        var anchor = document.createElement("a");
        anchor.href = canvas.toDataURL("image/png");
        anchor.download = fileName;
        anchor.click();
      } catch (err) {
        var fallback = document.createElement("a");
        fallback.href = qrImg.src;
        fallback.download = fileName;
        fallback.click();
      }
    });
  }

  document.querySelectorAll("[data-open-modal=modal-edit-user]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var user = btn.getAttribute("data-edit-user") || "";
      var role = btn.getAttribute("data-edit-role") || "TECHNICIAN";
      var userInput = document.getElementById("edit-user-name");
      var roleInput = document.getElementById("edit-user-role");
      var passInput = document.getElementById("edit-user-pass");
      if (userInput) { userInput.value = user; }
      if (roleInput) { roleInput.value = role; }
      if (passInput) { passInput.value = ""; }
    });
  });

  document.querySelectorAll("[data-open-modal=modal-assign-meter]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var meterId = btn.getAttribute("data-meterid") || "";
      var usersCsv = btn.getAttribute("data-users-csv") || "";
      var meterInput = document.getElementById("assign-meter-id");
      var usersInput = document.getElementById("assign-meter-users-csv");
      if (meterInput) { meterInput.value = meterId; }
      if (usersInput) {
        usersInput.value = usersCsv;
        usersInput.dispatchEvent(new Event("change"));
      }
      if (assignMeterPicker && assignMeterPicker.refreshFromHidden) {
        assignMeterPicker.refreshFromHidden();
      }
    });
  });
})();
</script>
</main>
</body>
</html>';
    }
}

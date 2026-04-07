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
        $showUserSuccess = (bool) ($state['show_user_success'] ?? false);
        $userData = $state['user_data'] ?? null;
        $showMeterSuccess = (bool) ($state['show_meter_success'] ?? false);
        $meterData = $state['meter_data'] ?? null;

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
                . '<button type="button" class="btn ghost small" data-open-modal="modal-user-credentials" data-credential-user="' . $userSafe . '" data-credential-role="' . $roleSafe . '" data-credential-token="' . $tokenSafeValue . '" data-credential-authtech="' . $authTechSafeValue . '">QR</button>'
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
.info-card{background:#f8fbff;border:1px solid #dbe6f5;border-radius:12px;padding:16px;margin-bottom:16px}
.warning-text{background:#fff3cd;border:1px solid #ffeaa7;border-radius:8px;padding:12px;margin:12px 0;color:#856404;font-size:13px}
.qr-side{text-align:center}
.qr-instructions{font-size:12px;color:var(--muted);margin-top:8px}
.meter-info{max-width:600px}
.actions{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap}
.notification-toast{position:fixed;top:20px;right:20px;max-width:380px;min-width:300px;padding:16px 20px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.15);z-index:10000;transform:translateX(450px);transition:transform 0.3s ease,opacity 0.3s ease;opacity:0}
.notification-toast.show{transform:translateX(0);opacity:1}
.notification-toast.success{background:#10b981;border:1px solid #059669;color:#fff}
.notification-toast.error{background:#ef4444;border:1px solid #dc2626;color:#fff}
.notification-toast-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.notification-toast-title{font-weight:700;font-size:14px}
.notification-toast-message{font-size:13px;line-height:1.4;opacity:0.95}
.notification-toast-close{background:none;border:none;color:#fff;opacity:0.7;cursor:pointer;font-size:18px;line-height:1;padding:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center;border-radius:6px;transition:all 0.2s}
.notification-toast-close:hover{opacity:1;background:rgba(255,255,255,0.15)}

/* Premium Credentials Card - Minimalista Elegante */
.credentials-premium{max-width:900px;width:95%}
.credentials-card-inner{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.12)}

/* Header - Logo + Texto */
.credentials-card-header{background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;position:relative;border-bottom:1px solid rgba(255,255,255,0.1)}
.header-brand{display:flex;align-items:center;gap:12px}
.header-logo{height:36px;width:auto;filter:brightness(0) invert(1);display:block}
.brand-text{color:#fff}
.brand-text h3{margin:0;font-size:18px;font-weight:700;letter-spacing:2px}
.brand-text span{font-size:11px;opacity:0.8;display:block;margin-top:2px}
.header-badge{background:rgba(255,255,255,0.1);color:#fff;padding:6px 14px;border-radius:20px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;border:1px solid rgba(255,255,255,0.2)}
.premium-close{position:absolute;right:20px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.1);border:none;color:#fff;width:32px;height:32px;border-radius:8px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s}
.premium-close:hover{background:rgba(255,255,255,0.2)}

/* Body */
.credentials-card-body{display:grid;grid-template-columns:1.1fr 0.9fr;min-height:420px}

/* User Section */
.card-section{padding:24px}
.user-section{background:#fff;border-right:1px solid #e2e8f0}
.section-header{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.section-header h4{margin:0;font-size:15px;font-weight:600;color:#334155;letter-spacing:0.3px}
.section-icon-svg{width:20px;height:20px;color:#64748b;stroke-width:2}

.credential-field{margin-bottom:14px}
.credential-field label{display:block;font-size:10px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.field-value-row{display:flex;align-items:center;gap:8px}
.field-value{flex:1;font-size:15px;font-weight:500;color:#1e293b}
.password-field{background:#f0fdf4;color:#15803d;padding:10px 14px;border-radius:8px;border:1px solid #86efac;font-family:SF Mono,Monaco,monospace;font-size:13px;letter-spacing:0.5px}
.role-badge{background:#eff6ff;color:#1d4ed8;padding:5px 12px;border-radius:16px;font-size:12px;font-weight:600;border:1px solid #dbeafe}

.copy-btn{width:32px;height:32px;border:1px solid #e2e8f0;background:#fff;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#64748b;transition:all 0.2s}
.copy-btn:hover{background:#f8fafc;color:#0f172a;border-color:#94a3b8}
.copy-btn svg{width:14px;height:14px}

.highlight-field .field-value-row{background:#f0fdf4;padding:3px;border-radius:8px}

/* Security Notice - No TOPO */
.security-notice{background:#fefce8;border:1px solid #fef08a;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:flex-start;margin-bottom:16px}
.notice-icon-svg{width:18px;height:18px;color:#a16207;flex-shrink:0;stroke-width:2}
.security-notice p{margin:0;font-size:11px;color:#713f12;line-height:1.4}
.security-notice strong{display:block;margin-bottom:2px;color:#854d0e;font-size:11px}

/* QR Section */
.qr-section-premium{background:#fff;padding:24px;display:flex;flex-direction:column;align-items:center}
.qr-section-header{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.qr-section-header h4{margin:0;font-size:15px;font-weight:600;color:#334155}

/* Quadrado pequeno embaixo do titulo */
.qr-icon-small{width:16px;height:16px;border:1.5px solid #64748b;border-radius:2px;position:relative}
.qr-icon-small::after{content:"";position:absolute;width:6px;height:6px;border:1.5px solid #64748b;top:3px;left:3px;border-radius:1px}

/* Logo no centro do QR */
.qr-logo-overlay{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;width:44px;height:44px;border-radius:8px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 15px rgba(0,0,0,0.15);padding:4px}
.qr-overlay-logo{width:36px;height:auto;object-fit:contain}

.qr-wrapper{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;margin-top:12px}
.qr-frame-premium{position:relative;padding:16px;background:#fff;border:2px solid #e2e8f0;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.06)}
.qr-frame-premium img{width:180px;height:180px;display:block}

.qr-help-text{text-align:center;font-size:12px;color:#64748b;line-height:1.5;margin-top:16px;max-width:280px}
.qr-brand-footer{text-align:center;margin-top:auto;padding-top:16px}
.qr-brand-footer span{font-size:10px;font-weight:600;color:#0f172a;text-transform:uppercase;letter-spacing:2px;background:#f1f5f9;padding:6px 14px;border-radius:20px}

/* Actions */
.credentials-card-actions{background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 32px;display:flex;gap:10px;justify-content:flex-end}
.action-btn{display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;transition:all 0.2s;border:none}
.action-btn svg{width:16px;height:16px}
.action-btn.secondary{background:#fff;color:#475569;border:1px solid #e2e8f0}
.action-btn.secondary:hover{background:#f8fafc;border-color:#cbd5e1}
.action-btn.primary{background:#0f172a;color:#fff}
.action-btn.primary:hover{background:#1e293b}

@media (max-width:768px){.credentials-card-body{grid-template-columns:1fr}.user-section{border-right:none;border-bottom:1px solid #e2e8f0}}

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
' . $flashHtml . '
<section class="kpis">' . $cards . '</section>

<section class="tables">
  <section class="panel">
    <div class="panel-head">
      <h2>Users</h2>
      <button type="button" class="btn ghost small" onclick="window.location.assign(window.usersLink); return false;">Ver Todos</button>
    </div>
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
    <div class="panel-head">
      <h2>Meters</h2>
      <button type="button" class="btn ghost small" onclick="window.location.assign(window.metersLink); return false;">Ver Todos</button>
    </div>
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
    <form method="post" action="./index.php" class="form-grid">
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
      <div class="full tiny">Gerador de password segura: letras maiúsculas/minúsculas e números.</div>
      <label>Comprimento<input id="password-length" type="number" min="12" max="64" value="15"></label>
      <label>Dias de validade<input type="number" name="valid_days" value="365" min="1"></label>
      <div class="full"><button class="btn" type="submit">Criar user + gerar QR</button></div>
    </form>
  </div>
</div>

<div class="modal" id="modal-create-meter">
  <div class="modal-card">
    <div class="modal-head"><h3>Associar Contador</h3><button class="close" type="button" data-close-modal>&times;</button></div>
    <form method="post" action="./index.php" class="form-grid">
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
    <form method="post" action="/index.php" class="form-grid" id="import-meter-form">
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
    <form method="post" action="./index.php" class="form-grid">
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
    <form method="post" action="./index.php" class="form-grid">
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

<div class="modal" id="modal-user-success">
  <div class="modal-card credentials-premium">
    <div class="credentials-card-inner">
      <!-- Header - Logo + Texto -->
      <div class="credentials-card-header">
        <div class="header-brand">
          <img src="assets/contaqualg.png" alt="Contaqua" class="header-logo">
          <div class="brand-text">
            <h3>CONTAQUA</h3>
            <span>Soluções e Equipamentos para Água</span>
          </div>
        </div>
        <div class="header-badge">CREDENCIAL DE ACESSO</div>
        <button class="close premium-close" type="button" data-close-modal>&times;</button>
      </div>
      
      <!-- Card Body -->
      <div class="credentials-card-body">
        <!-- Left: User Info -->
        <div class="card-section user-section">
          <!-- Aviso Confidencial - No TOPO -->
          <div class="security-notice">
            <svg class="notice-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <p><strong>Documento Confidencial</strong>Guarde estas credenciais em local seguro. Não partilhe com terceiros.</p>
          </div>
          
          <div class="section-header">
            <svg class="section-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <h4>Informações de Acesso</h4>
          </div>
          
          <div class="credential-field">
            <label>Utilizador</label>
            <div class="field-value-row">
              <span class="field-value" id="success-user">-</span>
              <button class="copy-btn" onclick="copyCredential(\'success-user\')" title="Copiar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
              </button>
            </div>
          </div>
          
          <div class="credential-field highlight-field">
            <label>Senha de Acesso</label>
            <div class="field-value-row">
              <code class="field-value password-field" id="success-password">-</code>
              <button class="copy-btn" onclick="copyCredential(\'success-password\')" title="Copiar">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
              </button>
            </div>
          </div>
          
          <div class="credential-field">
            <label>Perfil / Role</label>
            <span class="field-value role-badge" id="success-role">-</span>
          </div>
          
          <div class="credential-field">
            <label>Válido até</label>
            <span class="field-value" id="success-valid-until">-</span>
          </div>
        </div>
        
        <!-- Right: QR Section -->
        <div class="card-section qr-section-premium" id="qr-section-download">
          <div class="qr-section-header">
            <h4>Acesso Rápido</h4>
          </div>
          
          <!-- Quadrado pequeno embaixo do titulo -->
          <div style="text-align:center;margin-bottom:12px">
            <div class="qr-icon-small"></div>
          </div>
          
          <!-- Logo acima do QR -->
          <div style="text-align:center;margin-bottom:12px">
            <img src="assets/contaqualg.png" alt="Contaqua" style="height:24px;opacity:0.9">
          </div>
          
          <div class="qr-wrapper">
            <div class="qr-frame-premium">
              <img id="success-qr-image" src="" alt="QR Code" crossorigin="anonymous" />
              <!-- Logo no centro do QR -->
              <div class="qr-logo-overlay">
                <img src="assets/contaqualg.png" alt="Contaqua" class="qr-overlay-logo">
              </div>
            </div>
          </div>
          
          <p class="qr-help-text">Aponte a câmara do seu dispositivo para o código QR para aceder automaticamente à aplicação MeterApp.</p>
          
          <div class="qr-brand-footer">
            <span>MeterApp Ready</span>
          </div>
        </div>
      </div>
      
      <!-- Actions Footer -->
      <div class="credentials-card-actions">
        <button type="button" class="action-btn secondary" onclick="copyAllCredentials()">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
          Copiar Dados
        </button>
        <button type="button" class="action-btn secondary" id="save-qr-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
          Salvar QR
        </button>
        <button type="button" class="action-btn primary" id="save-complete-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="M21 15l-5-5L5 21"></path></svg>
          Salvar Cartão Completo
        </button>
      </div>
    </div>
  </div>
</div>
    </div>
  </div>
</div>

<div class="modal" id="modal-meter-success">
  <div class="modal-card">
    <div class="modal-head"><h3>Contador Criado com Sucesso</h3><button class="close" type="button" data-close-modal>&times;</button></div>
    <div class="meter-info">
      <h4>Informações do Contador</h4>
      <div class="info-card">
        <p><strong>DevEUI:</strong> <code id="success-deveui">-</code></p>
        <p><strong>Utilizadores Atribuídos:</strong> <span id="success-users">-</span></p>
        <p><strong>Auth Keys:</strong> <div id="success-authkeys">-</div></p>
        <p><strong>Válido até:</strong> <span id="success-meter-valid-until">-</span></p>
        <div class="actions">
          <button type="button" class="btn ghost" id="copy-meter-info">Copiar Informações</button>
          <button type="button" class="btn" id="download-meter-info">Baixar Detalhes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="notification-toast" id="notification-toast">
  <div class="notification-toast-header">
    <span class="notification-toast-title" id="notification-toast-title">Sucesso</span>
    <button class="notification-toast-close" onclick="hideNotification()">&times;</button>
  </div>
  <div class="notification-toast-message" id="notification-toast-message">Operação concluída com sucesso</div>
</div>

<script>
// Define global variables before any other code
window.adminToken = "' . $tokenSafe . '";
window.usersLink = "./index.php?page=users&admin_token=" + window.adminToken;
window.metersLink = "./index.php?page=meters&admin_token=" + window.adminToken;

// Debug: Log the links to console
console.log("Users link:", window.usersLink);
console.log("Meters link:", window.metersLink);

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
    var all = upper + lower + num;
    var out = [randomFrom(upper), randomFrom(lower), randomFrom(num)];
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
      var length = 15;
      if (lenInput) {
        var parsed = parseInt(lenInput.value || "15", 10);
        if (!isNaN(parsed)) { length = Math.max(12, Math.min(64, parsed)); }
      }
      if (passInput) { passInput.value = generatePassword(length); }
    });
  }

  var genEditBtn = document.getElementById("generate-edit-password-btn");
  if (genEditBtn) {
    genEditBtn.addEventListener("click", function(){
      var passInput = document.getElementById("edit-user-pass");
      if (passInput) { passInput.value = generatePassword(15); }
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

  // Handler for credentials QR button in table
  document.querySelectorAll("[data-open-modal=modal-user-credentials]").forEach(function(btn){
    btn.addEventListener("click", function(){
      var user = btn.getAttribute("data-credential-user") || "";
      var role = btn.getAttribute("data-credential-role") || "";
      var token = btn.getAttribute("data-credential-token") || "";
      var authTech = btn.getAttribute("data-credential-authtech") || "";
      
      // Build user data object for the modal
      var userData = {
        user: user,
        role: role,
        token: token,
        auth_tech: authTech,
        valid_until: "365 dias"
      };
      
      // Generate QR payload
      var rights = "0";
      var payload = "userid=" + encodeURIComponent(user) + "&rights=" + rights + "&auth_tech=" + authTech;
      userData.qr_payload = payload;
      
      // Show the credentials modal
      showUserSuccessModal(userData);
    });
  });

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

  // Handle form submissions with AJAX
  document.querySelectorAll("form").forEach(function(form){
    form.addEventListener("submit", function(event){
      // Skip if it is a GET form (search/filter forms)
      if (form.method.toLowerCase() === "get") {
        return;
      }
      
      event.preventDefault();
      
      var formData = new FormData(form);
      var submitBtn = form.querySelector("button[type=submit]");
      var originalText = submitBtn ? submitBtn.textContent : "";
      var modal = form.closest(".modal");
      
      // Show loading state
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "A processar...";
      }
      
      // Get the correct action URL - avoid conflicts with input[name="action"]
      var actionUrl = form.getAttribute("action");
      console.log("Form action attribute:", actionUrl);
      if (!actionUrl || actionUrl.indexOf("[object") !== -1) {
        actionUrl = "./index.php";
      }
      console.log("Final action URL:", actionUrl);
      
      fetch(actionUrl, {
        method: "POST",
        body: formData
      })
      .then(function(response) {
        return response.json();
      })
      .then(function(data) {
        // Reset button
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
        }
        
        // Close the form modal if successful
        if (data.success && modal) {
          modal.classList.remove("open");
        }
        
        // Show notification modal
        showNotification(data.message || "Operação concluída", data.type || "success");
        
        // If success and it is a create operation, show success modal with data from response
        if (data.success && formData.get("action") === "create_user" && data.user_data) {
          showUserSuccessModal(data.user_data);
        } else if (data.success && formData.get("action") === "create_meter" && data.meter_data) {
          showMeterSuccessModal(data.meter_data);
        }
        
        // Only refresh if not showing success modal (user needs to see credentials)
        if (data.success && !data.user_data && !data.meter_data) {
          setTimeout(function(){
            window.location.reload();
          }, 1500);
        }
      })
      .catch(function(error) {
        // Reset button
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
        }
        
        showNotification("Ocorreu um erro ao processar a operação", "error");
      });
    });
  });

  // Function to update dashboard data dynamically
  function updateDashboardData() {
    fetch("/index.php?admin_token=" + window.adminToken)
      .then(function(response) {
        return response.text();
      })
      .then(function(html) {
        // Parse the new HTML and update the dashboard
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, "text/html");
        
        // Update KPI cards
        var kpisSection = document.querySelector(".kpis");
        var newKpis = doc.querySelector(".kpis");
        if (kpisSection && newKpis) {
          kpisSection.innerHTML = newKpis.innerHTML;
        }
        
        // Update tables
        var tablesSection = document.querySelector(".tables");
        var newTables = doc.querySelector(".tables");
        if (tablesSection && newTables) {
          tablesSection.innerHTML = newTables.innerHTML;
        }
        
        // Reinitialize event listeners for new elements
        initializeEventListeners();
      })
      .catch(function(error) {
        console.error("Error updating dashboard:", error);
      });
  }

  // Function to initialize event listeners
  function initializeEventListeners() {
    // Re-attach modal open/close listeners
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
    
    // Re-attach copy listeners
    document.querySelectorAll("[data-copy-target]").forEach(function(btn){
      btn.addEventListener("click", function(){
        var id = btn.getAttribute("data-copy-target");
        var target = document.getElementById(id);
        if (!target) { return; }
        var text = target.value || target.textContent || "";
        navigator.clipboard.writeText(text).then(function(){ btn.textContent = "Copiado"; setTimeout(function(){ btn.textContent = "Copiar"; }, 1400); });
      });
    });
  }

  var notificationTimeout;
  
  function showNotification(message, type) {
    var toast = document.getElementById("notification-toast");
    var titleEl = document.getElementById("notification-toast-title");
    var messageEl = document.getElementById("notification-toast-message");
    
    if (!toast || !titleEl || !messageEl) return;
    
    // Clear previous timeout
    if (notificationTimeout) {
      clearTimeout(notificationTimeout);
    }
    
    titleEl.textContent = type === "success" ? "Sucesso" : "Erro";
    messageEl.textContent = message;
    toast.className = "notification-toast " + type + " show";
    
    // Auto hide after 4 seconds
    notificationTimeout = setTimeout(function() {
      hideNotification();
    }, 4000);
  }
  
  window.hideNotification = function() {
    var toast = document.getElementById("notification-toast");
    if (toast) {
      toast.classList.remove("show");
    }
  }

  // Show success modals if data is available
  var showUserSuccess = ' . ($showUserSuccess ? 'true' : 'false') . ';
  var userData = ' . ($userData ? json_encode($userData) : 'null') . ';
  var showMeterSuccess = ' . ($showMeterSuccess ? 'true' : 'false') . ';
  var meterData = ' . ($meterData ? json_encode($meterData) : 'null') . ';

  if (showUserSuccess && userData) {
    showUserSuccessModal(userData);
  }

  if (showMeterSuccess && meterData) {
    showMeterSuccessModal(meterData);
  }

  // Credential copy functions
  window.copyCredential = function(elementId) {
    var element = document.getElementById(elementId);
    if (!element) return;
    var text = element.textContent || "-";
    navigator.clipboard.writeText(text).then(function() {
      showNotification("Copiado para a área de transferência!", "success");
    });
  };
  
  window.copyAllCredentials = function() {
    var user = document.getElementById("success-user")?.textContent || "-";
    var password = document.getElementById("success-password")?.textContent || "-";
    var text = "Utilizador: " + user + "\nSenha: " + password;
    navigator.clipboard.writeText(text).then(function() {
      showNotification("Dados de acesso copiados!", "success");
    });
  };

  function showUserSuccessModal(data) {
    var modal = document.getElementById("modal-user-success");
    if (!modal) return;

    // Populate user data
    var userEl = document.getElementById("success-user");
    var passEl = document.getElementById("success-password");
    var roleEl = document.getElementById("success-role");
    var validEl = document.getElementById("success-valid-until");
    var qrEl = document.getElementById("success-qr-image");

    if (userEl) userEl.textContent = data.user || "-";
    if (passEl) passEl.textContent = data.password || "-";
    if (roleEl) roleEl.textContent = data.role ? data.role.toUpperCase() : "-";
    if (validEl) validEl.textContent = data.valid_until || "-";

    // Generate QR code
    if (qrEl && data.qr_payload) {
      var qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" + encodeURIComponent(data.qr_payload);
      qrEl.src = qrUrl;
    }

    modal.classList.add("open");
  }

  function showMeterSuccessModal(data) {
    var modal = document.getElementById("modal-meter-success");
    if (!modal) return;

    // Populate meter data
    var deveuiEl = document.getElementById("success-deveui");
    var usersEl = document.getElementById("success-users");
    var authkeysEl = document.getElementById("success-authkeys");
    var validEl = document.getElementById("success-meter-valid-until");

    if (deveuiEl) deveuiEl.textContent = data.deveui || "-";
    if (usersEl) usersEl.textContent = (data.assigned_users && data.assigned_users.length > 0) ? data.assigned_users.join(", ") : "-";
    if (authkeysEl) {
      var authkeysHtml = "";
      if (data.authkeys && data.authkeys.length > 0) {
        data.authkeys.forEach(function(key) {
          authkeysHtml += "<code style=\"display:block;margin:4px 0;\">" + key + "</code>";
        });
      } else {
        authkeysHtml = "-";
      }
      authkeysEl.innerHTML = authkeysHtml;
    }
    if (validEl) validEl.textContent = data.valid_until || "-";

    modal.classList.add("open");
  }

  // Download credentials functionality - NEW IDs
  var downloadUserBtn = document.getElementById("download-user-credentials");
  if (downloadUserBtn) {
    downloadUserBtn.addEventListener("click", function(){
      downloadUserCredentials();
    });
  }

  // Save QR only - NEW ID
  var saveQrBtn = document.getElementById("save-qr-btn");
  if (saveQrBtn) {
    saveQrBtn.addEventListener("click", function(){
      saveQrOnly();
    });
  }

  // Save complete credentials - NEW ID
  var saveCompleteBtn = document.getElementById("save-complete-btn");
  if (saveCompleteBtn) {
    saveCompleteBtn.addEventListener("click", function(){
      downloadCompleteCredentials();
    });
  }

  async function saveQrOnly() {
    var qrImg = document.getElementById("success-qr-image");
    if (!qrImg || !qrImg.src) {
      showNotification("QR code não disponível", "error");
      return;
    }
    
    // Create canvas - igual ao layout original
    var canvas = document.createElement("canvas");
    canvas.width = 900;
    canvas.height = 520;
    var ctx = canvas.getContext("2d");
    
    function loadImage(src) {
      return new Promise(function(resolve, reject) {
        var img = new Image();
        img.crossOrigin = "anonymous";
        img.onload = function() { resolve(img); };
        img.onerror = function() { reject(new Error("Failed to load image")); };
        img.src = src;
      });
    }
    
    try {
      // Load images
      var qrImage = await loadImage(qrImg.src);
      var logoImg = null;
      try {
        logoImg = await loadImage("assets/contaqualg.png");
      } catch(e) {
        console.log("Logo load failed");
      }
      
      // ========== HEADER ==========
      ctx.fillStyle = "#0f172a";
      ctx.fillRect(0, 0, canvas.width, 70);
      
      // Logo + Texto no header
      if (logoImg) {
        ctx.save();
        ctx.filter = "brightness(0) invert(1)";
        ctx.drawImage(logoImg, 30, 18, 40, 35);
        ctx.restore();
      }
      
      // Texto CONTAQUA
      ctx.fillStyle = "#fff";
      ctx.font = "bold 18px Arial";
      ctx.textAlign = "left";
      ctx.fillText("CONTAQUA", 80, 40);
      ctx.font = "11px Arial";
      ctx.fillStyle = "rgba(255,255,255,0.8)";
      ctx.fillText("Soluções e Equipamentos para Água", 80, 55);
      
      // Badge
      ctx.fillStyle = "rgba(255,255,255,0.1)";
      ctx.strokeStyle = "rgba(255,255,255,0.2)";
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.roundRect(700, 20, 160, 30, 15);
      ctx.fill();
      ctx.stroke();
      ctx.fillStyle = "#fff";
      ctx.font = "bold 10px Arial";
      ctx.textAlign = "center";
      ctx.fillText("CREDENCIAL DE ACESSO", 780, 38);
      
      // ========== BODY BACKGROUND ==========
      ctx.fillStyle = "#f8fafc";
      ctx.fillRect(0, 70, canvas.width, canvas.height - 70);
      
      // ========== LEFT SECTION ==========
      // Fundo branco do card
      ctx.fillStyle = "#fff";
      ctx.beginPath();
      ctx.roundRect(30, 90, 440, 380, 12);
      ctx.fill();
      ctx.strokeStyle = "#e2e8f0";
      ctx.lineWidth = 1;
      ctx.stroke();
      
      // Aviso amarelo - NO TOPO
      ctx.fillStyle = "#fefce8";
      ctx.strokeStyle = "#fef08a";
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.roundRect(45, 105, 410, 60, 8);
      ctx.fill();
      ctx.stroke();
      ctx.fillStyle = "#854d0e";
      ctx.font = "bold 12px Arial";
      ctx.textAlign = "left";
      ctx.fillText("Documento Confidencial", 65, 130);
      ctx.font = "11px Arial";
      ctx.fillStyle = "#713f12";
      ctx.fillText("Guarde estas credenciais em local seguro. Não partilhe com terceiros.", 65, 150);
      
      // Título "Informações de Acesso"
      ctx.fillStyle = "#334155";
      ctx.font = "bold 15px Arial";
      ctx.fillText("Informações de Acesso", 50, 195);
      
      // Ícone de utilizador (círculo simples)
      ctx.strokeStyle = "#64748b";
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.arc(50, 190, 8, 0, Math.PI * 2);
      ctx.stroke();
      
      // Linha divisória
      ctx.strokeStyle = "#1264a3";
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(50, 210);
      ctx.lineTo(180, 210);
      ctx.stroke();
      
      // ========== RIGHT SECTION (QR) ==========
      // Fundo branco
      ctx.fillStyle = "#fff";
      ctx.beginPath();
      ctx.roundRect(500, 90, 370, 380, 12);
      ctx.fill();
      ctx.strokeStyle = "#e2e8f0";
      ctx.lineWidth = 1;
      ctx.stroke();
      
      // Título "Acesso Rápido"
      ctx.fillStyle = "#334155";
      ctx.font = "bold 15px Arial";
      ctx.textAlign = "center";
      ctx.fillText("Acesso Rápido", 685, 125);
      
      // Quadrado pequeno embaixo do título
      ctx.strokeStyle = "#64748b";
      ctx.lineWidth = 1.5;
      ctx.strokeRect(678, 135, 16, 16);
      ctx.strokeRect(682, 139, 8, 8);
      
      // Logo acima do QR
      if (logoImg) {
        ctx.drawImage(logoImg, 655, 160, 60, 30);
      }
      
      // Frame do QR
      ctx.strokeStyle = "#e2e8f0";
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.roundRect(560, 200, 250, 250, 10);
      ctx.stroke();
      ctx.fillStyle = "#fff";
      ctx.fill();
      
      // QR code
      ctx.drawImage(qrImage, 575, 215, 220, 220);
      
      // Logo no centro do QR
      ctx.fillStyle = "#fff";
      ctx.beginPath();
      ctx.roundRect(660, 300, 50, 50, 8);
      ctx.fill();
      ctx.strokeStyle = "#e2e8f0";
      ctx.lineWidth = 1;
      ctx.stroke();
      if (logoImg) {
        ctx.drawImage(logoImg, 665, 305, 40, 40);
      }
      
      // Texto de ajuda
      ctx.fillStyle = "#64748b";
      ctx.font = "12px Arial";
      ctx.textAlign = "center";
      ctx.fillText("Aponte a câmara do seu dispositivo para o código QR", 685, 475);
      ctx.fillText("para aceder automaticamente à aplicação MeterApp.", 685, 492);
      
      // Badge MeterApp Ready
      ctx.fillStyle = "#f1f5f9";
      ctx.beginPath();
      ctx.roundRect(630, 430, 110, 24, 12);
      ctx.fill();
      ctx.fillStyle = "#0f172a";
      ctx.font = "bold 10px Arial";
      ctx.fillText("MeterApp Ready", 685, 446);
      
      // Download
      var link = document.createElement("a");
      link.download = "acesso-rapido.png";
      link.href = canvas.toDataURL("image/png");
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      showNotification("QR section salva!", "success");
    } catch (err) {
      console.error("Error:", err);
      showNotification("Erro ao gerar imagem. Tente novamente.", "error");
    }
  }

  async function downloadCompleteCredentials() {
    var user = document.getElementById("success-user")?.textContent || "-";
    var password = document.getElementById("success-password")?.textContent || "-";
    var role = document.getElementById("success-role")?.textContent || "-";
    var valid = document.getElementById("success-valid-until")?.textContent || "-";
    var qrImg = document.getElementById("success-qr-image");
    
    // Create canvas for the complete credential card
    var canvas = document.createElement("canvas");
    canvas.width = 900;
    canvas.height = 520;
    var ctx = canvas.getContext("2d");
    
    // Helper to load images
    function loadImage(src) {
      return new Promise(function(resolve, reject) {
        var img = new Image();
        img.crossOrigin = "anonymous";
        img.onload = function() { resolve(img); };
        img.onerror = function() { reject(new Error("Failed to load image")); };
        img.src = src;
      });
    }
    
    try {
      // Load QR image
      var qrImage = null;
      if (qrImg && qrImg.src) {
        try {
          qrImage = await loadImage(qrImg.src);
        } catch(e) {
          console.log("QR image load failed, will draw placeholder");
        }
      }
      
      // Background gradient header
      var gradient = ctx.createLinearGradient(0, 0, canvas.width, 0);
      gradient.addColorStop(0, "#1264a3");
      gradient.addColorStop(1, "#0d5c35");
      
      // Header - minimalista escuro
      ctx.fillStyle = "#0f172a";
      ctx.fillRect(0, 0, canvas.width, 85);
      
      // Try to load and draw logo
      var logoImage = null;
      try {
        logoImage = await loadImage("assets/contaqualg.png");
      } catch(e) {
        console.log("Logo load failed");
      }
      
      if (logoImage) {
        // Draw logo white/inverted
        ctx.save();
        ctx.filter = "brightness(0) invert(1)";
        ctx.drawImage(logoImage, 30, 22, 50, 40);
        ctx.restore();
      }
      
      // Header text
      ctx.fillStyle = "#fff";
      ctx.textAlign = "left";
      ctx.font = "bold 20px Arial";
      ctx.fillText("CONTAQUA", 90, 40);
      ctx.font = "11px Arial";
      ctx.fillStyle = "rgba(255,255,255,0.7)";
      ctx.fillText("Soluções e Equipamentos para Água", 90, 58);
      
      // Badge
      ctx.fillStyle = "rgba(255,255,255,0.1)";
      ctx.strokeStyle = "rgba(255,255,255,0.2)";
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.roundRect(720, 28, 150, 28, 14);
      ctx.fill();
      ctx.stroke();
      ctx.fillStyle = "#fff";
      ctx.font = "bold 10px Arial";
      ctx.textAlign = "center";
      ctx.fillText("CREDENCIAL DE ACESSO", 795, 46);
      
      // Body background
      ctx.fillStyle = "#f8fafc";
      ctx.fillRect(0, 85, canvas.width, canvas.height - 85);
      
      // Left section background
      ctx.fillStyle = "#f1f5f9";
      ctx.fillRect(0, 85, 520, canvas.height - 85);
      
      // Security notice
      ctx.fillStyle = "#fefce8";
      ctx.strokeStyle = "#fef08a";
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.roundRect(30, 105, 460, 65, 8);
      ctx.fill();
      ctx.stroke();
      ctx.fillStyle = "#854d0e";
      ctx.textAlign = "left";
      ctx.font = "bold 12px Arial";
      ctx.fillText("Documento Confidencial", 50, 130);
      ctx.font = "11px Arial";
      ctx.fillStyle = "#713f12";
      ctx.fillText("Guarde estas credenciais em local seguro. Não partilhe com terceiros.", 50, 150);
      
      // User details box
      ctx.fillStyle = "#fff";
      ctx.beginPath();
      ctx.roundRect(30, 185, 460, 235, 12);
      ctx.fill();
      ctx.strokeStyle = "#e2e8f0";
      ctx.lineWidth = 1;
      ctx.stroke();
      
      // Section title - with user icon
      ctx.fillStyle = "#334155";
      ctx.font = "bold 14px Arial";
      ctx.fillText("Informações de Acesso", 50, 215);
      
      // User field
      ctx.fillStyle = "#94a3b8";
      ctx.font = "10px Arial";
      ctx.fillText("UTILIZADOR", 50, 250);
      ctx.fillStyle = "#1e293b";
      ctx.font = "bold 15px Arial";
      ctx.fillText(user, 50, 272);
      
      // Password field (highlighted)
      ctx.fillStyle = "#94a3b8";
      ctx.font = "10px Arial";
      ctx.fillText("SENHA", 50, 310);
      ctx.fillStyle = "#15803d";
      ctx.beginPath();
      ctx.roundRect(48, 318, 200, 32, 6);
      ctx.fill();
      ctx.fillStyle = "#fff";
      ctx.font = "bold 13px monospace";
      ctx.fillText(password, 58, 339);
      
      // Role field
      ctx.fillStyle = "#94a3b8";
      ctx.font = "10px Arial";
      ctx.fillText("PERFIL", 50, 375);
      ctx.fillStyle = "#1d4ed8";
      ctx.beginPath();
      ctx.roundRect(48, 383, 110, 26, 13);
      ctx.fill();
      ctx.fillStyle = "#fff";
      ctx.font = "bold 11px Arial";
      ctx.fillText(role, 58, 400);
      
      // Valid until
      ctx.fillStyle = "#94a3b8";
      ctx.font = "10px Arial";
      ctx.fillText("VÁLIDO ATÉ", 280, 375);
      ctx.fillStyle = "#1e293b";
      ctx.font = "bold 13px Arial";
      ctx.fillText(valid, 280, 395);
      
      // Right section - QR
      ctx.fillStyle = "#fff";
      ctx.beginPath();
      ctx.roundRect(560, 105, 310, 340, 12);
      ctx.fill();
      ctx.strokeStyle = "#e2e8f0";
      ctx.lineWidth = 1;
      ctx.stroke();
      
      // QR section title
      ctx.fillStyle = "#334155";
      ctx.font = "bold 14px Arial";
      ctx.textAlign = "center";
      ctx.fillText("Acesso Rápido", 715, 135);
      
      // QR icon
      ctx.strokeStyle = "#64748b";
      ctx.lineWidth = 1;
      ctx.strokeRect(700, 145, 12, 12);
      ctx.strokeRect(703, 148, 6, 6);
      
      // Logo acima do QR
      if (logoImage) {
        ctx.drawImage(logoImage, 680, 165, 70, 35);
      }
      
      // QR frame
      ctx.strokeStyle = "#e2e8f0";
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.roundRect(610, 210, 210, 210, 10);
      ctx.stroke();
      ctx.fillStyle = "#fff";
      ctx.fill();
      
      // Draw QR image if loaded - agora sem logo no meio
      if (qrImage) {
        ctx.drawImage(qrImage, 625, 225, 180, 180);
      } else {
        ctx.fillStyle = "#f1f5f9";
        ctx.fillRect(625, 225, 180, 180);
      }
      
      // Help text
      ctx.fillStyle = "#64748b";
      ctx.font = "12px Arial";
      ctx.textAlign = "center";
      ctx.fillText("Aponte a câmara do seu dispositivo", 715, 445);
      ctx.fillText("para aceder à aplicação MeterApp", 715, 462);
      
      // Footer badge
      ctx.fillStyle = "#f1f5f9";
      ctx.beginPath();
      ctx.roundRect(660, 475, 110, 24, 12);
      ctx.fill();
      ctx.fillStyle = "#0f172a";
      ctx.font = "bold 10px Arial";
      ctx.fillText("MeterApp Ready", 715, 491);
      
      // Footer copyright
      ctx.fillStyle = "#94a3b8";
      ctx.font = "10px Arial";
      ctx.fillText("Contaqua - Sistemas de Gestão de Água", 450, 545);
      
      // Download
      var link = document.createElement("a");
      link.download = "credencial-" + user + ".png";
      link.href = canvas.toDataURL("image/png");
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      showNotification("Cartão de credencial salvo!", "success");
    } catch (err) {
      console.error("Error generating credentials card:", err);
      showNotification("Erro ao gerar cartão. Tente novamente.", "error");
    }
  }

  var downloadMeterBtn = document.getElementById("download-meter-info");
  if (downloadMeterBtn) {
    downloadMeterBtn.addEventListener("click", function(){
      downloadMeterInfo();
    });
  }

  function downloadUserCredentials() {
    if (!userData) return;
    
    var canvas = document.createElement("canvas");
    canvas.width = 800;
    canvas.height = 600;
    var ctx = canvas.getContext("2d");
    
    // Background
    ctx.fillStyle = "#f5faff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    // Header
    ctx.fillStyle = "#1f78d1";
    ctx.fillRect(0, 0, canvas.width, 80);
    
    ctx.fillStyle = "#ffffff";
    ctx.font = "bold 24px Arial";
    ctx.fillText("Credenciais de Acesso - Contaqua App", 40, 50);
    
    // Warning text
    ctx.fillStyle = "#856404";
    ctx.font = "14px Arial";
    var warningText = "⚠️ INFORMAÇÃO CONFIDENCIAL - Estas credenciais são de acesso exclusivo à aplicação Contaqua.";
    ctx.fillText(warningText, 40, 120);
    var warningText2 = "Não partilhe com terceiros e guarde em local seguro.";
    ctx.fillText(warningText2, 40, 140);
    
    // User info
    ctx.fillStyle = "#142032";
    ctx.font = "bold 18px Arial";
    ctx.fillText("Dados da Conta:", 40, 200);
    
    ctx.font = "16px Arial";
    ctx.fillText("Utilizador: " + (userData.user || "-"), 40, 240);
    ctx.fillText("Senha: " + (userData.password || "-"), 40, 270);
    ctx.fillText("Role: " + (userData.role ? userData.role.toUpperCase() : "-"), 40, 300);
    ctx.fillText("Válido até: " + (userData.valid_until || "-"), 40, 330);
    
    // QR Code
    if (userData.qr_payload) {
      ctx.font = "bold 18px Arial";
      ctx.fillText("QR Code para App:", 450, 200);
      
      var qrImg = new Image();
      qrImg.onload = function() {
        ctx.drawImage(qrImg, 450, 220, 150, 150);
        
        // Download after QR is loaded
        var link = document.createElement("a");
        link.download = "contaqua-credentials-" + (userData.user || "user") + ".png";
        link.href = canvas.toDataURL();
        link.click();
      };
      qrImg.src = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" + encodeURIComponent(userData.qr_payload);
    }
  }

  function downloadMeterInfo() {
    if (!meterData) return;
    
    var text = "INFORMAÇÕES DO CONTADOR - CONTAQUA\\n\\n";
    text += "DevEUI: " + (meterData.deveui || "-") + "\\n";
    text += "Utilizadores Atribuídos: " + ((meterData.assigned_users && meterData.assigned_users.length > 0) ? meterData.assigned_users.join(", ") : "-") + "\\n";
    text += "Auth Keys:\\n";
    if (meterData.authkeys && meterData.authkeys.length > 0) {
      meterData.authkeys.forEach(function(key, index) {
        text += "  " + (index + 1) + ". " + key + "\\n";
      });
    } else {
      text += "  -\\n";
    }
    text += "Válido até: " + (meterData.valid_until || "-") + "\\n";
    text += "\\nGerado em: " + new Date().toLocaleString("pt-PT");
    
    var blob = new Blob([text], { type: "text/plain" });
    var link = document.createElement("a");
    link.download = "contaqua-meter-" + (meterData.deveui || "unknown") + ".txt";
    link.href = URL.createObjectURL(blob);
    link.click();
  }
})();
</script>
</main>
</body>
</html>';
    }

    /** @param array<int, array<string, mixed>> $users */
    /** @param array<string, mixed> $state */
    public static function usersList(array $users, array $state = []): string
    {
        $search = (string) ($state['search'] ?? '');
        $role = (string) ($state['role'] ?? '');
        $page = (int) ($state['page'] ?? 1);
        $totalCount = (int) ($state['total_count'] ?? 0);
        $perPage = (int) ($state['per_page'] ?? 50);
        $adminToken = (string) ($state['admin_token'] ?? '');
        $roles = $state['roles'] ?? ['TECHNICIAN', 'MANAGER', 'MANUFACTURER', 'FACTORY'];

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

        $compactValue = static function (string $value): string {
            if ($value === '') {
                return '-';
            }
            if (strlen($value) <= 16) {
                return $value;
            }
            return substr($value, 0, 6) . '...' . substr($value, -4);
        };

        $userRows = '';
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

            $userRows .= '<tr>'
                . '<td>' . $userSafe . '</td>'
                . '<td><span class="chip">' . $roleSafe . '</span> <small>#' . $access . '</small></td>'
                . '<td>' . (int) ($user['user_id'] ?? 0) . '</td>'
                . '<td><small>Salt:</small> <code title="' . $saltSafeValue . '">' . htmlspecialchars($compactValue($salt), ENT_QUOTES, 'UTF-8') . '</code><br><small>Token:</small> <code title="' . $tokenSafeValue . '">' . htmlspecialchars($compactValue($token), ENT_QUOTES, 'UTF-8') . '</code></td>'
                . '<td><code>********</code></td>'
                . '<td class="actions-cell">'
                . '<button type="button" class="btn ghost small" data-open-modal="modal-user-qr" data-qr-user="' . $userSafe . '" data-qr-token="' . $tokenSafeValue . '">QR</button>'
                . '<button type="button" class="btn ghost small" data-open-modal="modal-edit-user" data-edit-user="' . $userSafe . '" data-edit-role="' . $roleSafe . '">Editar</button>'
                . '</td>'
                . '</tr>';
        }
        if ($userRows === '') {
            $userRows = '<tr><td colspan="6">Sem utilizadores encontrados.</td></tr>';
        }

        $totalPages = ceil($totalCount / $perPage);
        $pagination = '';
        if ($totalPages > 1) {
            $pagination .= '<div class="pagination">';
            if ($page > 1) {
                $prevPage = $page - 1;
                $pagination .= '<a href="./index.php?page=users&admin_token=' . rawurlencode($adminToken) . '&search=' . rawurlencode($search) . '&role=' . rawurlencode($role) . '&page_num=' . $prevPage . '" class="btn ghost small">« Anterior</a>';
            }
            
            $pagination .= '<span class="page-info">Página ' . $page . ' de ' . $totalPages . ' (' . $totalCount . ' registos)</span>';
            
            if ($page < $totalPages) {
                $nextPage = $page + 1;
                $pagination .= '<a href="./index.php?page=users&admin_token=' . rawurlencode($adminToken) . '&search=' . rawurlencode($search) . '&role=' . rawurlencode($role) . '&page_num=' . $nextPage . '" class="btn ghost small">Seguinte »</a>';
            }
            $pagination .= '</div>';
        }

        $roleOptions = '';
        foreach ($roles as $roleOption) {
            $roleString = strtoupper((string) $roleOption);
            $selected = $roleString === strtoupper($role) ? ' selected' : '';
            $roleOptions .= '<option value="' . htmlspecialchars($roleString, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                . htmlspecialchars($roleString, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }

        $tokenSafe = htmlspecialchars($adminToken, ENT_QUOTES, 'UTF-8');

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Utilizadores - Meter API Admin</title>
<style>
:root{--bg:#f4f6fb;--card:#ffffff;--line:#d8e1eb;--text:#142032;--muted:#57708a;--accent:#1264a3;--accent-2:#0a8f6a}
*{box-sizing:border-box}
body{font-family:"Trebuchet MS","Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--text);margin:0}
main{max-width:1240px;margin:0 auto;padding:24px 20px 40px}
h1{margin:0;font-size:28px;letter-spacing:.2px}
.hero{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:18px}
.btn{background:linear-gradient(130deg,#1264a3,#0a8f6a);color:#fff;border:none;padding:10px 14px;border-radius:10px;font-weight:700;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.btn:hover{filter:brightness(1.06)}
.btn.ghost{background:#fff;color:#1f3c56;border:1px solid var(--line)}
.btn.small{padding:7px 10px;font-size:12px;border-radius:8px}
.panel{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:16px}
.panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
.table-tools{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
table{width:100%;border-collapse:collapse}
th,td{padding:9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}
th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.7px}
code{font-size:12px;word-break:break-all;background:#f3f8fd;padding:2px 4px;border-radius:6px}
.chip{display:inline-flex;align-items:center;background:#eef4ff;border:1px solid #c6dbff;color:#173b62;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.actions-cell{display:flex;gap:6px;flex-wrap:wrap}
.pagination{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:16px}
.page-info{color:var(--muted);font-size:14px}
</style>
</head>
<body>
<main>
<header class="hero">
  <div>
    <h1>Utilizadores</h1>
  </div>
  <div>
    <a href="./index.php?admin_token=' . $tokenSafe . '" class="btn ghost small">← Voltar</a>
  </div>
</header>

<section class="panel">
  <div class="panel-head">
    <h2>Lista de Utilizadores</h2>
  </div>
  <div class="table-tools">
    <form method="get" action="./index.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <input type="hidden" name="page" value="users">
      <input type="hidden" name="admin_token" value="' . $tokenSafe . '">
      <input type="search" name="search" placeholder="Pesquisar utilizador..." value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '">
      <select name="role">
        <option value="">Todas as roles</option>
        ' . $roleOptions . '
      </select>
      <button type="submit" class="btn small">Filtrar</button>
      <a href="./index.php?page=users&admin_token=' . $tokenSafe . '" class="btn ghost small">Limpar</a>
    </form>
  </div>
  <table>
    <thead><tr><th>Utilizador</th><th>Role</th><th>User ID</th><th>Salt / Token</th><th>Pass (hash)</th><th>Ações</th></tr></thead>
    <tbody>' . $userRows . '</tbody>
  </table>
  ' . $pagination . '
</section>

</main>
</body>
</html>';
    }

    /** @param array<int, array<string, mixed>> $meters */
    /** @param array<string, mixed> $state */
    public static function metersList(array $meters, array $state = []): string
    {
        $search = (string) ($state['search'] ?? '');
        $page = (int) ($state['page'] ?? 1);
        $totalCount = (int) ($state['total_count'] ?? 0);
        $perPage = (int) ($state['per_page'] ?? 50);
        $adminToken = (string) ($state['admin_token'] ?? '');

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

            $assignedUsersHtml = '-';
            if ($assignedUsers !== []) {
                $chips = [];
                foreach ($assignedUsers as $assignedUser) {
                    $chips[] = '<span class="chip">' . htmlspecialchars($assignedUser, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                $assignedUsersHtml = implode(' ', $chips);
            }

            $deveuiSafe = htmlspecialchars($deveui, ENT_QUOTES, 'UTF-8');

            $meterRows .= '<tr>'
                . '<td><code>' . $deveuiSafe . '</code></td>'
                . '<td>' . ($authKeys === [] ? '-' : implode('<br>', $authKeys)) . '</td>'
                . '<td>' . $assignedUsersHtml . '</td>'
                . '<td class="actions-cell">'
                . '<button type="button" class="btn ghost small" data-open-modal="modal-assign-meter" data-meterid="' . $deveuiSafe . '">Editar</button>'
                . '</td>'
                . '</tr>';
        }
        if ($meterRows === '') {
            $meterRows = '<tr><td colspan="4">Sem contadores encontrados.</td></tr>';
        }

        $totalPages = ceil($totalCount / $perPage);
        $pagination = '';
        if ($totalPages > 1) {
            $pagination .= '<div class="pagination">';
            if ($page > 1) {
                $prevPage = $page - 1;
                $pagination .= '<a href="./index.php?page=meters&admin_token=' . rawurlencode($adminToken) . '&search=' . rawurlencode($search) . '&page_num=' . $prevPage . '" class="btn ghost small">« Anterior</a>';
            }
            
            $pagination .= '<span class="page-info">Página ' . $page . ' de ' . $totalPages . ' (' . $totalCount . ' registos)</span>';
            
            if ($page < $totalPages) {
                $nextPage = $page + 1;
                $pagination .= '<a href="./index.php?page=meters&admin_token=' . rawurlencode($adminToken) . '&search=' . rawurlencode($search) . '&page_num=' . $nextPage . '" class="btn ghost small">Seguinte »</a>';
            }
            $pagination .= '</div>';
        }

        $tokenSafe = htmlspecialchars($adminToken, ENT_QUOTES, 'UTF-8');

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contadores - Meter API Admin</title>
<style>
:root{--bg:#f4f6fb;--card:#ffffff;--line:#d8e1eb;--text:#142032;--muted:#57708a;--accent:#1264a3;--accent-2:#0a8f6a}
*{box-sizing:border-box}
body{font-family:"Trebuchet MS","Segoe UI",Tahoma,sans-serif;background:var(--bg);color:var(--text);margin:0}
main{max-width:1240px;margin:0 auto;padding:24px 20px 40px}
h1{margin:0;font-size:28px;letter-spacing:.2px}
.hero{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:18px}
.btn{background:linear-gradient(130deg,#1264a3,#0a8f6a);color:#fff;border:none;padding:10px 14px;border-radius:10px;font-weight:700;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
.btn:hover{filter:brightness(1.06)}
.btn.ghost{background:#fff;color:#1f3c56;border:1px solid var(--line)}
.btn.small{padding:7px 10px;font-size:12px;border-radius:8px}
.panel{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px;margin-bottom:16px}
.panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
.table-tools{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
table{width:100%;border-collapse:collapse}
th,td{padding:9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;font-size:13px}
th{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.7px}
code{font-size:12px;word-break:break-all;background:#f3f8fd;padding:2px 4px;border-radius:6px}
.chip{display:inline-flex;align-items:center;background:#eef4ff;border:1px solid #c6dbff;color:#173b62;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.actions-cell{display:flex;gap:6px;flex-wrap:wrap}
.pagination{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:16px}
.page-info{color:var(--muted);font-size:14px}
</style>
</head>
<body>
<main>
<header class="hero">
  <div>
    <h1>Contadores</h1>
  </div>
  <div>
    <a href="./index.php?admin_token=' . $tokenSafe . '" class="btn ghost small">← Voltar</a>
  </div>
</header>

<section class="panel">
  <div class="panel-head">
    <h2>Lista de Contadores</h2>
  </div>
  <div class="table-tools">
    <form method="get" action="./index.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <input type="hidden" name="page" value="meters">
      <input type="hidden" name="admin_token" value="' . $tokenSafe . '">
      <input type="search" name="search" placeholder="Pesquisar DevEUI ou utilizador..." value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '">
      <button type="submit" class="btn small">Filtrar</button>
      <a href="./index.php?page=meters&admin_token=' . $tokenSafe . '" class="btn ghost small">Limpar</a>
    </form>
  </div>
  <table>
    <thead><tr><th>DevEUI</th><th>Auth Keys</th><th>Atribuído a</th><th>Ações</th></tr></thead>
    <tbody>' . $meterRows . '</tbody>
  </table>
  ' . $pagination . '
</section>

</main>
</body>
</html>';
    }
}

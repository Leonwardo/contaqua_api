# MeterApp Backend API (PHP + MongoDB)

API backend compatível com a app Android **sem alterar a app** (apenas trocar URL/IP no `server_url/server_port` e, se necessário, `app_updater_url/app_updater_port`).

## 1) O que foi analisado na app MeterApp

### Servidor de dados/autenticação
- A app usa `server_url` + `server_port` (settings), defaults definidos em `settings.xml`.
- Referência: `apps/MeterApp/app/src/main/res/xml/settings.xml`.

### Servidor de updater
- A app usa `app_updater_url` + `app_updater_port` para updates APK.
- Referência: `apps/MeterApp/app/src/main/java/com/example/androidupload/appUpdater/HttpsAndroidUpdater.java`.

### Endpoints legados que a app já chama
- `POST /api/user_token`
- `POST /api/meter_token`
- `POST /api/config`
- `GET /api/config/{id}`
- `POST /api/firmware`
- `GET /api/firmware/{id}`
- `POST /api/meterdiag_list`
- `POST /api/meterdiag_report`
- `GET /api/server`

## 2) Endpoints novos solicitados

- `POST /api/auth/validate`
- `POST /api/meter/authorize`
- `GET /api/meter/config`
- `POST /api/meter/session`
- Painel admin: `GET /admin` e `GET /api/admin/metrics`

## 3) Coleções MongoDB usadas (imutáveis)

- `meter_config`
- `meter_session`
- `meter_auth`
- `user_auth`

A API foi implementada para usar apenas estas 4 coleções.

## 4) Requisitos de runtime

- PHP 8+
- Extensão MongoDB para PHP (`ext-mongodb`)
- Biblioteca `mongodb/mongodb` (Composer)

## 5) Setup rápido

1. Copiar `.env.example` para `.env`:

```powershell
Copy-Item .env.example .env
```

2. Editar `.env` e preencher:
- `MONGO_URI` (com password real)
- `MONGO_DB_NAME`
- `ADMIN_TOKEN`

3. Instalar Composer (se não tiver):
- https://getcomposer.org/download/

4. Instalar dependências:

```powershell
composer install
```

5. Garantir extensão MongoDB ativa no PHP:
- No `php.ini`, habilitar `extension=mongodb`
- Reiniciar Apache

## 6) Exemplo de requests

### Validar token
```http
POST /api/auth/validate
Content-Type: application/json

{ "token": "USER_TOKEN" }
```

### Autorizar contador
```http
POST /api/meter/authorize
Content-Type: application/json

{ "authkey": "KEY", "user": "rui", "meterid": "ABC123" }
```

### Configuração (novo endpoint)
```http
GET /api/meter/config?user=rui&meterid=ABC123
```

### Sessão (aceita timestamps antigos)
```http
POST /api/meter/session
Content-Type: application/json

{
  "token": "USER_TOKEN",
  "user": "rui",
  "meterid": "ABC123",
  "timestamp": "2025-01-01T10:20:30Z",
  "payload": { "reading": 123.45 }
}
```

### Login legado da app
```http
POST /api/user_token
Content-Type: application/x-www-form-urlencoded

user=rui&pass=1234
```

### Meter token legado da app
```http
POST /api/meter_token
Content-Type: application/x-www-form-urlencoded

token=USER_TOKEN&challenge=AABBCCDD&deveui=0011223344556677
```

## 7) Segurança e observações

- Sem JWT inventado. A autenticação usa token de `user_auth`.
- `valid` e `valid_until` são respeitados.
- Sessões não são rejeitadas por timestamp antigo.
- Logs em `storage/logs/api.log`.
- Painel admin protegido por `ADMIN_TOKEN` (query `?admin_token=` ou `Authorization: Bearer ...`).

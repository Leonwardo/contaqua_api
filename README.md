# Contaqua API v2 - Ubuntu VPS Setup (PHP 8.4 + Latest)

API backend profissional em PHP 8.4 + Slim Framework 4 + MongoDB 8.0 para aplicação Android MeterApp.

---

## 📋 Requisitos do Sistema (Versões Mais Recentes)

### Servidor (Ubuntu 24.04 LTS ou 24.10)
- **Ubuntu**: 24.04 LTS ou 24.10
- **Nginx**: 1.26+ (última versão estável)
- **PHP**: 8.4-FPM (última versão)
- **MongoDB**: 8.0 (última versão)
- **Composer**: 2.8+ (última versão)
- **Git**: 2.40+

### Extensões PHP Obrigatórias
| Extensão | Versão | Uso |
|----------|--------|-----|
| ext-mongodb | 1.20+ | Ligação MongoDB |
| ext-json | Built-in | API JSON |
| ext-mbstring | Built-in | Manipulação strings |
| ext-curl | Built-in | HTTP requests |
| ext-openssl | Built-in | Criptografia |
| ext-bcmath | Built-in | Cálculos precisos |
| ext-zip | Built-in | Composer/Uploads |
| ext-xml | Built-in | Parser XML |
| ext-intl | Built-in | Internacionalização |
| ext-ctype | Built-in | Validação |

### Dependências Composer (Versões Atualizadas 2024)
```json
{
    "php": "^8.4",
    "slim/slim": "^4.15",
    "slim/psr7": "^1.8",
    "mongodb/mongodb": "^1.20",
    "vlucas/phpdotenv": "^5.6",
    "monolog/monolog": "^3.10",
    "php-di/php-di": "^7.0"
}
```

---

## 🚀 Instalação Completa em Ubuntu VPS

### ETAPA 1: Preparação do Sistema

```bash
# Atualizar sistema completamente
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl gnupg2 ca-certificates lsb-release ubuntu-keyring

# Configurar timezone
sudo timedatectl set-timezone Europe/Lisbon
```

### ETAPA 2: Instalar Nginx (Última Versão)

```bash
# Importar chave oficial Nginx
sudo curl https://nginx.org/keys/nginx_signing.key | gpg --dearmor \
    | sudo tee /usr/share/keyrings/nginx-archive-keyring.gpg >/dev/null

# Adicionar repositório oficial Nginx
echo "deb [signed-by=/usr/share/keyrings/nginx-archive-keyring.gpg] \
http://nginx.org/packages/ubuntu $(lsb_release -cs) nginx" \
    | sudo tee /etc/apt/sources.list.d/nginx.list

# Instalar
sudo apt update
sudo apt install nginx -y

# Verificar versão
nginx -v  # Deve mostrar nginx/1.26.x ou superior

# Iniciar e habilitar
sudo systemctl start nginx
sudo systemctl enable nginx
```

### ETAPA 3: Instalar PHP 8.4-FPM (Última Versão)

```bash
# Adicionar repositório PHP de Ondřej Surý (mantenedor oficial)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP 8.4 e todas as extensões necessárias
sudo apt install -y \
    php8.4-fpm \
    php8.4-mongodb \
    php8.4-mbstring \
    php8.4-curl \
    php8.4-openssl \
    php8.4-bcmath \
    php8.4-zip \
    php8.4-xml \
    php8.4-intl \
    php8.4-ctype \
    php8.4-json \
    php8.4-fileinfo \
    php8.4-tokenizer \
    php8.4-opcache

# Verificar versão
php -v  # Deve mostrar PHP 8.4.x

# Verificar extensões instaladas
php -m | grep -E "mongodb|mbstring|curl|openssl|bcmath|zip|json"
```

### ETAPA 4: Instalar MongoDB 8.0 (Última Versão)

```bash
# Importar chave GPG MongoDB
curl -fsSL https://www.mongodb.org/static/pgp/server-8.0.asc | \
   sudo gpg -o /usr/share/keyrings/mongodb-server-8.0.gpg --dearmor

# Adicionar repositório MongoDB 8.0
UBUNTU_VERSION=$(lsb_release -cs)
echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-8.0.gpg ] \
https://repo.mongodb.org/apt/ubuntu ${UBUNTU_VERSION}/mongodb-org/8.0 multiverse" | \
   sudo tee /etc/apt/sources.list.d/mongodb-org-8.0.list

# Atualizar e instalar
sudo apt update
sudo apt install -y mongodb-org

# Iniciar MongoDB
sudo systemctl start mongod
sudo systemctl enable mongod

# Verificar status
sudo systemctl status mongod
mongod --version  # Deve mostrar v8.0.x

# Criar database e collections
mongosh <<'EOF'
use contaqua
db.createCollection("user_auth")
db.createCollection("meter_auth")
db.createCollection("meter_config")
db.createCollection("meter_session")

// Criar indexes para performance
db.user_auth.createIndex({ "user": 1 }, { unique: true })
db.user_auth.createIndex({ "user_id": 1 }, { unique: true })
db.meter_auth.createIndex({ "deveui": 1 }, { unique: true })
db.meter_session.createIndex({ "deveui": 1, "timestamp": -1 })
db.meter_config.createIndex({ "name": 1 })

print("Database e collections criados com sucesso")
EOF
```

### ETAPA 5: Instalar Composer (Última Versão)

```bash
# Baixar installer oficial
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

# Verificar hash (opcional mas recomendado)
HASH=$(curl -sS https://composer.github.io/installer.sig)
php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

# Instalar
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"

# Verificar versão
composer --version  # Deve mostrar 2.8.x ou superior
```

---

## 📁 Configuração do Projeto

### ETAPA 6: Configurar Diretórios e Permissões

```bash
# Criar estrutura do projeto
sudo mkdir -p /var/www/contaqua_api_v2
sudo chown -R $USER:$USER /var/www/contaqua_api_v2

# Criar diretórios necessários
sudo mkdir -p /var/www/contaqua_api_v2/logs
sudo mkdir -p /var/www/contaqua_api_v2/storage/uploads

# Configurar permissões (www-data = usuário do nginx/php-fpm)
sudo chown -R www-data:www-data /var/www/contaqua_api_v2
sudo chmod -R 755 /var/www/contaqua_api_v2
sudo chmod -R 775 /var/www/contaqua_api_v2/logs
sudo chmod -R 775 /var/www/contaqua_api_v2/storage
```

### ETAPA 7: Copiar Ficheiros do Projeto

```bash
# Opção 1: Via Git (recomendado)
cd /var/www/contaqua_api_v2
git clone https://SEU_REPO.git .  # ou clonar localmente

# Opção 2: Via SCP/RSYNC (do seu PC local)
# scp -r /caminho/local/contaqua_api_v2/* root@SEU_IP:/var/www/contaqua_api_v2/

# Opção 3: Via ZIP
# Descompactar ficheiros na pasta /var/www/contaqua_api_v2/
```

### ETAPA 8: Instalar Dependências Composer

```bash
cd /var/www/contaqua_api_v2

# Instalar dependências (sem dev para produção)
composer install --no-dev --optimize-autoloader --no-interaction

# Ou com dev para desenvolvimento
composer install --optimize-autoloader

# Verificar se autoload foi criado
ls -la vendor/autoload.php
```

### ETAPA 9: Configurar Ambiente (.env)

```bash
cd /var/www/contaqua_api_v2
cp .env.example .env
sudo nano .env
```

**Configuração mínima para produção:**
```env
# Application
APP_NAME=ContaquaAPI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com
APP_TIMEZONE=Europe/Lisbon

# MongoDB (ajustar se usar autenticação)
MONGO_URI=mongodb://127.0.0.1:27017
MONGO_DATABASE=contaqua

# Admin (MUITO IMPORTANTE - ALTERAR!)
ADMIN_TOKEN=seu_token_seguro_aqui_32_chars_minimo
ADMIN_SESSION_TIMEOUT=3600

# Security
CORS_ORIGIN=*
RATE_LIMIT_ENABLED=true

# Logging
LOG_LEVEL=info
LOG_PATH=logs/app.log

# Uploads
UPLOAD_MAX_SIZE=10485760
UPLOAD_PATH=storage/uploads/

# Legacy
LEGACY_AUTH_MODE=true
ALLOW_LEGACY_PLAIN_PASSWORDS=false
```

**Proteger ficheiro .env:**
```bash
sudo chown www-data:www-data /var/www/contaqua_api_v2/.env
sudo chmod 640 /var/www/contaqua_api_v2/.env
```

---

## 🔧 Configuração Nginx + PHP-FPM

### ETAPA 10: Configurar Nginx

Criar configuração:

```bash
sudo nano /etc/nginx/sites-available/contaqua-api
```

Adicionar (substituir `seu-dominio.com` ou IP):
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name seu-dominio.com;  # ou seu IP
    root /var/www/contaqua_api_v2/public;
    index index.php;

    # Logs
    access_log /var/log/nginx/contaqua_access.log;
    error_log /var/log/nginx/contaqua_error.log warn;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;

    # PHP-FPM
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization if_not_empty;
        fastcgi_param PHP_VALUE "upload_max_filesize=10M \n post_max_size=10M";
        include fastcgi_params;
        
        # Performance
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_cache_lock on;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        return 404;
    }

    # Deny access to sensitive files
    location ~\.(env|git|lock|md|sql|log)$ {
        deny all;
        return 404;
    }

    # Main rewrite rule - Slim Framework
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

Ativar site:
```bash
sudo ln -s /etc/nginx/sites-available/contaqua-api /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default  # remover default se existir
sudo nginx -t
sudo systemctl reload nginx
```

### ETAPA 11: Configurar PHP-FPM

```bash
sudo nano /etc/php/8.4/fpm/pool.d/www.conf
```

Ajustar para performance:
```ini
; Usuário do pool
user = www-data
group = www-data
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Performance
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; Environment
clear_env = no
```

Reiniciar:
```bash
sudo systemctl restart php8.4-fpm
```

---

## 🔒 Segurança e Firewall

### ETAPA 12: Configurar UFW (Firewall)

```bash
# Instalar UFW se não estiver
sudo apt install ufw -y

# Configurar padrões
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Permitir serviços necessários
sudo ufw allow 22/tcp    # SSH (ajustar porta se diferente)
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS

# Ativar
sudo ufw enable

# Verificar status
sudo ufw status verbose
```

### ETAPA 13: Configurar HTTPS (Let's Encrypt - Opcional mas Recomendado)

```bash
# Instalar Certbot
sudo apt install certbot python3-certbot-nginx -y

# Obter certificado (substituir pelo seu domínio)
sudo certbot --nginx -d seu-dominio.com

# Configurar auto-renewal
sudo certbot renew --dry-run
```

---

## ✅ Testar Instalação

### Verificações Finais

```bash
# 1. Testar health endpoint
curl http://localhost/api/health

# 2. Testar via IP externo (do seu PC)
curl http://SEU_IP_VPS/api/health

# 3. Testar admin panel
curl http://localhost/admin

# 4. Verificar logs em tempo real
sudo tail -f /var/log/nginx/contaqua_error.log
sudo tail -f /var/www/contaqua_api_v2/logs/app.log
```

---

## 📊 Resumo de Comandos (Cheat Sheet)

```bash
# Status dos serviços
sudo systemctl status nginx
sudo systemctl status php8.4-fpm
sudo systemctl status mongod

# Restart serviços
sudo systemctl restart nginx
sudo systemctl restart php8.4-fpm
sudo systemctl restart mongod

# Logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/www/contaqua_api_v2/logs/app.log
sudo tail -f /var/log/mongodb/mongod.log

# MongoDB CLI
mongosh

# Atualizar projeto
cd /var/www/contaqua_api_v2
git pull
composer install --no-dev --optimize-autoloader
sudo chown -R www-data:www-data /var/www/contaqua_api_v2
sudo systemctl reload php8.4-fpm
```

---

## 🐛 Troubleshooting

### "502 Bad Gateway"
```bash
# Verificar PHP-FPM
sudo systemctl restart php8.4-fpm
sudo systemctl status php8.4-fpm

# Verificar socket
ls -la /var/run/php/php8.4-fpm.sock
```

### "MongoDB connection failed"
```bash
# Verificar MongoDB
sudo systemctl status mongod
sudo systemctl restart mongod

# Testar conexão
mongosh --eval "db.adminCommand('ping')"
```

### "Permission denied"
```bash
sudo chown -R www-data:www-data /var/www/contaqua_api_v2
sudo chmod -R 775 /var/www/contaqua_api_v2/logs
sudo chmod -R 775 /var/www/contaqua_api_v2/storage
```

### "Class not found"
```bash
cd /var/www/contaqua_api_v2
composer dump-autoload
sudo chown -R www-data:www-data vendor/
```

---

## 🗄️ MongoDB - Schema das Coleções

### Coleções Obrigatórias

| Coleção | Descrição | Indexes |
|---------|-----------|---------|
| `user_auth` | Utilizadores do sistema | user (unique), user_id (unique) |
| `meter_auth` | Autenticação de contadores | deveui (unique) |
| `meter_config` | Configurações dos contadores | name |
| `meter_session` | Sessões/registros | deveui + timestamp (desc) |

### Schema user_auth
```javascript
{
    _id: ObjectId,
    access: Number,      // 1=TECHNICIAN, 2=MANAGER, 3=MANUFACTURER, 4=FACTORY
    user_id: Number,     // ID numérico único
    user: String,        // Username
    pass: String,        // SHA256 hash
    salt: String         // Salt para hash
}
```

### Schema meter_auth
```javascript
{
    _id: ObjectId,
    deveui: String,           // 16 chars hex
    authkeys: [String],        // Array de auth keys
    assigned_users: [String], // Users com acesso
    valid_until: ISODate       // Data expiração (opcional)
}
```

### Schema meter_session
```javascript
{
    _id: ObjectId,
    counter: Number,
    sessionkey: String,   // Hex string
    deveui: String,       // 16 chars hex
    comment: String,      // Opcional
    timestamp: ISODate    // Data/hora sessão
}
```

---

## 📞 Suporte

Para problemas:
1. Verificar logs: `/var/log/nginx/` e `/var/www/contaqua_api_v2/logs/`
2. Testar MongoDB: `mongosh`
3. Testar PHP: `php -v` e `php -m`

---

**Documentação atualizada para:**
- Ubuntu 24.04/24.10
- PHP 8.4 (última versão)
- MongoDB 8.0 (última versão)
- Nginx 1.26+
- Composer 2.8+

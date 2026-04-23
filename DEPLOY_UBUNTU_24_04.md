# Guia de Deploy - Contaqua API no Ubuntu 24.04.4 LTS

Este guia explica como instalar a Contaqua API num servidor Ubuntu 24.04.4 LTS (Noble Numbat).

---

## 📋 Requisitos

- **Servidor**: Ubuntu 24.04.4 LTS (fresh install recomendado)
- **Acesso**: Root ou sudo
- **Domínio**: Opcional (pode usar IP)
- **MongoDB**: MongoDB Atlas (cloud) ou local

---

## 🚀 Instalação Rápida (Automática)

### Passo 1: Preparar o Servidor

```bash
# Aceder ao servidor via SSH
ssh root@SEU_IP_SERVIDOR

# Atualizar sistema (recomendado)
apt update && apt upgrade -y
```

### Passo 2: Transferir Ficheiros do Projeto

**Opção A - Via SCP (do seu computador local):**
```bash
# No seu computador (Windows/PowerShell ou Linux/Mac)
scp -r c:\xampp\htdocs\contaqua_api\* root@SEU_IP_SERVIDOR:/var/www/contaqua_api/

# Ou se estiver em Linux/Mac
scp -r /caminho/para/contaqua_api/* root@SEU_IP_SERVIDOR:/var/www/contaqua_api/
```

**Opção B - Via Git:**
```bash
# No servidor
apt install git -y
git clone https://SEU_REPOSITORIO/contaqua_api.git /var/www/contaqua_api
```

**Opção C - Via FTP/SFTP:**
- Usar FileZilla ou similar
- Copiar todos os ficheiros para `/var/www/contaqua_api/`

### Passo 3: Executar o Script de Instalação

```bash
# Dar permissões ao script
chmod +x /var/www/contaqua_api/install.sh

# Executar o script
sudo bash /var/www/contaqua_api/install.sh

# Ou com domínio específico:
sudo bash /var/www/contaqua_api/install.sh seudominio.com
```

Este script irá:
1. ✅ Atualizar o sistema
2. ✅ Instalar Nginx
3. ✅ Instalar PHP 8.3 + extensões (mongodb, mbstring, curl, etc.)
4. ✅ Instalar Composer
5. ✅ Criar estrutura de diretórios
6. ✅ Criar ficheiro .env base
7. ✅ Configurar Nginx
8. ✅ Configurar permissões
9. ✅ Instalar MongoDB Shell (opcional)

### Passo 4: Configurar Variáveis de Ambiente

```bash
# Editar o ficheiro .env
nano /var/www/contaqua_api/.env
```

**Configurações obrigatórias:**

```env
# MongoDB (Atlas - Cloud)
MONGO_URI=mongodb+srv://USERNAME:PASSWORD@cluster.mongodb.net/water_meter?retryWrites=true&w=majority
MONGO_DATABASE=water_meter

# OU MongoDB Local
MONGO_URI=mongodb://127.0.0.1:27017
MONGO_DATABASE=contaqua

# Segurança - ALTERAR ESTE TOKEN!
ADMIN_TOKEN=EscolhaUmTokenSeguroAqui123

# URL da aplicação
APP_URL=http://SEU_IP_OU_DOMINIO
```

### Passo 5: Instalar Dependências PHP

```bash
cd /var/www/contaqua_api

# Instalar dependências (sem pacotes de desenvolvimento)
composer install --no-dev --optimize-autoloader

# Se der erro de memória:
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader
```

### Passo 6: Reiniciar Serviços

```bash
# Reiniciar PHP-FPM
systemctl restart php8.3-fpm

# Reiniciar Nginx
systemctl restart nginx

# Verificar status
systemctl status php8.3-fpm
systemctl status nginx
```

### Passo 7: Testar Instalação

```bash
# Testar endpoint de health
curl http://localhost/api/health

# Resposta esperada:
# {"status":"operational","timestamp":"...","version":"2.0"}
```

No browser:
- **API**: `http://SEU_IP/api/health`
- **Admin**: `http://SEU_IP/admin?admin_token=SEU_TOKEN_AQUI`

---

## 🔧 Instalação Manual (Passo a Passo Detalhado)

Se preferir fazer manualmente ou o script automático falhar:

### 1. Atualizar Sistema

```bash
apt update && apt upgrade -y
```

### 2. Instalar Nginx

```bash
apt install nginx -y
systemctl enable nginx
systemctl start nginx
```

### 3. Instalar PHP 8.3

```bash
# Adicionar repositório Ondrej (para PHP 8.3 em Ubuntu 24.04)
apt install software-properties-common -y
add-apt-repository ppa:ondrej/php -y
apt update

# Instalar PHP 8.3 e extensões necessárias
apt install -y php8.3-fpm php8.3-cli php8.3-mongodb php8.3-mbstring \
    php8.3-curl php8.3-openssl php8.3-bcmath php8.3-zip php8.3-xml

# Verificar instalação
php -v
php -m | grep mongodb
```

### 4. Instalar Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
composer --version
```

### 5. Criar Estrutura do Projeto

```bash
mkdir -p /var/www/contaqua_api
mkdir -p /var/www/contaqua_api/logs
mkdir -p /var/www/contaqua_api/storage/uploads

# Copiar ficheiros do projeto (ver Passo 2 da instalação rápida)
# ...

# Permissões
chown -R www-data:www-data /var/www/contaqua_api
chmod -R 755 /var/www/contaqua_api
chmod -R 775 /var/www/contaqua_api/logs
chmod -R 775 /var/www/contaqua_api/storage
```

### 6. Configurar Nginx

Criar ficheiro `/etc/nginx/sites-available/contaqua_api`:

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    
    root /var/www/contaqua_api/public;
    index index.php;
    
    access_log /var/log/nginx/contaqua_access.log;
    error_log /var/log/nginx/contaqua_error.log warn;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization if_not_empty;
        include fastcgi_params;
    }
    
    location ~ /\. {
        deny all;
    }
    
    location ~\.(env|git|lock|md|sql|log)$ {
        deny all;
    }
}
```

Ativar configuração:
```bash
ln -sf /etc/nginx/sites-available/contaqua_api /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
```

### 7. Instalar Dependências

```bash
cd /var/www/contaqua_api
composer install --no-dev --optimize-autoloader
```

### 8. Configurar .env

```bash
cp .env.example .env  # se existir exemplo
nano .env
```

Ver Passo 4 da instalação rápida para configurações.

### 9. Reiniciar Serviços

```bash
systemctl restart php8.3-fpm
systemctl restart nginx
```

---

## 🛡️ Configurar SSL (HTTPS) com Let's Encrypt

### 1. Instalar Certbot

```bash
apt install certbot python3-certbot-nginx -y
```

### 2. Obter Certificado

```bash
# Para um domínio específico
certbot --nginx -d seudominio.com -d www.seudominio.com

# Modo automático (sem interação)
certbot --nginx -d seudominio.com --non-interactive --agree-tos -m email@seudominio.com
```

### 3. Verificar Renovação Automática

```bash
certbot renew --dry-run
```

---

## 🔥 Configurar Firewall (UFW)

```bash
# Instalar UFW se não estiver instalado
apt install ufw -y

# Configurar regras
ufw default deny incoming
ufw default allow outgoing

# Permitir SSH (IMPORTANTE - não se bloqueie!)
ufw allow 22/tcp

# Permitir HTTP e HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Ativar firewall
ufw --force enable

# Verificar status
ufw status
```

---

## 🗄️ Configurar MongoDB (Opcional - Se usar local)

Se não usar MongoDB Atlas e quiser MongoDB local:

```bash
# Instalar MongoDB
wget -qO - https://www.mongodb.org/static/pgp/server-7.0.asc | gpg --dearmor -o /usr/share/keyrings/mongodb-server-7.0.gpg
echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" | tee /etc/apt/sources.list.d/mongodb-org-7.0.list
apt update
apt install -y mongodb-org

# Iniciar e ativar
systemctl start mongod
systemctl enable mongod

# Criar database e collections
mongosh <<EOF
use contaqua
db.createCollection('user_auth')
db.createCollection('meter_auth')
db.createCollection('meter_config')
db.createCollection('meter_session')
EOF
```

---

## ✅ Verificação Pós-Instalação

### Testar Endpoints

```bash
# Health check
curl http://localhost/api/health

# Testar login (vai falhar sem credenciais, mas testa o endpoint)
curl -X POST http://localhost/api/user_token -d "user=test&pass=test"

# Testar lista de configs (vai falhar sem token, mas testa o endpoint)
curl -X POST http://localhost/api/config -d "token=invalid&deveui=test"
```

### Verificar Logs

```bash
# Logs do Nginx
tail -f /var/log/nginx/contaqua_error.log

# Logs da aplicação
tail -f /var/www/contaqua_api/logs/app.log

# Logs do PHP-FPM
journalctl -u php8.3-fpm -f
```

---

## 🐛 Resolução de Problemas

### Erro: "Could not open input file: composer.phar"
```bash
# Instalar composer globalmente
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### Erro: "Connection refused" no MongoDB
```bash
# Verificar se a URI está correta no .env
# Se usar Atlas: verificar IP whitelist no MongoDB Atlas
# Se usar local: verificar se mongod está a correr
systemctl status mongod
```

### Erro: "Permission denied" nos logs
```bash
chown -R www-data:www-data /var/www/contaqua_api/logs
chmod -R 775 /var/www/contaqua_api/logs
```

### Erro: "502 Bad Gateway"
```bash
# Verificar PHP-FPM
systemctl status php8.3-fpm

# Verificar socket
ls -la /var/run/php/php8.3-fpm.sock

# Corrigir permissões do socket
nano /etc/php/8.3/fpm/pool.d/www.conf
# Alterar: listen.owner = www-data
# Alterar: listen.group = www-data
systemctl restart php8.3-fpm
```

### Erro: "Class MongoDB\Client not found"
```bash
# Verificar se extensão mongodb está instalada
php -m | grep mongodb

# Se não aparecer, instalar:
apt install php8.3-mongodb -y
systemctl restart php8.3-fpm
```

---

## 📱 Configurar MeterApp

No ficheiro de configuração do MeterApp:

```json
{
  "server_url": "SEU_IP_OU_DOMINIO",
  "server_port": "80",
  "app_updater_url": "SEU_IP_OU_DOMINIO",
  "app_updater_port": "80"
}
```

Se usar HTTPS:
```json
{
  "server_url": "SEU_IP_OU_DOMINIO",
  "server_port": "443",
  "app_updater_url": "SEU_IP_OU_DOMINIO",
  "app_updater_port": "443"
}
```

---

## 📝 Comandos Úteis

```bash
# Reiniciar tudo
systemctl restart php8.3-fpm nginx

# Ver status
systemctl status php8.3-fpm nginx

# Ver processos PHP
ps aux | grep php

# Ver portas em uso
netstat -tlnp | grep :80

# Testar configuração Nginx
nginx -t

# Ver espaço em disco
df -h

# Ver uso de memória
free -h
```

---

## 🎉 Conclusão

Após completar estes passos, a API estará:
- ✅ Acessível em `http://SEU_IP` ou `https://seudominio.com`
- ✅ Com SSL configurado (se aplicável)
- ✅ Com firewall ativo
- ✅ Pronta para receber pedidos do MeterApp

**Próximo passo**: Aceder ao painel admin e criar o primeiro utilizador e contador.

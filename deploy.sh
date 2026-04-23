#!/bin/bash
# Deploy script para Ubuntu VPS
# Executar como: sudo bash deploy.sh

set -e

echo "=== Contaqua API v2 - Deploy Script ==="
echo ""

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Funções
print_status() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERRO]${NC} $1"
}

print_info() {
    echo -e "${YELLOW}[INFO]${NC} $1"
}

# Verificar se é root
if [[ $EUID -ne 0 ]]; then
   print_error "Este script precisa ser executado como root (sudo)"
   exit 1
fi

# Variáveis
PROJECT_DIR="/var/www/apisagem"
DOMAIN=${1:-"_"}

echo "Diretório do projeto: $PROJECT_DIR"
echo "Domínio: $DOMAIN"
echo ""

# 1. Atualizar sistema
print_info "1. Atualizando sistema..."
apt update && apt upgrade -y
print_status "Sistema atualizado"

# 2. Instalar Nginx
print_info "2. Instalando Nginx..."
if ! command -v nginx &> /dev/null; then
    apt install nginx -y
    systemctl enable nginx
    systemctl start nginx
    print_status "Nginx instalado"
else
    print_status "Nginx já instalado"
fi

# 3. Instalar PHP 8.3
print_info "3. Instalando PHP 8.3-FPM..."
if ! command -v php &> /dev/null || ! php -v | grep -q "8.3"; then
    apt install software-properties-common -y
    add-apt-repository ppa:ondrej/php -y
    apt update
    apt install php8.3-fpm php8.3-mongodb php8.3-mbstring php8.3-curl php8.3-bcmath php8.3-zip php8.3-xml -y
    print_status "PHP 8.3 instalado"
else
    print_status "PHP 8.3 já instalado"
fi

# Verificar extensões
print_info "Verificando extensões PHP..."
php -m | grep -E "mongodb|mbstring|curl|openssl|bcmath|zip" || true

# 4. Instalar MongoDB
print_info "4. Instalando MongoDB..."
if ! command -v mongod &> /dev/null; then
    wget -qO - https://www.mongodb.org/static/pgp/server-7.0.asc | gpg --dearmor -o /usr/share/keyrings/mongodb-server-7.0.gpg
    echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" | tee /etc/apt/sources.list.d/mongodb-org-7.0.list
    apt update
    apt install mongodb-org -y
    systemctl start mongod
    systemctl enable mongod
    print_status "MongoDB instalado"
else
    print_status "MongoDB já instalado"
    systemctl restart mongod
fi

# 5. Instalar Composer
print_info "5. Instalando Composer..."
if ! command -v composer &> /dev/null; then
    apt install curl -y
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    print_status "Composer instalado"
else
    print_status "Composer já instalado"
fi

# 6. Criar estrutura do projeto
print_info "6. Configurando projeto..."
mkdir -p $PROJECT_DIR

# Verificar se há ficheiros no diretório
if [ ! -f "$PROJECT_DIR/composer.json" ]; then
    print_error "Ficheiros do projeto não encontrados em $PROJECT_DIR"
    print_info "Certifique-se de que o código está na pasta $PROJECT_DIR"
    print_info "Pode usar: git clone SEU_REPO $PROJECT_DIR"
    exit 1
fi

# 7. Instalar dependências
print_info "7. Instalando dependências Composer..."
cd $PROJECT_DIR
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || {
    print_info "Tentando com memory limit aumentado..."
    COMPOSER_MEMORY_LIMIT=-1 COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
}
print_status "Dependências instaladas"

# 8. Configurar permissões
print_info "8. Configurando permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs
chmod -R 775 $PROJECT_DIR/storage
print_status "Permissões configuradas"

# 9. Configurar Nginx
print_info "9. Configurando Nginx..."
cat > /etc/nginx/sites-available/apisagem << 'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/apisagem/public;
    index index.php;

    access_log /var/log/nginx/apisagem_access.log;
    error_log /var/log/nginx/apisagem_error.log warn;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization if_not_empty;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\. {
        deny all;
    }

    location ~\.(env|git|lock|md|sh|log)$ {
        deny all;
    }
}
EOF

# Ativar site
ln -sf /etc/nginx/sites-available/apisagem /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
print_status "Nginx configurado"

# 10. Configurar MongoDB (se local)
print_info "10. Configurando MongoDB (se local)..."
if command -v mongosh &> /dev/null; then
    mongosh --eval "
        use apisagem;
        db.createCollection('user_auth');
        db.createCollection('meter_auth');
        db.createCollection('meter_config');
        db.createCollection('meter_session');
        print('Database e collections criados');
    " 2>/dev/null || print_info "MongoDB remoto ou já configurado"
else
    print_info "mongosh não instalado - usando MongoDB Atlas ou já configurado"
fi

# 11. Configurar .env se não existir
if [ ! -f "$PROJECT_DIR/.env" ]; then
    print_info "11. Criando ficheiro .env..."
    cat > $PROJECT_DIR/.env << EOF
APP_NAME=ContaquaAPI
APP_ENV=production
APP_DEBUG=false
APP_URL=http://$DOMAIN
APP_TIMEZONE=Europe/Lisbon

MONGO_URI=mongodb://127.0.0.1:27017
MONGO_DATABASE=apisagem

ADMIN_TOKEN=change_this_to_secure_random_token_$(openssl rand -hex 8)
ADMIN_SESSION_TIMEOUT=3600

CORS_ORIGIN=*
RATE_LIMIT_ENABLED=false

LOG_LEVEL=info
LOG_PATH=logs/app.log

UPLOAD_MAX_SIZE=10485760
UPLOAD_PATH=storage/uploads/

LEGACY_AUTH_MODE=true
ALLOW_LEGACY_PLAIN_PASSWORDS=false
EOF
    chown www-data:www-data $PROJECT_DIR/.env
    chmod 640 $PROJECT_DIR/.env
    print_status "Ficheiro .env criado"
    print_info "IMPORTANTE: Edite $PROJECT_DIR/.env e altere o ADMIN_TOKEN!"
else
    print_status "Ficheiro .env já existe"
fi

# 12. Reiniciar serviços
print_info "12. Reiniciando serviços..."
systemctl restart php8.3-fpm
systemctl restart nginx
print_status "Serviços reiniciados"

# 13. Configurar firewall
print_info "13. Configurando firewall..."
if command -v ufw &> /dev/null; then
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
    print_status "Firewall configurado"
else
    print_info "UFW não instalado, ignorando..."
fi

# Testar
print_info "Testando instalação..."
sleep 2
if curl -s http://localhost/api/health | grep -q "operational"; then
    print_status "API está operacional!"
else
    print_error "API não respondeu corretamente"
    print_info "Verifique logs: tail -f /var/log/nginx/contaqua_error.log"
fi

echo ""
echo "=== Deploy Concluído ==="
echo ""
echo "Aceda ao painel admin: http://$DOMAIN/admin"
echo "Token admin: grep ADMIN_TOKEN $PROJECT_DIR/.env"
echo ""
echo "Comandos úteis:"
echo "  Ver logs:  tail -f /var/log/nginx/apisagem_error.log"
echo "  Reiniciar: systemctl restart nginx php8.3-fpm"
echo "  Update:    bash /var/www/apisagem/update.sh"
echo ""

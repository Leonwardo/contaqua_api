#!/bin/bash
# ============================================
# Contaqua API - Script de Instalação Único
# ============================================
# Uso: sudo bash install.sh

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=========================================="
echo "  Instalador Contaqua API v2"
echo "=========================================="
echo ""

# Verificar se é root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}[ERRO]${NC} Execute como root: sudo bash install.sh"
   exit 1
fi

PROJECT_DIR="/var/www/contaqua_api"
DOMAIN=${1:-"_"}

echo -e "${YELLOW}[1/8]${NC} Atualizando sistema..."
apt update -qq && apt upgrade -y -qq

echo -e "${YELLOW}[2/8]${NC} Instalando Nginx..."
apt install -y -qq nginx
systemctl enable nginx

echo -e "${YELLOW}[3/8]${NC} Instalando PHP 8.3 e extensões..."
apt install -y -qq software-properties-common
add-apt-repository ppa:ondrej/php -y -qq
apt update -qq
apt install -y -qq php8.3-fpm php8.3-mongodb php8.3-mbstring php8.3-curl php8.3-openssl php8.3-bcmath php8.3-zip php8.3-xml php8.3-cli
systemctl enable php8.3-fpm

echo -e "${YELLOW}[4/8]${NC} Instalando Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --quiet
    mv composer.phar /usr/local/bin/composer
fi

echo -e "${YELLOW}[5/8]${NC} Configurando diretórios..."
mkdir -p $PROJECT_DIR
mkdir -p $PROJECT_DIR/logs
mkdir -p $PROJECT_DIR/storage/uploads
mkdir -p $PROJECT_DIR/storage/logs

echo -e "${YELLOW}[6/8]${NC} Criando .env..."
cat > $PROJECT_DIR/.env << 'EOF'
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
APP_TIMEZONE=UTC

MONGO_URI=mongodb+srv://contaqua_api:Contaqua99@water-meter-cluster.wlvbwms.mongodb.net/contaqua_api?retryWrites=true&w=majority
MONGO_DATABASE=water_meter

ADMIN_TOKEN=ContaquaAdminSecure2026
ADMIN_SESSION_TIMEOUT=3600

CORS_ORIGIN=*
RATE_LIMIT_ENABLED=false
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=60

LOG_LEVEL=info
LOG_PATH=logs/app.log
LOG_MAX_FILES=30

UPLOAD_MAX_SIZE=10485760
UPLOAD_PATH=storage/uploads/

LEGACY_AUTH_MODE=true
ALLOW_LEGACY_PLAIN_PASSWORDS=false
EOF

chmod 640 $PROJECT_DIR/.env

echo -e "${YELLOW}[7/8]${NC} Configurando Nginx..."
cat > /etc/nginx/sites-available/contaqua_api << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name $DOMAIN;
    
    root $PROJECT_DIR/public;
    index index.php;
    
    access_log /var/log/nginx/contaqua_access.log;
    error_log /var/log/nginx/contaqua_error.log warn;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization if_not_empty;
        include fastcgi_params;
    }
    
    location ~ /\. {
        deny all;
    }
    
    location ~\.(env|git|lock|md|sql|log)$ {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/contaqua_api /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t

echo -e "${YELLOW}[8/8]${NC} Configurando permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs
chmod -R 775 $PROJECT_DIR/storage

echo -e "${YELLOW}[Extra]${NC} Instalando MongoDB Shell (mongosh)..."
if ! command -v mongosh &> /dev/null; then
    wget -qO - https://www.mongodb.org/static/pgp/server-7.0.asc | gpg --dearmor -o /usr/share/keyrings/mongodb-server-7.0.gpg 2>/dev/null || true
    echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" | tee /etc/apt/sources.list.d/mongodb-org-7.0.list >/dev/null 2>&1 || true
    apt update -qq 2>/dev/null || true
    apt install -y -qq mongodb-mongosh 2>/dev/null || echo "mongosh não disponível, mas não é obrigatório se usar MongoDB Atlas"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✓ Instalação concluída!${NC}"
echo "=========================================="
echo ""
echo "Próximos passos:"
echo "1. Copie os ficheiros do projeto para: $PROJECT_DIR"
echo "   (incluindo composer.json, src/, routes/, public/, config/)"
echo "2. Edite $PROJECT_DIR/.env e configure a MONGO_URI corretamente"
echo "3. Execute: cd $PROJECT_DIR && composer install --no-dev"
echo "4. Reinicie serviços: systemctl restart nginx php8.3-fpm"
echo ""
echo "Testar: curl http://localhost/api/health"
echo "Admin:  http://localhost/admin?admin_token=ContaquaAdminSecure2026"
echo ""
echo "IMPORTANTE: Altere o ADMIN_TOKEN no ficheiro .env antes de colocar em produção!"
echo ""

# Reiniciar serviços
systemctl restart php8.3-fpm 2>/dev/null || true
systemctl restart nginx 2>/dev/null || true

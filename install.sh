#!/bin/bash
# ============================================
# Contaqua API - Script de Instalação Completo
# ============================================
# Uso: sudo bash install.sh
# Instala tudo automaticamente para a pasta /var/www/apisagem

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo -e "${BLUE}  Instalador Contaqua API - Completo${NC}"
echo "=========================================="
echo ""

# Verificar se é root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}[ERRO]${NC} Execute como root: sudo bash install.sh"
   exit 1
fi

PROJECT_DIR="/var/www/apisagem"
DOMAIN=${1:-"_"}

# Função para verificar erros
check_error() {
    if [ $? -ne 0 ]; then
        echo -e "${RED}[ERRO]${NC} Falhou: $1"
        echo -e "${YELLOW}[INFO]${NC} Continuando mesmo assim..."
    fi
}

echo -e "${YELLOW}[1/10]${NC} Atualizando sistema..."
apt update -y
check_error "apt update"
apt upgrade -y
check_error "apt upgrade"

echo -e "${YELLOW}[2/10]${NC} Instalando Nginx..."
apt install -y nginx
check_error "nginx install"
systemctl enable nginx

echo -e "${YELLOW}[3/10]${NC} Instalando PHP 8.3 e extensões..."
apt install -y software-properties-common
check_error "software-properties"
echo -e "${BLUE}[INFO]${NC} A adicionar repositório PHP..."
add-apt-repository ppa:ondrej/php -y
echo -e "${BLUE}[INFO]${NC} Atualizando packages..."
apt update -y
echo -e "${BLUE}[INFO]${NC} A instalar PHP 8.3 (pode demorar)..."
apt install -y php8.3-fpm php8.3-mongodb php8.3-mbstring php8.3-curl php8.3-bcmath php8.3-zip php8.3-xml php8.3-cli php8.3-gd
check_error "PHP install"
systemctl enable php8.3-fpm

echo -e "${YELLOW}[4/10]${NC} Instalando Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    echo -e "${GREEN}[OK]${NC} Composer instalado"
else
    echo -e "${GREEN}[OK]${NC} Composer já existe"
fi

echo -e "${YELLOW}[5/10]${NC} Configurando diretórios..."
mkdir -p $PROJECT_DIR
mkdir -p $PROJECT_DIR/logs
mkdir -p $PROJECT_DIR/storage/uploads
mkdir -p $PROJECT_DIR/storage/logs
echo -e "${GREEN}[OK]${NC} Diretórios criados"

echo -e "${YELLOW}[6/10]${NC} Verificando ficheiros do projeto..."
if [ ! -f "$PROJECT_DIR/composer.json" ]; then
    echo -e "${RED}[AVISO]${NC} composer.json não encontrado em $PROJECT_DIR"
    echo -e "${YELLOW}[INFO]${NC} Certifique-se de que o código está na pasta $PROJECT_DIR"
    echo -e "${YELLOW}[INFO]${NC} Pode fazer: git clone SEU_REPO $PROJECT_DIR"
fi

echo -e "${YELLOW}[7/10]${NC} Configurando Nginx para apisagem..."
cat > /etc/nginx/sites-available/apisagem << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name $DOMAIN;
    
    root $PROJECT_DIR/public;
    index index.php;
    
    access_log /var/log/nginx/apisagem_access.log;
    error_log /var/log/nginx/apisagem_error.log warn;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Tamanho máximo de upload
    client_max_body_size 10M;
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization if_not_empty;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    location ~ /\. {
        deny all;
    }
    
    location ~\.(env|git|lock|md|sql|log|sh)$ {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/apisagem /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t
check_error "Nginx config"

echo -e "${YELLOW}[8/10]${NC} Configurando permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs
chmod -R 775 $PROJECT_DIR/storage
echo -e "${GREEN}[OK]${NC} Permissões configuradas"

echo -e "${YELLOW}[9/10]${NC} Instalando dependências Composer..."
if [ -f "$PROJECT_DIR/composer.json" ]; then
    cd $PROJECT_DIR
    export COMPOSER_ALLOW_SUPERUSER=1
    composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || {
        echo -e "${YELLOW}[INFO]${NC} Tentando com memory limit aumentado..."
        COMPOSER_MEMORY_LIMIT=-1 COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
    }
    echo -e "${GREEN}[OK]${NC} Dependências instaladas"
else
    echo -e "${RED}[AVISO]${NC} composer.json não encontrado. Pulei instalação de dependências."
fi

echo -e "${YELLOW}[10/10]${NC} Criando ficheiro .env..."
if [ ! -f "$PROJECT_DIR/.env" ]; then
    cat > $PROJECT_DIR/.env << EOF
APP_ENV=production
APP_DEBUG=false
APP_URL=http://$DOMAIN
APP_TIMEZONE=Europe/Lisbon

MONGO_URI=mongodb://127.0.0.1:27017
MONGO_DATABASE=apisagem

ADMIN_TOKEN=$(openssl rand -hex 16)
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
    chown www-data:www-data $PROJECT_DIR/.env
    chmod 640 $PROJECT_DIR/.env
    echo -e "${GREEN}[OK]${NC} Ficheiro .env criado"
else
    echo -e "${GREEN}[OK]${NC} .env já existe, mantido"
fi

echo -e "${YELLOW}[Extra]${NC} Instalando MongoDB Shell (mongosh)..."
if ! command -v mongosh &> /dev/null; then
    wget -qO - https://www.mongodb.org/static/pgp/server-7.0.asc 2>/dev/null | gpg --dearmor -o /usr/share/keyrings/mongodb-server-7.0.gpg 2>/dev/null || true
    echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" 2>/dev/null | tee /etc/apt/sources.list.d/mongodb-org-7.0.list > /dev/null 2>&1 || true
    apt update -y 2>/dev/null || true
    apt install -y mongodb-mongosh 2>/dev/null || echo -e "${YELLOW}[INFO]${NC} mongosh não disponível (opcional)"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✓ INSTALAÇÃO CONCLUÍDA!${NC}"
echo "=========================================="
echo ""
echo -e "${BLUE}Informações:${NC}"
echo "  Project Dir: $PROJECT_DIR"
echo "  Domain: $DOMAIN"
echo ""
echo -e "${YELLOW}Próximos passos:${NC}"
echo "  1. Edite $PROJECT_DIR/.env e configure a MONGO_URI"
echo "  2. Obtenha o ADMIN_TOKEN: grep ADMIN_TOKEN $PROJECT_DIR/.env"
echo "  3. Aceda: http://$DOMAIN/admin"
echo ""
echo -e "${GREEN}Teste:${NC}"
echo "  curl http://localhost/api/health"
echo ""

# Reiniciar serviços
echo -e "${YELLOW}[INFO]${NC} A reiniciar serviços..."
systemctl restart php8.3-fpm || echo -e "${YELLOW}[AVISO]${NC} php8.3-fpm restart falhou"
systemctl restart nginx || echo -e "${YELLOW}[AVISO]${NC} nginx restart falhou"

# Testar
echo -e "${YELLOW}[INFO]${NC} A testar API..."
sleep 2
if curl -s http://localhost/api/health 2>/dev/null | grep -q "operational"; then
    echo -e "${GREEN}[SUCESSO]${NC} API está operacional!"
else
    echo -e "${RED}[AVISO]${NC} API não respondeu. Verifique:"
    echo "  tail -f /var/log/nginx/apisagem_error.log"
fi

echo ""

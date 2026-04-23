#!/bin/bash
# ============================================
# Contaqua API - Script de Instalação Completo
# ============================================
# Uso: sudo bash install.sh
# Instala tudo automaticamente para a pasta /var/www/apisagem
# NÃO FECHA em erros - continua sempre até ao fim

# NÃO usar set -e para não fechar em erros
# set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo -e "${BLUE}  Instalador Contaqua API - Completo${NC}"
echo -e "${RED}  Refined by X Delta Core and Leonwardo${NC}"
echo "=========================================="
echo ""

# Verificar se é root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}[ERRO]${NC} Execute como root: sudo bash install.sh"
   exit 1
fi

PROJECT_DIR="/var/www/apisagem"
DOMAIN=${1:-"_"}
SERVER_IP=$(hostname -I | awk '{print $1}')

echo -e "${BLUE}INFO:${NC} Diretório do projeto: $PROJECT_DIR"
echo -e "${BLUE}INFO:${NC} IP do servidor: $SERVER_IP"
echo ""

echo -e "${YELLOW}[1/10]${NC} Atualizando sistema..."
apt update -y
apt upgrade -y
echo -e "${GREEN}[OK]${NC} Sistema atualizado"

echo -e "${YELLOW}[2/10]${NC} Instalando Nginx..."
apt install -y nginx
systemctl enable nginx
echo -e "${GREEN}[OK]${NC} Nginx instalado"

echo -e "${YELLOW}[3/10]${NC} Instalando PHP 8.3 e extensões..."
apt install -y software-properties-common
echo -e "${BLUE}[INFO]${NC} A adicionar repositório PHP (Ondrej)..."
add-apt-repository ppa:ondrej/php -y
apt update -y
echo -e "${BLUE}[INFO]${NC} A instalar PHP 8.3 e extensões..."
apt install -y php8.3-fpm php8.3-mongodb php8.3-mbstring php8.3-curl php8.3-bcmath php8.3-zip php8.3-xml php8.3-cli php8.3-gd
echo -e "${GREEN}[OK]${NC} PHP 8.3 instalado"
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
    echo -e "${RED}[AVISO]${NC} composer.json NÃO encontrado!"
    echo -e "${YELLOW}[INFO]${NC} Execute primeiro: git clone SEU_REPO $PROJECT_DIR"
    echo -e "${YELLOW}[INFO]${NC} Ou copie os ficheiros do projeto para $PROJECT_DIR"
fi

echo -e "${YELLOW}[7/10]${NC} Configurando Nginx..."
# Fazer backup da config anterior se existir
if [ -f "/etc/nginx/sites-available/apisagem" ]; then
    cp /etc/nginx/sites-available/apisagem /etc/nginx/sites-available/apisagem.bak
fi

cat > /etc/nginx/sites-available/apisagem << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    
    root /var/www/apisagem/public;
    index index.php;
    
    access_log /var/log/nginx/apisagem_access.log;
    error_log /var/log/nginx/apisagem_error.log warn;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
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

# Remover default se existir
rm -f /etc/nginx/sites-enabled/default

# Criar link simbólico
ln -sf /etc/nginx/sites-available/apisagem /etc/nginx/sites-enabled/apisagem

# Testar configuração
echo -e "${BLUE}[INFO]${NC} A testar configuração Nginx..."
nginx -t && echo -e "${GREEN}[OK]${NC} Configuração Nginx válida" || echo -e "${RED}[AVISO]${NC} Erro na configuração Nginx"

echo -e "${YELLOW}[8/10]${NC} Configurando permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs 2>/dev/null || true
chmod -R 775 $PROJECT_DIR/storage 2>/dev/null || true
echo -e "${GREEN}[OK]${NC} Permissões configuradas"

echo -e "${YELLOW}[9/10]${NC} Instalando dependências Composer..."
if [ -f "$PROJECT_DIR/composer.json" ]; then
    cd $PROJECT_DIR
    export COMPOSER_ALLOW_SUPERUSER=1
    echo -e "${BLUE}[INFO]${NC} A correr composer install (pode demorar)..."
    composer install --no-dev --optimize-autoloader --no-interaction || {
        echo -e "${YELLOW}[INFO]${NC} A tentar com memory limit aumentado..."
        COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction
    }
    echo -e "${GREEN}[OK]${NC} Composer install concluído"
else
    echo -e "${RED}[AVISO]${NC} composer.json não encontrado - dependencies não instaladas"
fi

echo -e "${YELLOW}[10/10]${NC} Criando ficheiro .env..."
# Criar .env sempre (mesmo que já exista, faz backup)
if [ -f "$PROJECT_DIR/.env" ]; then
    cp $PROJECT_DIR/.env $PROJECT_DIR/.env.backup.$(date +%Y%m%d_%H%M%S)
    echo -e "${YELLOW}[INFO]${NC} Backup do .env anterior criado"
fi

# Gerar token aleatório
ADMIN_TOKEN=$(openssl rand -hex 16 2>/dev/null || date +%s | sha256sum | base64 | head -c 32)

cat > $PROJECT_DIR/.env << EOF
APP_ENV=production
APP_DEBUG=false
APP_URL=http://$SERVER_IP
APP_TIMEZONE=Europe/Lisbon

# MongoDB - ALTERE ESTES VALORES!
MONGO_URI=mongodb://127.0.0.1:27017
MONGO_DATABASE=apisagem

# Segurança - ALTERE ESTE TOKEN!
ADMIN_TOKEN=$ADMIN_TOKEN
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

echo -e "${YELLOW}[EXTRA]${NC} Instalando MongoDB Shell (opcional)..."
if ! command -v mongosh &> /dev/null; then
    wget -qO - https://www.mongodb.org/static/pgp/server-7.0.asc 2>/dev/null | gpg --dearmor -o /usr/share/keyrings/mongodb-server-7.0.gpg 2>/dev/null || true
    echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" 2>/dev/null | tee /etc/apt/sources.list.d/mongodb-org-7.0.list > /dev/null 2>&1 || true
    apt update -y 2>/dev/null || true
    apt install -y mongodb-mongosh 2>/dev/null || echo -e "${YELLOW}[INFO]${NC} mongosh não instalado (opcional)"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✓ INSTALAÇÃO CONCLUÍDA!${NC}"
echo "=========================================="
echo ""

# Reiniciar serviços
echo -e "${YELLOW}[INFO]${NC} A reiniciar serviços..."
systemctl restart php8.3-fpm 2>/dev/null || echo -e "${YELLOW}[AVISO]${NC} php8.3-fpm"
systemctl restart nginx 2>/dev/null || echo -e "${YELLOW}[AVISO]${NC} nginx"
sleep 2

# Testar API
echo -e "${YELLOW}[INFO]${NC} A testar API..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null || echo "000")

echo ""
echo "=========================================="
if [ "$HTTP_STATUS" = "200" ]; then
    echo -e "${GREEN}✓ SITE ONLINE!${NC}"
    echo "=========================================="
    echo ""
    echo -e "${GREEN}Aceda ao site:${NC}"
    echo "  http://$SERVER_IP"
    echo "  http://$SERVER_IP/api/health"
    echo "  http://$SERVER_IP/admin"
    echo ""
    echo -e "${YELLOW}Credenciais Admin:${NC}"
    echo "  Token: $ADMIN_TOKEN"
    echo "  URL: http://$SERVER_IP/admin?admin_token=$ADMIN_TOKEN"
else
    echo -e "${YELLOW}⚠ SITE PODE NÃO ESTAR ACESSÍVEL${NC}"
    echo "=========================================="
    echo ""
    echo -e "${YELLOW}Verifique:${NC}"
    echo "  1. systemctl status nginx"
    echo "  2. systemctl status php8.3-fpm"
    echo "  3. tail -f /var/log/nginx/apisagem_error.log"
fi
echo ""
echo -e "${BLUE}IMPORTANTE - Edite o .env:${NC}"
echo "  nano $PROJECT_DIR/.env"
echo "  Altere MONGO_URI para o seu MongoDB Atlas"
echo ""
echo -e "${BLUE}Comandos úteis:${NC}"
echo "  Ver logs: tail -f /var/log/nginx/apisagem_error.log"
echo "  Restart:  systemctl restart nginx php8.3-fpm"
echo "  Update:   bash $PROJECT_DIR/update.sh"
echo ""

exit 0

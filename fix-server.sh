#!/bin/bash
# ============================================
# Contaqua API - Script de Correção do Servidor
# ============================================
# Corrige nginx, atualiza .env e coloca site online

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/apisagem"
SERVER_IP=$(hostname -I | awk '{print $1}')

echo "=========================================="
echo -e "${BLUE}  Correção do Servidor${NC}"
echo "=========================================="
echo ""

# Verificar se é root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}[ERRO]${NC} Execute como root: sudo bash fix-server.sh"
   exit 1
fi

# 1. VERIFICAR E CORRIGIR NGINX
echo -e "${YELLOW}[1/5]${NC} A verificar Nginx..."

# Verificar se nginx está instalado
if ! command -v nginx &> /dev/null; then
    echo -e "${YELLOW}[INFO]${NC} Nginx não encontrado, a instalar..."
    apt install -y nginx
fi

# Criar configuração correta do Nginx
echo -e "${YELLOW}[INFO]${NC} A criar configuração Nginx..."
cat > /etc/nginx/sites-available/apisagem << 'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    
    root /var/www/apisagem/public;
    index index.php index.html;
    
    access_log /var/log/nginx/apisagem_access.log;
    error_log /var/log/nginx/apisagem_error.log warn;
    
    # Tamanho máximo de upload
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
    
    location ~\.(env|git|lock|md|sql|log|sh)$ {
        deny all;
    }
}
EOF

# Ativar configuração
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/apisagem /etc/nginx/sites-enabled/apisagem

# Testar configuração
echo -e "${YELLOW}[INFO]${NC} A testar configuração Nginx..."
if nginx -t; then
    echo -e "${GREEN}[OK]${NC} Configuração Nginx válida"
else
    echo -e "${RED}[ERRO]${NC} Erro na configuração Nginx"
    nginx -t
fi

# 2. CORRIGIR PHP-FPM
echo -e "${YELLOW}[2/5]${NC} A verificar PHP-FPM..."
if [ -f "/var/run/php/php8.3-fpm.sock" ]; then
    echo -e "${GREEN}[OK]${NC} Socket PHP-FPM existe"
else
    echo -e "${YELLOW}[INFO]${NC} PHP-FPM pode não estar a correr"
fi

# 3. ATUALIZAR .ENV COM MONGODB ATLAS
echo -e "${YELLOW}[3/5]${NC} A atualizar .env com MongoDB Atlas..."

# Backup do .env atual
if [ -f "$PROJECT_DIR/.env" ]; then
    cp $PROJECT_DIR/.env $PROJECT_DIR/.env.backup.$(date +%Y%m%d_%H%M%S)
    echo -e "${GREEN}[OK]${NC} Backup do .env criado"
fi

# Criar novo .env com configurações Atlas
cat > $PROJECT_DIR/.env << EOF
# ============================================
# Contaqua API v2 - Environment Configuration
# ============================================

# Application
APP_NAME=ContaquaAPI
APP_ENV=production
APP_DEBUG=false
APP_URL=http://$SERVER_IP
APP_TIMEZONE=UTC

# MongoDB Configuration (MongoDB Atlas)
MONGO_URI=mongodb+srv://contaqua_api:Contaqua99@water-meter-cluster.wlvbwms.mongodb.net/contaqua_api?retryWrites=true&w=majority
MONGO_DATABASE=water_meter
MONGO_USERNAME=contaqua_api
MONGO_PASSWORD=Contaqua99

# Admin Configuration
ADMIN_TOKEN=ContaquaAdminSecure2026
ADMIN_SESSION_TIMEOUT=3600

# Security
CORS_ORIGIN=*
RATE_LIMIT_ENABLED=false
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=60

# Logging
LOG_LEVEL=debug
LOG_PATH=logs/app.log
LOG_MAX_FILES=30

# Uploads
UPLOAD_MAX_SIZE=10485760
UPLOAD_PATH=storage/uploads/

# Android App Compatibility
LEGACY_AUTH_MODE=true
ALLOW_LEGACY_PLAIN_PASSWORDS=false
EOF

chown www-data:www-data $PROJECT_DIR/.env
chmod 640 $PROJECT_DIR/.env
echo -e "${GREEN}[OK]${NC} .env atualizado com MongoDB Atlas"

# 4. CORRIGIR PERMISSÕES
echo -e "${YELLOW}[4/5]${NC} A corrigir permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs 2>/dev/null || true
chmod -R 775 $PROJECT_DIR/storage 2>/dev/null || true
echo -e "${GREEN}[OK]${NC} Permissões corrigidas"

# 5. REINICIAR SERVIÇOS
echo -e "${YELLOW}[5/5]${NC} A reiniciar serviços..."
systemctl stop nginx 2>/dev/null || true
systemctl stop php8.3-fpm 2>/dev/null || true
sleep 1
systemctl start php8.3-fpm
echo -e "${GREEN}[OK]${NC} PHP-FPM iniciado"
systemctl start nginx
echo -e "${GREEN}[OK]${NC} Nginx iniciado"
sleep 2

# TESTAR
echo ""
echo "=========================================="
echo -e "${YELLOW}[TESTE]${NC} A verificar se o site responde..."
echo "=========================================="

HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null || echo "000")

echo ""
if [ "$HTTP_STATUS" = "200" ]; then
    echo -e "${GREEN}✓ SITE ONLINE!${NC}"
    echo ""
    echo -e "${GREEN}URLs de acesso:${NC}"
    echo "  http://$SERVER_IP"
    echo "  http://$SERVER_IP/api/health"
    echo "  http://$SERVER_IP/admin"
    echo ""
    echo -e "${YELLOW}Admin:${NC}"
    echo "  Token: ContaquaAdminSecure2026"
    echo "  URL: http://$SERVER_IP/admin?admin_token=ContaquaAdminSecure2026"
else
    echo -e "${RED}✗ Site não responde (HTTP $HTTP_STATUS)${NC}"
    echo ""
    echo -e "${YELLOW}Diagnóstico:${NC}"
    echo "  systemctl status nginx"
    echo "  systemctl status php8.3-fpm"
    echo "  tail -20 /var/log/nginx/apisagem_error.log"
    echo "  tail -20 $PROJECT_DIR/logs/app.log"
fi

echo ""
echo -e "${BLUE}Comandos úteis:${NC}"
echo "  Restart: systemctl restart nginx php8.3-fpm"
echo "  Logs:    tail -f /var/log/nginx/apisagem_error.log"
echo ""

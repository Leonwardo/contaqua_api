#!/bin/bash
# ============================================
# Contaqua API - Correção FINAL
# ============================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/apisagem"

echo "=========================================="
echo -e "${RED}  CORREÇÃO FINAL - RESTAURAR COMPOSER${NC}"
echo "=========================================="
echo ""

cd $PROJECT_DIR

# 1. BACKUP E RESTAURAR composer.json ORIGINAL
echo -e "${YELLOW}[1/6]${NC} A restaurar composer.json completo..."

if [ -f "composer.json.backup" ]; then
    cp composer.json.backup composer.json
    echo -e "${GREEN}[OK]${NC} composer.json restaurado do backup"
else
    # Criar composer.json completo
    cat > composer.json << 'EOF'
{
  "name": "contaqua/api-v2",
  "description": "Contaqua API v2 - Professional REST API with Slim Framework 4 and MongoDB",
  "type": "project",
  "license": "proprietary",
  "require": {
    "php": "^8.2",
    "ext-json": "*",
    "ext-mongodb": "*",
    "ext-mbstring": "*",
    "ext-curl": "*",
    "ext-openssl": "*",
    "ext-bcmath": "*",
    "ext-zip": "*",
    "ext-xml": "*",
    "mongodb/mongodb": "^1.15 || ^2.0",
    "slim/slim": "^4.15",
    "slim/psr7": "^1.8",
    "vlucas/phpdotenv": "^5.6",
    "monolog/monolog": "^3.10",
    "php-di/php-di": "^7.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  },
  "config": {
    "optimize-autoloader": true
  }
}
EOF
    echo -e "${GREEN}[OK]${NC} composer.json completo criado"
fi

# 2. LIMPAR VENDOR E LOCK
echo -e "${YELLOW}[2/6]${NC} A limpar vendor e lock..."
rm -rf vendor/
rm -f composer.lock
echo -e "${GREEN}[OK]${NC} Limpo"

# 3. INSTALAR DEPENDÊNCIAS
echo -e "${YELLOW}[3/6]${NC} A instalar dependências..."
echo -e "${BLUE}[INFO]${NC} Isso pode demorar 2-3 minutos..."

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1

if [ $? -ne 0 ]; then
    echo -e "${YELLOW}[AVISO]${NC} Tentando com ignore-platform..."
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs 2>&1
fi

# 4. VERIFICAR SE VENDOR FOI CRIADO
echo -e "${YELLOW}[4/6]${NC} A verificar instalação..."
if [ -f "vendor/autoload.php" ]; then
    echo -e "${GREEN}[OK]${NC} vendor/autoload.php existe"
    
    # Verificar se php-di existe
    if [ -f "vendor/php-di/php-di/src/ContainerBuilder.php" ]; then
        echo -e "${GREEN}[OK]${NC} php-di instalado"
    else
        echo -e "${RED}[ERRO]${NC} php-di NÃO encontrado!"
    fi
    
    # Verificar symfony
    if [ -f "vendor/symfony/polyfill-php80/bootstrap.php" ]; then
        echo -e "${GREEN}[OK]${NC} symfony polyfill instalado"
    else
        echo -e "${RED}[ERRO]${NC} symfony polyfill NÃO encontrado!"
    fi
else
    echo -e "${RED}[ERRO]${NC} vendor/autoload.php NÃO existe!"
fi

# 5. PERMISSÕES
echo -e "${YELLOW}[5/7]${NC} A corrigir permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs 2>/dev/null || mkdir -p $PROJECT_DIR/logs && chmod 775 $PROJECT_DIR/logs
chmod -R 775 $PROJECT_DIR/storage 2>/dev/null || mkdir -p $PROJECT_DIR/storage && chmod 775 $PROJECT_DIR/storage
echo -e "${GREEN}[OK]${NC} Permissões corrigidas"

# 6. CONFIGURAR NGINX
echo -e "${YELLOW}[6/7]${NC} A configurar Nginx..."

# Criar configuração correta do Nginx
cat > /etc/nginx/sites-available/apisagem << 'NGINXEOF'
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
NGINXEOF

# Ativar configuração
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
ln -sf /etc/nginx/sites-available/apisagem /etc/nginx/sites-enabled/apisagem

# Testar configuração
if nginx -t 2>&1 | grep -q "successful"; then
    echo -e "${GREEN}[OK]${NC} Configuração Nginx válida"
else
    echo -e "${YELLOW}[AVISO]${NC} Erro na configuração:"
    nginx -t
fi

# 7. REINICIAR E TESTAR
echo -e "${YELLOW}[7/7]${NC} A reiniciar e testar..."
systemctl restart php8.3-fpm
systemctl restart nginx
sleep 3

# Testar
echo ""
echo -e "${YELLOW}[TESTE FINAL]${NC}"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null || echo "000")

echo ""
if [ "$HTTP_STATUS" = "200" ]; then
    SERVER_IP=$(hostname -I | awk '{print $1}')
    echo -e "${GREEN}==========================================${NC}"
    echo -e "${GREEN}✓✓✓ SITE ONLINE! ✓✓✓${NC}"
    echo -e "${GREEN}==========================================${NC}"
    echo ""
    echo -e "${GREEN}URL:${NC} http://$SERVER_IP"
    echo -e "${GREEN}API:${NC} http://$SERVER_IP/api/health"
else
    echo -e "${RED}✗ ERRO PERSISTE (HTTP $HTTP_STATUS)${NC}"
    echo ""
    echo -e "${YELLOW}Ver logs:${NC}"
    tail -5 /var/log/nginx/apisagem_error.log
fi

echo ""

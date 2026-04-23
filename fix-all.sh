#!/bin/bash
# ============================================
# Contaqua API - Fix Final Completo
# ============================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/apisagem"
SERVER_IP="213.63.236.100"

echo "=========================================="
echo -e "${RED}  FIX FINAL - Resolver Erro 500${NC}"
echo "=========================================="
echo ""

cd $PROJECT_DIR

# 1. LIMPAR TUDO E RECOMEÇAR
echo -e "${YELLOW}[1/6]${NC} A limpar vendor e lock..."
rm -rf vendor/
rm -f composer.lock
rm -rf /tmp/composer-*
echo -e "${GREEN}[OK]${NC} Limpo"

# 2. RESTAURAR COMPOSER.JSON ORIGINAL
echo -e "${YELLOW}[2/6]${NC} A restaurar composer.json original..."
if [ -f "composer.json.backup" ]; then
    cp composer.json.backup composer.json
else
    # Criar composer.json completo compatível com PHP 8.3
    cat > composer.json << 'EOF'
{
  "name": "contaqua/api-v2",
  "description": "Contaqua API v2",
  "type": "project",
  "require": {
    "php": "^8.2",
    "ext-json": "*",
    "ext-mongodb": "*",
    "ext-mbstring": "*",
    "ext-curl": "*",
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
fi
echo -e "${GREEN}[OK]${NC} composer.json restaurado"

# 3. INSTALAR DEPENDÊNCIAS COM CACHE LIMPO
echo -e "${YELLOW}[3/6]${NC} A instalar dependências (sem cache)..."
export COMPOSER_ALLOW_SUPERUSER=1
composer clear-cache 2>/dev/null || true

# Tentar instalação completa
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs 2>&1

if [ $? -eq 0 ]; then
    echo -e "${GREEN}[OK]${NC} Composer install concluído"
else
    echo -e "${YELLOW}[AVISO]${NC} Instalação com erros, verificando..."
fi

# 4. VERIFICAR SE TODOS OS FICHEIROS EXISTEM
echo -e "${YELLOW}[4/6]${NC} A verificar ficheiros críticos..."

FILES_OK=true

if [ ! -f "vendor/autoload.php" ]; then
    echo -e "${RED}[✗]${NC} vendor/autoload.php - NÃO EXISTE"
    FILES_OK=false
else
    echo -e "${GREEN}[✓]${NC} vendor/autoload.php"
fi

if [ ! -f "vendor/php-di/php-di/src/ContainerBuilder.php" ]; then
    echo -e "${RED}[✗]${NC} php-di/ContainerBuilder.php - NÃO EXISTE"
    FILES_OK=false
else
    echo -e "${GREEN}[✓]${NC} php-di instalado"
fi

if [ ! -f "vendor/slim/slim/Slim/App.php" ]; then
    echo -e "${RED}[✗]${NC} slim/App.php - NÃO EXISTE"
    FILES_OK=false
else
    echo -e "${GREEN}[✓]${NC} slim instalado"
fi

if [ ! -f "vendor/mongodb/mongodb/src/Client.php" ]; then
    echo -e "${RED}[✗]${NC} mongodb/Client.php - NÃO EXISTE"
    FILES_OK=false
else
    echo -e "${GREEN}[✓]${NC} mongodb instalado"
fi

# 5. CORRIGIR PERMISSÕES E NGINX
echo -e "${YELLOW}[5/6]${NC} A corrigir permissões e Nginx..."

# Permissões
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
mkdir -p $PROJECT_DIR/logs
chmod 775 $PROJECT_DIR/logs

# Corrigir link simbólico do Nginx
rm -f /etc/nginx/sites-enabled/apisagem
rm -f /etc/nginx/sites-enabled/default

# Recriar configuração Nginx
cat > /etc/nginx/sites-available/apisagem << EOF
server {
    listen 80;
    server_name $SERVER_IP;
    
    root $PROJECT_DIR/public;
    index index.php index.html;
    
    access_log /var/log/nginx/apisagem_access.log;
    error_log /var/log/nginx/apisagem_error.log;
    
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

# Criar link simbólico correto
ln -sf /etc/nginx/sites-available/apisagem /etc/nginx/sites-enabled/apisagem

echo -e "${GREEN}[OK]${NC} Configuração atualizada"

# 6. REINICIAR E TESTAR
echo -e "${YELLOW}[6/6]${NC} A reiniciar serviços..."

systemctl restart php8.3-fpm
systemctl restart nginx

sleep 3

echo ""
echo "=========================================="
echo -e "${YELLOW}[TESTE FINAL]${NC}"
echo "=========================================="

# Verificar logs de erro
if [ -f "/var/log/nginx/apisagem_error.log" ]; then
    echo -e "${YELLOW}Últimos erros Nginx:${NC}"
    tail -3 /var/log/nginx/apisagem_error.log
fi

echo ""

# Testar site
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null || echo "000")

echo ""
if [ "$HTTP_STATUS" = "200" ]; then
    echo -e "${GREEN}==========================================${NC}"
    echo -e "${GREEN}✓✓✓ SITE ONLINE! ✓✓✓${NC}"
    echo -e "${GREEN}==========================================${NC}"
    echo ""
    echo -e "${GREEN}URLs:${NC}"
    echo "  http://$SERVER_IP"
    echo "  http://$SERVER_IP/api/health"
    echo "  http://$SERVER_IP/admin"
    echo ""
    echo -e "${GREEN}Admin Token:${NC}"
    grep "ADMIN_TOKEN" $PROJECT_DIR/.env 2>/dev/null || echo "  (ver .env)"
elif [ "$HTTP_STATUS" = "500" ]; then
    echo -e "${RED}✗ ERRO 500 PERSISTE${NC}"
    echo ""
    echo -e "${YELLOW}Diagnóstico:${NC}"
    
    # Verificar erro específico do PHP
    if [ -f "$PROJECT_DIR/logs/app.log" ]; then
        echo -e "${YELLOW}Logs da app:${NC}"
        tail -5 $PROJECT_DIR/logs/app.log
    fi
    
    echo ""
    echo -e "${YELLOW}Testar PHP manualmente:${NC}"
    php -r "require '$PROJECT_DIR/vendor/autoload.php'; echo 'Autoload OK\n';" 2>&1
    
else
    echo -e "${RED}✗ Site não responde (HTTP $HTTP_STATUS)${NC}"
fi

echo ""
echo -e "${BLUE}Comandos úteis:${NC}"
echo "  tail -f /var/log/nginx/apisagem_error.log"
echo "  tail -f $PROJECT_DIR/logs/app.log"
echo "  systemctl restart php8.3-fpm nginx"
echo ""

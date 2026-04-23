#!/bin/bash
# ============================================
# Contaqua API - Correção URGENTE
# ============================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/apisagem"

echo "=========================================="
echo -e "${RED}  CORREÇÃO URGENTE${NC}"
echo "=========================================="
echo ""

cd $PROJECT_DIR

# 1. LIMPAR VENDOR CORROMPIDO
echo -e "${YELLOW}[1/5]${NC} A limpar vendor corrompido..."
rm -rf vendor/
rm -f composer.lock
echo -e "${GREEN}[OK]${NC} Vendor limpo"

# 2. CORRIGIR composer.json
echo -e "${YELLOW}[2/5]${NC} A corrigir composer.json..."

# Fazer backup
cp composer.json composer.json.backup

# Atualizar versão do mongodb para aceitar 2.x
cat > composer.json << 'EOF'
{
    "name": "contaqua/api",
    "description": "Contaqua Water Meter Management API",
    "type": "project",
    "require": {
        "php": "^8.0",
        "slim/slim": "^4.0",
        "slim/psr7": "^1.0",
        "mongodb/mongodb": "^1.15 || ^2.1",
        "vlucas/phpdotenv": "^5.0",
        "monolog/monolog": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    }
}
EOF

echo -e "${GREEN}[OK]${NC} composer.json atualizado"

# 3. INSTALAR DEPENDÊNCIAS
echo -e "${YELLOW}[3/5]${NC} A instalar dependências (pode demorar)..."
echo -e "${BLUE}[INFO]${NC} A ignorar verificação de plataforma..."

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-mongodb 2>&1

if [ $? -eq 0 ]; then
    echo -e "${GREEN}[OK]${NC} Composer install concluído"
else
    echo -e "${YELLOW}[AVISO]${NC} Tentar método alternativo..."
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1
fi

# 4. CORRIGIR PERMISSÕES
echo -e "${YELLOW}[4/5]${NC} A corrigir permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs 2>/dev/null || mkdir -p $PROJECT_DIR/logs && chmod 775 $PROJECT_DIR/logs
chmod -R 775 $PROJECT_DIR/storage 2>/dev/null || mkdir -p $PROJECT_DIR/storage && chmod 775 $PROJECT_DIR/storage
echo -e "${GREEN}[OK]${NC} Permissões corrigidas"

# 5. REINICIAR E TESTAR
echo -e "${YELLOW}[5/5]${NC} A reiniciar serviços..."
systemctl restart php8.3-fpm
systemctl restart nginx
sleep 3

# Testar
echo ""
echo -e "${YELLOW}[TESTE]${NC} A testar site..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null || echo "000")

echo ""
if [ "$HTTP_STATUS" = "200" ]; then
    SERVER_IP=$(hostname -I | awk '{print $1}')
    echo -e "${GREEN}==========================================${NC}"
    echo -e "${GREEN}✓✓✓ SITE ONLINE! ✓✓✓${NC}"
    echo -e "${GREEN}==========================================${NC}"
    echo ""
    echo -e "${GREEN}Aceda:${NC} http://$SERVER_IP"
    echo -e "${GREEN}Teste:${NC} http://$SERVER_IP/api/health"
    echo -e "${GREEN}Admin:${NC} http://$SERVER_IP/admin"
else
    echo -e "${RED}✗ Ainda com erro (HTTP $HTTP_STATUS)${NC}"
    echo ""
    echo -e "${YELLOW}Verificar:${NC}"
    echo "  tail -20 /var/log/nginx/apisagem_error.log"
    echo "  tail -20 $PROJECT_DIR/logs/app.log"
fi

echo ""

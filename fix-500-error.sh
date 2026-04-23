#!/bin/bash
# ============================================
# Contaqua API - Corrigir Erro HTTP 500
# ============================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/apisagem"

echo "=========================================="
echo -e "${BLUE}  Corrigir Erro HTTP 500${NC}"
echo "=========================================="
echo ""

cd $PROJECT_DIR

# 1. Verificar logs do Nginx
echo -e "${YELLOW}[1/6]${NC} Logs do Nginx (últimos erros):"
tail -10 /var/log/nginx/apisagem_error.log 2>/dev/null || echo "  Sem erros recentes"
echo ""

# 2. Verificar se vendor existe
echo -e "${YELLOW}[2/6]${NC} Verificar Composer vendor..."
if [ ! -f "$PROJECT_DIR/vendor/autoload.php" ]; then
    echo -e "${RED}[ERRO]${NC} vendor/autoload.php NÃO EXISTE!"
    echo -e "${YELLOW}[INFO]${NC} A instalar dependências..."
    cd $PROJECT_DIR
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1
    echo -e "${GREEN}[OK]${NC} Composer install concluído"
else
    echo -e "${GREEN}[OK]${NC} vendor/autoload.php existe"
fi
echo ""

# 3. Verificar permissões
echo -e "${YELLOW}[3/6]${NC} Corrigir permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs 2>/dev/null || mkdir -p $PROJECT_DIR/logs && chmod 775 $PROJECT_DIR/logs
chmod -R 775 $PROJECT_DIR/storage 2>/dev/null || mkdir -p $PROJECT_DIR/storage && chmod 775 $PROJECT_DIR/storage
echo -e "${GREEN}[OK]${NC} Permissões corrigidas"
echo ""

# 4. Verificar .env
echo -e "${YELLOW}[4/6]${NC} Verificar .env..."
if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo -e "${RED}[ERRO]${NC} .env NÃO EXISTE!"
    echo -e "${YELLOW}[INFO]${NC} Criar .env com: nano $PROJECT_DIR/.env"
else
    echo -e "${GREEN}[OK]${NC} .env existe"
    ls -la $PROJECT_DIR/.env
    # Verificar permissões do .env
    chmod 640 $PROJECT_DIR/.env
    chown www-data:www-data $PROJECT_DIR/.env
fi
echo ""

# 5. Testar PHP
echo -e "${YELLOW}[5/6]${NC} Testar PHP..."
php -v | head -1
php -m | grep -i mongo || echo -e "${RED}[AVISO]${NC} Extensão mongodb não encontrada!"

# Testar se consegue correr PHP no projeto
cd $PROJECT_DIR
php -r "require 'vendor/autoload.php'; echo 'Autoload OK\n';" 2>&1 || echo -e "${RED}[ERRO]${NC} Falha no autoload!"
echo ""

# 6. Testar site
echo -e "${YELLOW}[6/6]${NC} Testar site..."
sleep 2
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null || echo "000")

echo ""
if [ "$HTTP_STATUS" = "200" ]; then
    echo -e "${GREEN}✓ SITE ONLINE!${NC}"
    echo ""
    SERVER_IP=$(hostname -I | awk '{print $1}')
    echo -e "${GREEN}Aceda:${NC} http://$SERVER_IP"
else
    echo -e "${RED}✗ Site ainda com erro (HTTP $HTTP_STATUS)${NC}"
    echo ""
    echo -e "${YELLOW}Verificar logs da app:${NC}"
    tail -20 $PROJECT_DIR/logs/app.log 2>/dev/null || echo "  (sem logs)"
    echo ""
    echo -e "${YELLOW}Verificar logs PHP:${NC}"
    tail -20 /var/log/php8.3-fpm.log 2>/dev/null || echo "  (sem logs PHP)"
fi

echo ""
echo "=========================================="
echo -e "${BLUE}Comandos para debug:${NC}"
echo "=========================================="
echo "  tail -f $PROJECT_DIR/logs/app.log"
echo "  tail -f /var/log/nginx/apisagem_error.log"
echo "  tail -f /var/log/php8.3-fpm.log"
echo "  systemctl restart php8.3-fpm"
echo ""

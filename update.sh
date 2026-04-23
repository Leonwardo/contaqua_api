#!/bin/bash
# ============================================
# Contaqua API - Script de Atualização Automática
# ============================================
# Uso: sudo bash update.sh
# Faz git pull + instala dependências + reinicia serviços

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/apisagem"

echo "=========================================="
echo -e "${BLUE}  Atualizador Contaqua API${NC}"
echo "=========================================="
echo ""

# Verificar se é root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}[ERRO]${NC} Execute como root: sudo bash update.sh"
   exit 1
fi

# Verificar se a pasta existe
if [ ! -d "$PROJECT_DIR" ]; then
    echo -e "${RED}[ERRO]${NC} Pasta $PROJECT_DIR não existe"
    exit 1
fi

cd $PROJECT_DIR

# Verificar se é um repositório git
if [ ! -d ".git" ]; then
    echo -e "${RED}[ERRO]${NC} $PROJECT_DIR não é um repositório git"
    echo -e "${YELLOW}[INFO]${NC} Inicialize o git ou clone o repositório"
    exit 1
fi

echo -e "${YELLOW}[1/6]${NC} A verificar estado do git..."

# Verificar se há mudanças locais não commitadas
if ! git diff --quiet HEAD 2>/dev/null; then
    echo -e "${YELLOW}[AVISO]${NC} Há mudanças locais não commitadas"
    echo -e "${YELLOW}[INFO]${NC} A fazer stash das mudanças..."
    git stash
fi

echo -e "${YELLOW}[2/6]${NC} A fazer git pull..."
git pull origin main 2>/dev/null || git pull origin master 2>/dev/null || {
    echo -e "${RED}[ERRO]${NC} git pull falhou"
    echo -e "${YELLOW}[INFO]${NC} Verifique a conexão e o repositório remoto"
    exit 1
}

echo -e "${GREEN}[OK]${NC} Código atualizado"

echo -e "${YELLOW}[3/6]${NC} A atualizar dependências Composer..."
echo -e "${BLUE}[INFO]${NC} Versão PHP atual: $(php -v | head -n 1)"

if [ -f "composer.json" ]; then
    export COMPOSER_ALLOW_SUPERUSER=1
    export COMPOSER_MEMORY_LIMIT=-1

    if grep -q '"mongodb/mongodb": "1.15.0"' composer.json; then
        echo -e "${YELLOW}[INFO]${NC} Ajustando mongodb/mongodb para compatibilidade com ext-mongodb 2.x..."
        cp composer.json composer.json.backup.$(date +%Y%m%d_%H%M%S)
        sed -i 's/"mongodb\/mongodb": "1.15.0"/"mongodb\/mongodb": "^1.15 || ^2.0"/' composer.json
    fi

    composer install --no-dev --optimize-autoloader --no-interaction || {
        echo -e "${YELLOW}[INFO]${NC} Composer install falhou. A tentar fallback..."
        rm -f composer.lock
        rm -rf vendor
        composer update mongodb/mongodb --with-all-dependencies --no-dev --optimize-autoloader --no-interaction || {
            echo -e "${RED}[ERRO]${NC} Falha ao resolver dependências com Composer"
            exit 1
        }
    }

    php -r "require '$PROJECT_DIR/vendor/autoload.php';" >/dev/null 2>&1 || {
        echo -e "${RED}[ERRO]${NC} Vendor/autoload inválido após atualização"
        exit 1
    }

    echo -e "${GREEN}[OK]${NC} Dependências atualizadas"
else
    echo -e "${YELLOW}[AVISO]${NC} composer.json não encontrado"
fi

echo -e "${YELLOW}[4/6]${NC} A configurar permissões..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/logs 2>/dev/null || true
chmod -R 775 $PROJECT_DIR/storage 2>/dev/null || true
echo -e "${GREEN}[OK]${NC} Permissões configuradas"

echo -e "${YELLOW}[5/6]${NC} A testar configuração Nginx..."
nginx -t && echo -e "${GREEN}[OK]${NC} Nginx config válida" || {
    echo -e "${RED}[ERRO]${NC} Configuração Nginx inválida"
    exit 1
}

echo -e "${YELLOW}[6/6]${NC} A reiniciar serviços..."
systemctl restart php8.3-fpm && echo -e "${GREEN}[OK]${NC} PHP-FPM reiniciado" || echo -e "${YELLOW}[AVISO]${NC} PHP-FPM restart falhou"
systemctl reload nginx && echo -e "${GREEN}[OK]${NC} Nginx recarregado" || echo -e "${YELLOW}[AVISO]${NC} Nginx reload falhou"

echo ""
echo -e "${YELLOW}[EXTRA]${NC} A verificar se API responde..."
sleep 2

if curl -s http://localhost/api/health 2>/dev/null | grep -q "operational"; then
    echo ""
    echo "=========================================="
    echo -e "${GREEN}✓ ATUALIZAÇÃO CONCLUÍDA COM SUCESSO!${NC}"
    echo "=========================================="
    echo ""
    echo -e "${GREEN}API está operacional!${NC}"
else
    echo ""
    echo "=========================================="
    echo -e "${YELLOW}⚠ ATUALIZAÇÃO CONCLUÍDA COM AVISOS${NC}"
    echo "=========================================="
    echo ""
    echo -e "${YELLOW}[AVISO]${NC} API não respondeu ao teste"
    echo -e "${YELLOW}[INFO]${NC} Verifique os logs:"
    echo "  tail -f /var/log/nginx/apisagem_error.log"
    echo "  tail -f $PROJECT_DIR/logs/app.log"
fi

echo ""
echo -e "${BLUE}Últimos commits:${NC}"
git log --oneline -3 2>/dev/null || echo "  (não foi possível mostrar commits)"
echo ""

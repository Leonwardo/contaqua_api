#!/bin/bash

set -u

PROJECT_DIR="/var/www/apisagem"
PUBLIC_IP="${1:-213.63.236.100}"
NGINX_SITE="/etc/nginx/sites-available/apisagem"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

step() {
    echo -e "${YELLOW}[$1]${NC} $2"
}

ok() {
    echo -e "${GREEN}[OK]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[AVISO]${NC} $1"
}

err() {
    echo -e "${RED}[ERRO]${NC} $1"
}

echo "=========================================="
echo -e "${BLUE}  Setup Contaqua API (Ubuntu 24.04)${NC}"
echo "=========================================="
echo ""

if [[ ${EUID} -ne 0 ]]; then
    err "Execute como root: sudo bash setup_api.sh"
    exit 1
fi

if [[ ! -d "${PROJECT_DIR}" ]]; then
    warn "Diretório ${PROJECT_DIR} não existe. Vou criar."
    mkdir -p "${PROJECT_DIR}"
fi

cd "${PROJECT_DIR}" || {
    err "Não consegui aceder a ${PROJECT_DIR}"
    exit 1
}

if [[ ! -f "${PROJECT_DIR}/public/index.php" ]]; then
    err "${PROJECT_DIR}/public/index.php não existe. Confirme se o repositório está correto em ${PROJECT_DIR}."
    exit 1
fi

step "1/10" "Parar Apache para libertar porta 80"
systemctl disable --now apache2 2>/dev/null || warn "Apache não estava ativo"
ok "Porta 80 preparada para Nginx"

step "2/10" "Corrigir conflito de repositórios MongoDB no APT"
if grep -Rqs "repo.mongodb.org/apt/ubuntu" /etc/apt/sources.list /etc/apt/sources.list.d/*.list 2>/dev/null; then
    rm -f /etc/apt/sources.list.d/*mongodb*.list 2>/dev/null || true
    ok "Entradas MongoDB duplicadas removidas"
else
    ok "Sem conflito MongoDB no APT"
fi

step "3/10" "Atualizar pacotes e instalar Nginx/PHP 8.3/Composer"
apt update -y || {
    err "apt update falhou"
    exit 1
}

apt install -y nginx git curl unzip ca-certificates composer || {
    err "Falha ao instalar pacotes base"
    exit 1
}

apt install -y php8.3-fpm php8.3-cli php8.3-common php8.3-mbstring php8.3-curl php8.3-bcmath php8.3-xml php8.3-zip php8.3-gd || {
    err "Falha ao instalar PHP 8.3"
    exit 1
}

if ! php -m | grep -qi '^mongodb$'; then
    apt install -y php8.3-mongodb || apt install -y php-mongodb || true
fi

if ! php -m | grep -qi '^mongodb$'; then
    err "Extensão mongodb não está carregada no PHP"
    exit 1
fi

ok "Nginx, PHP 8.3 e Composer instalados"

step "4/10" "Configurar Nginx para abrir ${PROJECT_DIR}/public"
cat > "${NGINX_SITE}" << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${PUBLIC_IP} _;

    root ${PROJECT_DIR}/public;
    index index.php index.html;

    access_log /var/log/nginx/apisagem_access.log;
    error_log /var/log/nginx/apisagem_error.log warn;

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

rm -f /etc/nginx/sites-enabled/default
ln -sfn "${NGINX_SITE}" /etc/nginx/sites-enabled/apisagem
nginx -t || {
    err "Configuração Nginx inválida"
    exit 1
}
ok "Nginx configurado"

step "5/10" "Garantir estrutura de diretórios"
mkdir -p "${PROJECT_DIR}/logs" "${PROJECT_DIR}/storage/uploads" "${PROJECT_DIR}/storage/logs"
ok "Diretórios logs/storage prontos"

step "6/10" "Corrigir dependência mongodb/mongodb para PHP ext-mongodb 2.x"
if [[ -f "composer.json" ]] && grep -q '"mongodb/mongodb": "1.15.0"' composer.json; then
    cp composer.json "composer.json.backup.$(date +%Y%m%d_%H%M%S)"
    sed -i 's/"mongodb\/mongodb": "1.15.0"/"mongodb\/mongodb": "^1.15 || ^2.0"/' composer.json
    ok "composer.json atualizado para aceitar mongodb 2.x"
else
    ok "composer.json já está compatível"
fi

step "7/10" "Reinstalar vendor limpo (resolve Class not found / erro 500)"
if [[ ! -f "composer.json" ]]; then
    err "composer.json não encontrado em ${PROJECT_DIR}"
    exit 1
fi

rm -rf vendor

export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_MEMORY_LIMIT=-1

if ! composer install --no-dev --optimize-autoloader --no-interaction; then
    warn "composer install falhou. A tentar update dirigido do mongodb..."
    rm -f composer.lock
    rm -rf vendor
    composer update mongodb/mongodb --with-all-dependencies --no-dev --optimize-autoloader --no-interaction || {
        err "Composer não conseguiu resolver dependências"
        exit 1
    }
fi

php -r "require '${PROJECT_DIR}/vendor/autoload.php'; echo 'autoload-ok';" >/dev/null 2>&1 || {
    err "Autoload do Composer continua inválido"
    exit 1
}
ok "Dependências PHP instaladas"

step "8/10" "Configurar .env com APP_URL correto"
if [[ -f "${PROJECT_DIR}/.env" ]]; then
    if grep -q '^APP_URL=' "${PROJECT_DIR}/.env"; then
        sed -i "s|^APP_URL=.*|APP_URL=http://${PUBLIC_IP}|" "${PROJECT_DIR}/.env"
    else
        echo "APP_URL=http://${PUBLIC_IP}" >> "${PROJECT_DIR}/.env"
    fi
    ok ".env atualizado"
else
    cat > "${PROJECT_DIR}/.env" << ENVEOF
APP_NAME=ContaquaAPI
APP_ENV=production
APP_DEBUG=false
APP_URL=http://${PUBLIC_IP}
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
ENVEOF
    ok ".env criado"
fi

chmod 640 "${PROJECT_DIR}/.env"

step "9/10" "Aplicar permissões e reiniciar serviços"
chown -R www-data:www-data "${PROJECT_DIR}"
chmod -R 755 "${PROJECT_DIR}"
chmod -R 775 "${PROJECT_DIR}/logs" "${PROJECT_DIR}/storage"

systemctl enable php8.3-fpm nginx
systemctl restart php8.3-fpm
systemctl restart nginx
ok "Serviços reiniciados"

step "10/10" "Teste final da API"
HTTP_STATUS="$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/api/health || echo 000)"

echo ""
if [[ "${HTTP_STATUS}" == "200" ]]; then
    echo -e "${GREEN}✓ API ONLINE${NC}"
    echo "URL:        http://${PUBLIC_IP}"
    echo "Health:     http://${PUBLIC_IP}/api/health"
    echo "Admin:      http://${PUBLIC_IP}/admin"
else
    echo -e "${RED}✗ API ainda com erro (HTTP ${HTTP_STATUS})${NC}"
    echo ""
    echo "--- /var/log/nginx/apisagem_error.log ---"
    tail -n 25 /var/log/nginx/apisagem_error.log 2>/dev/null || true
    echo ""
    echo "--- /var/log/php8.3-fpm.log ---"
    tail -n 25 /var/log/php8.3-fpm.log 2>/dev/null || true
    echo ""
    echo "--- ${PROJECT_DIR}/logs/app.log ---"
    tail -n 25 "${PROJECT_DIR}/logs/app.log" 2>/dev/null || true
    exit 1
fi
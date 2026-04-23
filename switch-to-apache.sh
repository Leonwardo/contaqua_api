#!/bin/bash
# ============================================
# Contaqua API - Mudar para Apache
# ============================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/apisagem"
SERVER_IP=$(hostname -I | awk '{print $1}')

echo "=========================================="
echo -e "${BLUE}  Mudar de Nginx para Apache${NC}"
echo "=========================================="
echo ""

# 1. PARAR NGINX
echo -e "${YELLOW}[1/5]${NC} A parar Nginx..."
systemctl stop nginx
systemctl disable nginx
echo -e "${GREEN}[OK]${NC} Nginx parado"

# 2. INSTALAR APACHE (se não estiver)
echo -e "${YELLOW}[2/5]${NC} A verificar/instalar Apache..."
if ! command -v apache2 &> /dev/null; then
    apt update
    apt install -y apache2 libapache2-mod-php8.3
fi

# 3. CONFIGURAR APACHE
echo -e "${YELLOW}[3/5]${NC} A configurar Apache..."

# Ativar módulos necessários
a2enmod rewrite
a2enmod php8.3

# Criar configuração do site
cat > /etc/apache2/sites-available/apisagem.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/apisagem/public
    
    <Directory /var/www/apisagem/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/apisagem_error.log
    CustomLog ${APACHE_LOG_DIR}/apisagem_access.log combined
    
    # PHP-FPM (se usar mod_proxy)
    # ProxyPassMatch ^/(.*\.php)$ unix:/var/run/php/php8.3-fpm.sock|fcgi://localhost/var/www/apisagem/public/$1
</VirtualHost>
EOF

# Desativar site default e ativar o nosso
a2dissite 000-default 2>/dev/null || true
a2ensite apisagem

echo -e "${GREEN}[OK]${NC} Apache configurado"

# 4. CRIAR .HTACCESS
echo -e "${YELLOW}[4/5]${NC} A criar .htaccess..."
cat > $PROJECT_DIR/public/.htaccess << 'EOF'
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
EOF

chown www-data:www-data $PROJECT_DIR/public/.htaccess
echo -e "${GREEN}[OK]${NC} .htaccess criado"

# 5. REINICIAR APACHE
echo -e "${YELLOW}[5/5]${NC} A iniciar Apache..."
systemctl restart apache2
sleep 2

# Verificar se está a correr
if systemctl is-active --quiet apache2; then
    echo -e "${GREEN}[OK]${NC} Apache iniciado"
else
    echo -e "${RED}[ERRO]${NC} Apache falhou ao iniciar"
    systemctl status apache2 --no-pager | head -10
fi

# TESTAR
echo ""
echo "=========================================="
echo -e "${YELLOW}[TESTE]${NC}"
echo "=========================================="
sleep 2

HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null || echo "000")

echo ""
if [ "$HTTP_STATUS" = "200" ]; then
    echo -e "${GREEN}✓✓✓ SITE ONLINE COM APACHE! ✓✓✓${NC}"
    echo ""
    echo -e "${GREEN}Aceda:${NC}"
    echo "  http://$SERVER_IP"
    echo "  http://$SERVER_IP/api/health"
    echo "  http://$SERVER_IP/admin"
else
    echo -e "${RED}✗ Erro (HTTP $HTTP_STATUS)${NC}"
    echo ""
    echo -e "${YELLOW}Ver logs Apache:${NC}"
    echo "  tail -20 /var/log/apache2/apisagem_error.log"
    echo "  tail -20 /var/log/apache2/error.log"
fi

echo ""
echo -e "${BLUE}Comandos úteis:${NC}"
echo "  systemctl status apache2"
echo "  systemctl restart apache2"
echo "  tail -f /var/log/apache2/apisagem_error.log"
echo ""

# Voltar para Nginx (se quiser)
echo -e "${YELLOW}Para voltar para Nginx:${NC}"
echo "  bash /var/www/apisagem/switch-to-nginx.sh"
echo ""

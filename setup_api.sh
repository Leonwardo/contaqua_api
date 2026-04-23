#!/bin/bash

echo "🚀 A iniciar setup da API Contaqua..."

# parar serviços
echo "🛑 Parar Apache e Nginx..."
sudo systemctl stop apache2
sudo systemctl disable apache2
sudo systemctl stop nginx

# instalar nginx e php-fpm
echo "📦 Instalar Nginx + PHP 8.3..."
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-cli php8.3-mbstring php8.3-curl php8.3-bcmath php8.3-xml php8.3-zip unzip

# garantir que php-fpm está ativo
sudo systemctl enable php8.3-fpm
sudo systemctl start php8.3-fpm

# limpar config antiga nginx
echo "🧹 Limpar configs antigas..."
sudo rm -f /etc/nginx/sites-enabled/default
sudo rm -f /etc/nginx/sites-available/apisagem

# criar config nginx
echo "⚙️ Criar config Nginx..."
sudo bash -c 'cat > /etc/nginx/sites-available/apisagem <<EOF
server {
    listen 80;
    server_name 213.63.236.100;

    root /var/www/apisagem/public;
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
EOF'

# ativar site
sudo ln -s /etc/nginx/sites-available/apisagem /etc/nginx/sites-enabled/

# permissões
echo "🔐 Ajustar permissões..."
sudo chown -R www-data:www-data /var/www/apisagem
sudo chmod -R 755 /var/www/apisagem
sudo chmod -R 775 /var/www/apisagem/storage /var/www/apisagem/logs 2>/dev/null

# testar nginx
echo "🧪 Testar configuração..."
sudo nginx -t

# reiniciar nginx
echo "🔄 Reiniciar Nginx..."
sudo systemctl restart nginx
sudo systemctl enable nginx

# testar conexão PHP-FPM
echo "🧪 Verificar PHP-FPM..."
sudo systemctl status php8.3-fpm --no-pager | head -3

echo ""
echo "✅ Setup concluído!"
echo "👉 Testa no browser: http://213.63.236.100"
echo "👉 API Health: http://213.63.236.100/api/health"
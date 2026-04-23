#!/bin/bash

echo "🚀 A iniciar setup da API Contaqua..."

# parar serviços
echo "🛑 Parar Apache e Nginx..."
sudo systemctl stop apache2
sudo systemctl disable apache2
sudo systemctl stop nginx

# instalar nginx e php-fpm
echo "📦 Instalar Nginx + PHP 8.2..."
sudo apt update
sudo apt install -y nginx php8.2-fpm php8.2-cli php8.2-mbstring php8.2-curl php8.2-bcmath php8.2-xml php8.2-zip unzip

# garantir que php-fpm está ativo
sudo systemctl enable php8.2-fpm
sudo systemctl start php8.2-fpm

# limpar config antiga nginx
echo "🧹 Limpar configs antigas..."
sudo rm -f /etc/nginx/sites-enabled/default
sudo rm -f /etc/nginx/sites-available/apisagem

# criar config nginx
echo "⚙️ Criar config Nginx..."
sudo bash -c 'cat > /etc/nginx/sites-available/apisagem <<EOF
server {
    listen 80;
    server_name _;

    root /var/www/apisagem;
    index index.php index.html;

    access_log /var/log/nginx/apisagem_access.log;
    error_log /var/log/nginx/apisagem_error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
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

echo "✅ Setup concluído!"
echo "👉 Testa no browser: http://IP_DO_SERVIDOR"
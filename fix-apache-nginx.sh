#!/bin/bash
# ============================================
# Contaqua API - Corrigir conflito Apache/Nginx
# ============================================
# Para o Apache e inicia o Nginx

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo -e "${BLUE}  Corrigir Apache vs Nginx${NC}"
echo "=========================================="
echo ""

# Verificar se é root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}[ERRO]${NC} Execute como root: sudo bash fix-apache-nginx.sh"
   exit 1
fi

SERVER_IP=$(hostname -I | awk '{print $1}')

echo -e "${YELLOW}[1/6]${NC} A verificar o que está na porta 80..."
echo -e "${BLUE}[INFO]${NC} Processos na porta 80:"
ss -tlnp | grep :80 || echo "  (nenhum encontrado)"
echo ""

# Ver quem está a usar a porta 80
echo -e "${YELLOW}[2/6]${NC} A identificar processos na porta 80..."
PORT_80_PID=$(lsof -t -i:80 2>/dev/null || ss -tlnp | grep :80 | grep -oP 'pid=\K[0-9]+' | head -1)
if [ -n "$PORT_80_PID" ]; then
    echo -e "${YELLOW}[INFO]${NC} Processo na porta 80 (PID: $PORT_80_PID):"
    ps -p $PORT_80_PID -o comm= 2>/dev/null || echo "  (não identificado)"
    echo -e "${YELLOW}[INFO]${NC} A matar processo $PORT_80_PID..."
    kill -9 $PORT_80_PID 2>/dev/null || true
    sleep 2
else
    echo -e "${GREEN}[OK]${NC} Nenhum processo identificado na porta 80"
fi

# Verificar se Apache está a correr
echo -e "${YELLOW}[3/6]${NC} A parar Apache..."
if systemctl is-active --quiet apache2; then
    echo -e "${YELLOW}[INFO]${NC} Apache2 está ATIVO - a parar..."
    systemctl stop apache2
    systemctl disable apache2
    echo -e "${GREEN}[OK]${NC} Apache2 parado e desativado"
elif systemctl is-active --quiet httpd; then
    echo -e "${YELLOW}[INFO]${NC} Apache (httpd) está ATIVO - a parar..."
    systemctl stop httpd
    systemctl disable httpd
    echo -e "${GREEN}[OK]${NC} Apache parado e desativado"
else
    echo -e "${GREEN}[OK]${NC} Apache não está a correr"
fi

# Tentar matar qualquer processo na porta 80
echo -e "${YELLOW}[4/6]${NC} A libertar porta 80 (forçado)..."
fuser -k 80/tcp 2>/dev/null || true
sleep 2

# Verificar se porta ficou livre
echo -e "${YELLOW}[5/6]${NC} A verificar se porta 80 está livre..."
if ss -tlnp | grep -q :80; then
    echo -e "${RED}[ERRO]${NC} Porta 80 ainda ocupada!"
    echo -e "${YELLOW}[INFO]${NC} Processos restantes:"
    ss -tlnp | grep :80
    lsof -i :80 2>/dev/null || true
else
    echo -e "${GREEN}[OK]${NC} Porta 80 está livre"
fi

# Verificar configuração Nginx
echo -e "${YELLOW}[6/6]${NC} A verificar configuração Nginx..."
if nginx -t 2>&1 | grep -q "successful\|ok"; then
    echo -e "${GREEN}[OK]${NC} Configuração Nginx válida"
else
    echo -e "${RED}[ERRO]${NC} Configuração Nginx inválida!"
    echo -e "${YELLOW}[INFO]${NC} Erro:"
    nginx -t
fi

# Iniciar Nginx
echo -e "${YELLOW}[INFO]${NC} A iniciar Nginx..."
systemctl start nginx
sleep 3

# Verificar se iniciou
if systemctl is-active --quiet nginx; then
    echo -e "${GREEN}[OK]${NC} Nginx iniciado com sucesso"
else
    echo -e "${RED}[ERRO]${NC} Nginx falhou ao iniciar!"
    echo ""
    echo -e "${YELLOW}[LOGS DO ERRO]:${NC}"
    journalctl -xeu nginx.service --no-pager | tail -20
    echo ""
    echo -e "${YELLOW}[Tentar comando alternativo]:${NC}"
    echo "  nginx  (inicia diretamente sem systemctl)"
fi

# Verificar status
echo ""
echo "=========================================="
echo -e "${BLUE}Status dos serviços:${NC}"
echo "=========================================="
echo ""
echo -e "${YELLOW}Nginx:${NC}"
systemctl status nginx --no-pager -l | head -10
echo ""

# Verificar porta 80
echo -e "${YELLOW}Porta 80:${NC}"
ss -tlnp | grep :80 || echo "  (nenhum serviço na porta 80)"
echo ""

# Testar site
echo -e "${YELLOW}[TESTE]${NC} A testar site..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/api/health 2>/dev/null || echo "000")

echo ""
if [ "$HTTP_STATUS" = "200" ]; then
    echo -e "${GREEN}✓ SITE ONLINE!${NC}"
    echo ""
    echo -e "${GREEN}Aceda ao site:${NC}"
    echo "  http://$SERVER_IP"
    echo "  http://$SERVER_IP/api/health"
    echo "  http://$SERVER_IP/admin"
else
    echo -e "${RED}✗ Site não responde (HTTP $HTTP_STATUS)${NC}"
    echo ""
    echo -e "${YELLOW}Verificar logs:${NC}"
    echo "  tail -20 /var/log/nginx/apisagem_error.log"
fi

echo ""
echo -e "${BLUE}Comandos úteis:${NC}"
echo "  systemctl status nginx"
echo "  systemctl restart nginx"
echo "  tail -f /var/log/nginx/apisagem_error.log"
echo "  journalctl -xeu nginx.service"
echo ""
echo -e "${BLUE}Se continuar a falhar, tenta:${NC}"
echo "  1. nginx -t  (verificar config)"
echo "  2. nginx      (iniciar manualmente)"
echo "  3. reboot     (reiniciar servidor)"
echo ""

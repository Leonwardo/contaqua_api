<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController
{
    public function index(Request $request, Response $response): Response
    {
        $html = '<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contaqua</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            min-height: 100vh;
            width: 100%;
            background: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            position: relative;
        }
        
        /* Imagem de direitos autorais no centro */
        .copyright-img {
            max-width: 300px;
            max-height: 200px;
            opacity: 0.5;
            pointer-events: none;
            user-select: none;
        }
        
        /* Botão invisível - canto inferior direito */
        .hidden-trigger {
            position: fixed;
            bottom: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: transparent;
            border: none;
            cursor: pointer;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .hidden-trigger:hover {
            opacity: 0.1;
            background: rgba(0,0,0,0.05);
        }
        
        /* Modal minimalista e elegante */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        
        .modal-box {
            background: #fff;
            padding: 60px 50px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            border-radius: 0;
            box-shadow: 0 25px 80px rgba(0,0,0,0.15);
            transform: translateY(20px);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-overlay.active .modal-box {
            transform: translateY(0);
        }
        
        .modal-icon {
            font-size: 32px;
            margin-bottom: 25px;
            opacity: 0.8;
        }
        
        .modal-title {
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 40px;
        }
        
        .modal-input {
            width: 100%;
            padding: 18px 0;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            font-size: 16px;
            text-align: center;
            letter-spacing: 2px;
            background: transparent;
            transition: border-color 0.3s;
            margin-bottom: 40px;
        }
        .modal-input:focus {
            outline: none;
            border-bottom-color: #333;
        }
        .modal-input::placeholder {
            color: #ccc;
            letter-spacing: 1px;
        }
        
        .modal-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .btn {
            padding: 16px 40px;
            border: none;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #1a1a1a;
            color: #fff;
        }
        .btn-primary:hover {
            background: #333;
            transform: translateY(-1px);
        }
        
        .btn-text {
            background: transparent;
            color: #999;
        }
        .btn-text:hover {
            color: #666;
        }
        
        .error {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 20px;
            letter-spacing: 1px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .error.show {
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- SVG de direitos autorais minimalista -->
    <svg class="copyright-img" viewBox="0 0 200 60" xmlns="http://www.w3.org/2000/svg">
        <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" 
              font-family="Georgia, serif" font-size="14" fill="#ccc" letter-spacing="3">
            © 2026 Contaqua
        </text>
        <text x="50%" y="75%" dominant-baseline="middle" text-anchor="middle" 
              font-family="Arial, sans-serif" font-size="9" fill="#ddd" letter-spacing="2">
            ALL RIGHTS RESERVED
        </text>
        <line x1="40" y1="35" x2="160" y2="35" stroke="#eee" stroke-width="0.5"/>
    </svg>
    
    <!-- Trigger invisível canto inferior direito -->
    <div class="hidden-trigger" onclick="openModal()" title=""></div>
    
    <!-- Modal minimalista -->
    <div class="modal-overlay" id="modal" onclick="closeOnOverlay(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div class="modal-icon">◼</div>
            <div class="modal-title">Acesso Restrito</div>
            <input type="password" 
                   class="modal-input" 
                   id="tokenInput" 
                   placeholder="Insira o código de acesso"
                   autocomplete="off">
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="verify()">Entrar</button>
                <button class="btn btn-text" onclick="closeModal()">Cancelar</button>
            </div>
            <div class="error" id="errorMsg">Código inválido</div>
        </div>
    </div>
    
    <script>
        const TOKEN = "ContaquaAdminSecure2026";
        
        function openModal() {
            document.getElementById("modal").classList.add("active");
            setTimeout(() => document.getElementById("tokenInput").focus(), 100);
        }
        
        function closeModal() {
            document.getElementById("modal").classList.remove("active");
            document.getElementById("tokenInput").value = "";
            document.getElementById("errorMsg").classList.remove("show");
        }
        
        function closeOnOverlay(e) {
            if (e.target === e.currentTarget) closeModal();
        }
        
        function verify() {
            const input = document.getElementById("tokenInput").value.trim();
            if (input === TOKEN) {
                // Redirecionar para o portal com o token na URL
                window.location.href = "admin/portal?token=" + encodeURIComponent(input);
            } else {
                document.getElementById("errorMsg").classList.add("show");
                document.getElementById("tokenInput").style.borderBottomColor = "#e74c3c";
                setTimeout(() => {
                    document.getElementById("tokenInput").style.borderBottomColor = "#e0e0e0";
                }, 1000);
            }
        }
        
        // Enter para confirmar
        document.getElementById("tokenInput").addEventListener("keypress", function(e) {
            if (e.key === "Enter") verify();
        });
        
        // ESC para fechar
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closeModal();
        });
    </script>
</body>
</html>';
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}

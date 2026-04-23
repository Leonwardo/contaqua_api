<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PortalController
{
    private string $adminToken;
    
    public function __construct(array $adminConfig)
    {
        $this->adminToken = $adminConfig['token'] ?? 'ContaquaAdminSecure2026';
    }
    
    public function portal(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $token = $params['token'] ?? '';
        
        if ($token !== $this->adminToken) {
            return $response->withHeader('Location', '.')->withStatus(302);
        }
        
        $html = '<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Administrativo - Contaqua</title>
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
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            width: 100%;
            text-align: center;
        }
        .header {
            margin-bottom: 30px;
        }
        .header-icon {
            font-size: 24px;
            margin-bottom: 20px;
            opacity: 0.6;
        }
        .header h1 {
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 11px;
            color: #bbb;
            letter-spacing: 2px;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }
        .card {
            background: #fff;
            padding: 50px 40px;
            text-decoration: none;
            display: block;
            border: 1px solid #f0f0f0;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }
        .card:hover {
            border-color: #ddd;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            transform: translateY(-4px);
        }
        .card::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: #1a1a1a;
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        .card:hover::after {
            width: 60px;
        }
        .card-icon {
            font-size: 32px;
            margin-bottom: 25px;
            opacity: 0.8;
        }
        .card h2 {
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #333;
            margin-bottom: 15px;
        }
        .card p {
            font-size: 13px;
            color: #999;
            line-height: 1.8;
            letter-spacing: 0.5px;
        }
        .back-link {
            margin-top: 40px;
        }
        .back-link a {
            font-size: 11px;
            color: #ccc;
            text-decoration: none;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: color 0.3s;
        }
        .back-link a:hover {
            color: #999;
        }
        @media (max-width: 600px) {
            .cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .card {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-icon">◼</div>
            <h1>Portal Administrativo</h1>
            <p>Selecione uma opção</p>
        </div>
        
        <div class="cards">
            <a href="dashboard?token=' . $token . '" class="card">
                <div class="card-icon">◼</div>
                <h2>Painel Administrativo</h2>
                <p>Gerenciamento completo do sistema</p>
            </a>
            
            <a href="status?token=' . $token . '" class="card">
                <div class="card-icon">◼</div>
                <h2>Status da API</h2>
                <p>Monitoramento e métricas</p>
            </a>
        </div>
        
        <div class="back-link">
            <a href="..">← Voltar</a>
        </div>
    </div>
</body>
</html>';
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}

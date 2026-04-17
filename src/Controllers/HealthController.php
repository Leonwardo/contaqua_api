<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\MongoConnection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController
{
    public function __construct(
        private MongoConnection $mongoConnection,
        private array $appConfig
    ) {
    }
    
    /**
     * Health check endpoint
     * GET /api/server or /api/health
     */
    public function health(Request $request, Response $response): Response
    {
        $mongoStatus = $this->mongoConnection->ping() ? 'ok' : 'error';
        
        $data = [
            'ok' => true,
            'status' => 'operational',
            'mongodb' => $mongoStatus,
            'version' => '2.0.0',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'app' => $this->appConfig['name'] ?? 'ContaquaAPI',
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Simple server status (legacy compatibility)
     * GET /api/server
     */
    public function server(Request $request, Response $response): Response
    {
        return $this->health($request, $response);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\FirmwareService;
use App\Services\UserAuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Controller for firmware/OTA endpoints
 * Compatible with MeterApp expectations
 */
class FirmwareController
{
    public function __construct(
        private FirmwareService $firmwareService,
        private UserAuthService $userAuthService,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * List available firmware updates
     * POST /api/firmware
     * 
     * Expected body: {token: string, hw_version?: string, meter_type?: string}
     * Returns: JSON array of firmware entries
     */
    public function listFirmware(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $token = (string) ($data['token'] ?? '');
        $hwVersion = (string) ($data['hw_version'] ?? $data['hwversion'] ?? '');
        $meterType = (string) ($data['meter_type'] ?? $data['metertype'] ?? '');
        
        // Validate token
        if ($token === '' || $this->userAuthService->validateToken($token) === null) {
            $response->getBody()->write(json_encode([]));
            return $response->withStatus(401);
        }
        
        $firmwares = $this->firmwareService->getAvailableFirmware(
            $hwVersion !== '' ? $hwVersion : null,
            $meterType !== '' ? $meterType : null
        );
        
        // Format for MeterApp compatibility
        $result = [];
        foreach ($firmwares as $firmware) {
            $id = (string) ($firmware->_id ?? '');
            $result[] = [
                'id' => $id,
                'version' => $firmware->version,
                'name' => $firmware->name,
                'description' => $firmware->description ?? '',
                'path' => '/api/firmware/' . $id,
                'size' => $firmware->file_size,
                'hw_version' => $firmware->hw_version ?? '',
                'meter_type' => $firmware->meter_type ?? '',
            ];
        }
        
        $response->getBody()->write(json_encode($result, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Download firmware file
     * GET /api/firmware/{id}
     * 
     * Returns: Binary firmware data
     */
    public function downloadFirmware(Request $request, Response $response, string $id): Response
    {
        $binary = $this->firmwareService->getFirmwareBinary($id);
        
        if ($binary === null) {
            $response->getBody()->write('Firmware not found');
            return $response->withStatus(404);
        }
        
        $firmware = $this->firmwareService->getById($id);
        $filename = $firmware ? $firmware->name : 'firmware.bin';
        
        $response->getBody()->write($binary);
        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($binary));
    }
    
    /**
     * Legacy firmware list endpoint
     * POST /api/firmware/check or /api/firmware/update
     * 
     * Alternative endpoint for checking updates
     */
    public function checkUpdate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $token = (string) ($data['token'] ?? '');
        $currentVersion = (string) ($data['current_version'] ?? $data['version'] ?? '');
        
        // Validate token
        if ($token === '' || $this->userAuthService->validateToken($token) === null) {
            $response->getBody()->write(json_encode([
                'update_available' => false,
                'error' => 'unauthorized'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
        
        // Get latest active firmware
        $firmwares = $this->firmwareService->getAvailableFirmware();
        
        if (empty($firmwares)) {
            $response->getBody()->write(json_encode([
                'update_available' => false,
                'message' => 'no firmware available'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // Get the most recent firmware
        $latest = $firmwares[0];
        $hasUpdate = $currentVersion === '' || version_compare($latest->version, $currentVersion, '>');
        
        $result = [
            'update_available' => $hasUpdate,
            'current_version' => $currentVersion,
            'latest_version' => $latest->version,
        ];
        
        if ($hasUpdate) {
            $id = (string) ($latest->_id ?? '');
            $result['firmware'] = [
                'id' => $id,
                'version' => $latest->version,
                'name' => $latest->name,
                'description' => $latest->description ?? '',
                'download_url' => '/api/firmware/' . $id,
                'size' => $latest->file_size,
            ];
        }
        
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

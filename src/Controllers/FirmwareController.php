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
     * Meter firmware update check (POST /api/firmware)
     *
     * Compatible with MeterApp RemoteDownloader.checkForUpdate:
     *   body: token, revision (current), type (app|mid|inst), deveui
     *   200  -> text parsed by:
     *             resultMsg.replaceAll(" ","").replaceAll("[","").replaceAll("]","").replaceAll("\"","");
     *             content = resultMsg.split(",")
     *             version = content[0]
     *             securityCtx = content[1]
     *             fileId = content[2]   (appended to /api/firmware/{id})
     *             size = Integer.parseInt(content[3])
     *   non-200 -> HttpsStatusException thrown in client (== "no update")
     *
     * So we MUST return exactly a 4-element tuple:
     *   ["<version>","<securityCtx>","<id>","<size>"]
     */
    public function listFirmware(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $token = (string) ($data['token'] ?? '');
        $currentRevision = (string) ($data['revision'] ?? '');
        $type = strtolower((string) ($data['type'] ?? ''));
        $deveui = strtoupper((string) ($data['deveui'] ?? ''));
        
        if ($token === '' || $this->userAuthService->validateToken($token) === null) {
            $response->getBody()->write('unauthorized');
            return $response->withStatus(401);
        }
        
        // Use meter_type as the "revision type" selector (app/mid/inst).
        $firmwares = $this->firmwareService->getAvailableFirmware(
            null,
            $type !== '' ? $type : null
        );
        
        if (empty($firmwares)) {
            // Client interprets non-200 as "no update available"
            $response->getBody()->write('no update');
            return $response->withStatus(204);
        }
        
        $latest = $firmwares[0];
        
        // If current revision is already >= latest, respond 204
        if ($currentRevision !== '' && version_compare($latest->version, $currentRevision, '<=')) {
            $response->getBody()->write('up to date');
            return $response->withStatus(204);
        }
        
        $id = (string) ($latest->_id ?? '');
        $securityCtx = $latest->hw_version ?: 'NONE'; // reused as security context
        
        // Sanitize values so the client's strip-and-split survives
        $sanitize = fn(string $v) => str_replace([',', ' ', '[', ']', '"'], '_', $v);
        $tuple = [
            $sanitize($latest->version),
            $sanitize($securityCtx),
            $sanitize($id),
            (string) $latest->file_size,
        ];
        
        $response->getBody()->write(json_encode($tuple, JSON_UNESCAPED_SLASHES));
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
     * MeterApp self-updater check (POST /api/android)
     *
     * Compatible with HttpsAndroidUpdater.checkForUpdate:
     *   body: version=<current>&appId=<pkg>
     *   200  -> text parsed by stripping spaces,[,],"
     *           content = [targetVersion, appId, fileName, size]
     *   non-200 -> HttpsStatusException (== no update)
     *
     * Download URL used by the app: <serverUrl>/api/android/{fileName}
     */
    public function listAndroid(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $currentVersion = (int) ($data['version'] ?? 0);
        $appId = (string) ($data['appId'] ?? '');
        
        // App-updater is unauthenticated in the client; we keep it open
        // but gated by appId presence.
        if ($appId === '') {
            return $response->withStatus(400);
        }
        
        // TODO: source this from a dedicated collection. For now we signal
        // "no update available" which is the common steady state.
        $response->getBody()->write('no update');
        return $response->withStatus(204);
    }
    
    /**
     * MeterApp self-updater download (GET /api/android/{file})
     */
    public function downloadAndroid(Request $request, Response $response, string $file): Response
    {
        $response->getBody()->write('not found');
        return $response->withStatus(404);
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

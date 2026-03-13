<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\MongoCollections;
use App\Http\Request;
use App\Http\Response;
use App\Services\UserAuthService;

final class FirmwareController
{
    public function __construct(
        private MongoCollections $collections,
        private UserAuthService $userAuthService
    )
    {
    }

    // Legacy endpoint expected by app: POST /api/firmware
    public function checkUpdate(Request $request): Response
    {
        $deveui = (string) $request->input('deveui', '');
        $type = strtoupper((string) $request->input('type', 'APP'));
        $token = (string) $request->input('token', '');

        if ($deveui === '' || $token === '') {
            return Response::text('missing deveui', 400);
        }

        if ($this->userAuthService->validateToken($token) === null) {
            return Response::text('unauthorized', 401);
        }

        $document = $this->collections->meterConfig()->findOne([
            'type' => 'firmware',
            'firmware_type' => $type,
            '$or' => [
                ['allowed_meters' => '*'],
                ['allowed_meters' => $deveui],
                ['allowed_meters' => ['$in' => [$deveui]]],
            ],
        ]);

        if (!is_array($document)) {
            return Response::text('no update', 404);
        }

        $id = (string) ($document['_id'] ?? '');
        $version = (string) ($document['version'] ?? '');
        $securityCtx = (string) ($document['security_ctx'] ?? '');
        $size = (int) ($document['size'] ?? 0);

        $payload = json_encode([$version, $securityCtx, $id, $size], JSON_UNESCAPED_SLASHES);
        return Response::text($payload ?: '[]', 200);
    }

    // Legacy endpoint expected by app: GET /api/firmware/{id}
    public function download(array $params): Response
    {
        $id = (string) ($params['id'] ?? '');
        if ($id === '') {
            return Response::text('Not found', 404);
        }

        try {
            $objectId = new \MongoDB\BSON\ObjectId($id);
        } catch (\Throwable) {
            return Response::text('Not found', 404);
        }

        $document = $this->collections->meterConfig()->findOne(['_id' => $objectId, 'type' => 'firmware']);
        if (!is_array($document)) {
            return Response::text('Not found', 404);
        }

        if (isset($document['file_content_base64']) && is_string($document['file_content_base64'])) {
            $binary = base64_decode($document['file_content_base64'], true);
            if ($binary !== false) {
                return new Response(200, $binary, ['Content-Type' => 'application/octet-stream']);
            }
        }

        if (isset($document['file_content']) && is_string($document['file_content'])) {
            return new Response(200, $document['file_content'], ['Content-Type' => 'application/octet-stream']);
        }

        return Response::text('Not found', 404);
    }
}

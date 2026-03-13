<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\MongoCollections;
use App\Http\Request;
use App\Http\Response;
use App\Services\MeterAuthService;
use App\Services\MeterConfigService;
use App\Services\MeterSessionService;
use App\Services\UserAuthService;

final class MeterController
{
    public function __construct(
        private MeterAuthService $meterAuthService,
        private MeterConfigService $meterConfigService,
        private MeterSessionService $meterSessionService,
        private MongoCollections $collections,
        private UserAuthService $userAuthService
    ) {
    }

    public function authorize(Request $request): Response
    {
        $authKey = (string) $request->input('authkey', '');
        $user = (string) $request->input('user', '');
        $meterId = (string) $request->input('meterid', '');

        if ($authKey === '' || $user === '' || $meterId === '') {
            return Response::json(['ok' => false, 'error' => 'authkey, user, meterid are required'], 400);
        }

        $document = $this->meterAuthService->authorize($authKey, $user, $meterId);
        if ($document === null) {
            return Response::json(['ok' => false, 'authorized' => false], 403);
        }

        return Response::json(['ok' => true, 'authorized' => true, 'record' => $document]);
    }

    // Legacy endpoint expected by current app: POST /api/meter_token
    public function meterToken(Request $request): Response
    {
        $userToken = (string) $request->input('token', '');
        $challenge = (string) $request->input('challenge', '');
        $deveui = (string) $request->input('deveui', '');

        if ($userToken === '' || $challenge === '' || $deveui === '') {
            return Response::text('unable to retrieve token', 401);
        }

        $token = $this->meterAuthService->generateMeterToken($userToken, $challenge, $deveui);
        if ($token === null) {
            return Response::text('unable to retrieve token', 401);
        }

        return Response::text('"' . $token . '"', 200);
    }

    public function config(Request $request): Response
    {
        $user = (string) $request->input('user', '');
        $meterId = (string) $request->input('meterid', $request->input('deveui', ''));

        if ($user === '' || $meterId === '') {
            return Response::json(['ok' => false, 'error' => 'user and meterid are required'], 400);
        }

        $configs = $this->meterConfigService->getAllowedConfigs($user, $meterId);

        return Response::json([
            'ok' => true,
            'count' => count($configs),
            'configs' => $configs,
        ]);
    }

    // Legacy endpoint expected by current app: POST /api/config
    public function configLegacy(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        $deveui = (string) $request->input('deveui', '');
        $category = strtolower((string) $request->input('category', 'general'));

        if ($token === '' || $deveui === '') {
            return Response::text('[]', 401);
        }

        $userDocument = $this->userAuthService->validateToken($token);
        if ($userDocument === null) {
            return Response::text('[]', 401);
        }

        $user = (string) ($userDocument['user'] ?? $userDocument['username'] ?? '');

        $configs = $this->meterConfigService->getAllowedConfigs($user, $deveui);
        $result = [];
        foreach ($configs as $config) {
            if (isset($config['category']) && strtolower((string) $config['category']) !== $category) {
                continue;
            }

            $id = (string) ($config['_id'] ?? '');
            $name = (string) ($config['name'] ?? 'config_' . $id);
            $result[] = [
                'id' => $id,
                'name' => $name,
                'path' => '/api/config/' . $id,
                'description' => (string) ($config['description'] ?? ''),
            ];
        }

        return Response::text(json_encode($result, JSON_UNESCAPED_SLASHES) ?: '[]', 200);
    }

    public function configFile(array $params): Response
    {
        $id = $params['id'] ?? '';
        if ($id === '') {
            return Response::text('Not found', 404);
        }

        try {
            $objectId = new \MongoDB\BSON\ObjectId($id);
        } catch (\Throwable) {
            return Response::text('Not found', 404);
        }

        $document = $this->collections->meterConfig()->findOne(['_id' => $objectId]);
        if (!is_array($document)) {
            return Response::text('Not found', 404);
        }

        $content = (string) ($document['file_content'] ?? $document['content'] ?? '');
        if ($content === '') {
            return Response::text('No content', 404);
        }

        return new Response(200, $content, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function session(Request $request): Response
    {
        $payload = $request->body;
        if ($payload === []) {
            return Response::json(['ok' => false, 'error' => 'payload is required'], 400);
        }

        $token = (string) ($payload['token'] ?? $request->input('token', ''));
        if ($token === '' || $this->userAuthService->validateToken($token) === null) {
            return Response::json(['ok' => false, 'error' => 'invalid token'], 401);
        }

        try {
            $stored = $this->meterSessionService->storeSession($payload);
        } catch (\InvalidArgumentException $exception) {
            return Response::json(['ok' => false, 'error' => $exception->getMessage()], 400);
        }

        return Response::json(['ok' => true] + $stored, 201);
    }

    public function meterDiagList(Request $request): Response
    {
        $deveui = (string) $request->input('deveui', '');
        $token = (string) $request->input('token', '');
        if ($deveui === '' || $token === '' || $this->userAuthService->validateToken($token) === null) {
            return Response::text('', 401);
        }

        $document = $this->collections->meterConfig()->findOne(['diagnostic_for' => $deveui]);
        if (!is_array($document)) {
            return Response::text('', 200);
        }

        return Response::text((string) ($document['diagnostic_script'] ?? ''), 200);
    }

    public function meterDiagReport(Request $request): Response
    {
        $token = (string) $request->input('token', '');
        if ($token === '' || $this->userAuthService->validateToken($token) === null) {
            return Response::text('unauthorized', 401);
        }
        return Response::text('ok', 200);
    }
}

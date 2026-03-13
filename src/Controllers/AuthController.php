<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\UserAuthService;

final class AuthController
{
    public function __construct(private UserAuthService $userAuthService)
    {
    }

    public function validate(Request $request): Response
    {
        $token = (string) ($request->input('token', $request->bearerToken() ?? ''));

        if ($token === '') {
            return Response::json(['ok' => false, 'error' => 'token is required'], 400);
        }

        $document = $this->userAuthService->validateToken($token);
        if ($document === null) {
            return Response::json(['ok' => false, 'authenticated' => false], 401);
        }

        return Response::json([
            'ok' => true,
            'authenticated' => true,
            'user' => $document,
        ]);
    }

    // Legacy endpoint expected by current app: POST /api/user_token
    public function userToken(Request $request): Response
    {
        $user = (string) $request->input('user', '');
        $pass = (string) $request->input('pass', '');

        if ($user === '' || $pass === '') {
            return Response::text('Unable to authenticate user', 201);
        }

        $token = $this->userAuthService->loginAndGetToken($user, $pass);
        if ($token === null) {
            return Response::text('Unable to authenticate user', 201);
        }

        return Response::text($token, 200);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

final class AppUpdateController
{
    // Legacy updater endpoint expected by app: POST /api/android
    public function check(Request $request): Response
    {
        // Return 204 when no app update is configured.
        return Response::text('[]', 204);
    }

    // Legacy updater endpoint expected by app: GET /api/android/{id}
    public function download(array $params): Response
    {
        return Response::text('Not found', 404);
    }
}

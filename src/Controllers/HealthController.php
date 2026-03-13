<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;

final class HealthController
{
    public function server(): Response
    {
        return Response::text('OK', 200);
    }

    public function health(): Response
    {
        return Response::json([
            'ok' => true,
            'service' => 'meter-api',
            'time' => gmdate('c'),
        ]);
    }
}

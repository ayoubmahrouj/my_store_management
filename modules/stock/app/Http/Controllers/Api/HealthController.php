<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

final class HealthController
{
    public function index(Request $request): Response
    {
        return Response::json([
            'success' => true,
            'data'    => [
                'status'  => 'ok',
                'app'     => Config::get('app.name'),
                'version' => '0.1.0-sprint0',
                'php'     => PHP_VERSION,
                'time'    => date(DATE_ATOM),
            ],
            'meta'   => [],
            'errors' => [],
        ]);
    }
}

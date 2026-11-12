<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Persistence\PdoProductRepository;

final class StockApiController
{
    public function index(Request $request): Response
    {
        $repo = new PdoProductRepository();

        return Response::json([
            'success' => true,
            'data'    => [
                'summary'   => $repo->stats(),
                'locations' => $repo->stockByLocation(),
            ],
            'meta'    => ['generated_at' => date(DATE_ATOM)],
            'errors'  => [],
        ]);
    }
}

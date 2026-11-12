<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Entities\StockMovement;
use App\Infrastructure\Persistence\PdoDocumentRepository;

final class MovementApiController
{
    public function index(Request $request): Response
    {
        $items = array_map(
            static fn (StockMovement $movement): array => $movement->toArray(),
            (new PdoDocumentRepository())->movements([
                'q'           => trim((string) $request->query('q', '')),
                'type'        => trim((string) $request->query('type', '')),
                'location_id' => (int) $request->query('site', 0),
                'date_from'   => trim((string) $request->query('du', '')),
                'date_to'     => trim((string) $request->query('au', '')),
                'limit'       => (int) $request->query('limit', 100),
            ])
        );

        return Response::json([
            'success' => true,
            'data'    => $items,
            'meta'    => ['count' => count($items)],
            'errors'  => [],
        ]);
    }
}

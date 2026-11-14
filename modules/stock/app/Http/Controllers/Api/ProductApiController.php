<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use Gos\Domain\Entities\Produit;
use App\Infrastructure\Persistence\PdoProductRepository;

final class ProductApiController
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));
        $repo = new PdoProductRepository();
        $items = array_map(
            static fn (Produit $product): array => $product->toArray(),
            $repo->search($term !== '' ? $term : null)
        );

        return Response::json([
            'success' => true,
            'data'    => $items,
            'meta'    => ['count' => count($items)],
            'errors'  => [],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $product = (new PdoProductRepository())->find((int) $id);
        if ($product === null) {
            return Response::json([
                'success' => false,
                'message' => t('Produit introuvable.'),
                'errors'  => [],
            ], 404);
        }

        return Response::json([
            'success' => true,
            'data'    => $product->toArray(),
            'meta'    => [],
            'errors'  => [],
        ]);
    }
}

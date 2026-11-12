<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Domain\Entities\StockDocument;
use App\Infrastructure\Persistence\PdoDocumentRepository;

final class DocumentApiController
{
    public function index(Request $request): Response
    {
        $type = trim((string) $request->query('type', 'entree'));
        if (!in_array($type, ['entree', 'sortie', 'transfert', 'inventaire'], true)) {
            $type = 'entree';
        }

        $filters = [
            'q'           => trim((string) $request->query('q', '')),
            'status'      => trim((string) $request->query('statut', '')),
            'location_id' => (int) $request->query('site', 0),
            'date_from'   => trim((string) $request->query('du', '')),
            'date_to'     => trim((string) $request->query('au', '')),
        ];

        $items = array_map(
            static fn (StockDocument $doc): array => $doc->toArray(),
            (new PdoDocumentRepository())->search($type, $filters)
        );

        return Response::json([
            'success' => true,
            'data'    => $items,
            'meta'    => ['count' => count($items), 'type' => $type],
            'errors'  => [],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $document = (new PdoDocumentRepository())->find((int) $id);
        if ($document === null) {
            return Response::json(['success' => false, 'message' => 'Document introuvable.', 'errors' => []], 404);
        }

        return Response::json([
            'success' => true,
            'data'    => $document->toArray(),
            'meta'    => [],
            'errors'  => [],
        ]);
    }
}

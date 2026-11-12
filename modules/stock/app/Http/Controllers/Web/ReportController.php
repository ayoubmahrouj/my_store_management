<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Entities\Brand;
use App\Domain\Entities\Category;
use App\Domain\Entities\Location;
use App\Domain\Entities\Product;
use App\Domain\Entities\Supplier;
use App\Infrastructure\Export\ExportDocument;
use App\Infrastructure\Persistence\PdoDocumentRepository;
use App\Infrastructure\Persistence\PdoProductRepository;
use App\Infrastructure\Persistence\PdoReferenceRepository;

final class ReportController
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $productRepo = new PdoProductRepository();
        $docRepo = new PdoDocumentRepository();
        $refs = new PdoReferenceRepository();
        $locations = $refs->locations(true);

        $products = $productRepo->search(
            $filters['q'] !== '' ? $filters['q'] : null,
            null,
            true,
            $this->productFilters($filters)
        );

        $lowStock = array_values(array_filter($products, static fn (Product $p): bool => $p->isLowStock() || $p->isOutOfStock()));
        usort($lowStock, static fn (Product $a, Product $b): int => $a->quantity() <=> $b->quantity());

        $byLocation = $productRepo->stockByLocation($filters);
        $totalValue = 0.0;
        $totalQuantity = 0.0;
        $lowCount = 0;
        foreach ($products as $product) {
            $totalValue += $product->stockValue();
            $totalQuantity += $product->quantity();
            if ($product->isLowStock() || $product->isOutOfStock()) {
                $lowCount++;
            }
        }

        return View::render('reports/index', [
            'title'      => 'Rapports',
            'stats'      => [
                'total_value'    => $totalValue,
                'total_quantity' => $totalQuantity,
                'products'       => count($products),
                'low_stock'      => $lowCount,
                'locations'      => count($locations),
            ],
            'products'   => $products,
            'lowStock'   => array_slice($lowStock, 0, 12),
            'categories' => $productRepo->stockByCategory($filters),
            'byLocation' => $byLocation,
            'locations'  => $locations,
            'brands'     => $refs->brands(true),
            'categoriesList' => $refs->categories(true),
            'movements'  => $docRepo->movements($this->movementFilters($filters, 300)),
            'filters'    => $filters,
        ]);
    }

    public function export(Request $request): Response
    {
        $kind = (string) $request->query('export', 'stock');
        $format = (string) $request->query('format', 'xls');
        $filters = $this->filters($request);

        if ($kind === 'movements') {
            return $this->exportMovements($filters, $format);
        }

        return $this->exportStock($filters, $format);
    }

    /** @param array<string, mixed> $filters */
    private function exportStock(array $filters, string $format): Response
    {
        $products = (new PdoProductRepository())->search(
            $filters['q'] !== '' ? $filters['q'] : null,
            null,
            true,
            $this->productFilters($filters)
        );

        $rows = [];
        $totalQty = 0.0;
        $totalValue = 0.0;
        $low = 0;
        foreach ($products as $product) {
            $totalQty += $product->quantity();
            $totalValue += $product->stockValue();
            $status = $product->isOutOfStock() ? 'Rupture' : ($product->isLowStock() ? 'Seuil bas' : 'En stock');
            if ($product->isLowStock() || $product->isOutOfStock()) {
                $low++;
            }
            $rows[] = [
                $product->sku(),
                $product->designation(),
                $product->brand(),
                $product->category(),
                $product->unit(),
                $status,
                $product->quantity(),
                $product->purchaseCost(),
                $product->stockValue(),
                $product->minStock(),
                $product->positions(),
            ];
        }

        $document = new ExportDocument(
            filename: 'rapport-stock-' . date('Ymd-Hi'),
            title: 'Rapport de stock',
            subtitle: 'État consolidé du stock, valorisé au coût moyen pondéré et filtré selon vos critères.',
            meta: $this->filterMeta($filters),
            summary: [
                ['label' => 'Produits', 'value' => (string) count($products)],
                ['label' => 'Quantité totale', 'value' => qty($totalQty)],
                ['label' => 'Capital stock', 'value' => money($totalValue)],
                ['label' => 'Alertes seuil', 'value' => (string) $low],
            ],
            columns: [
                ['label' => 'SKU', 'width' => 0.9],
                ['label' => 'Désignation', 'width' => 2.4],
                ['label' => 'Marque', 'width' => 1.1],
                ['label' => 'Catégorie', 'width' => 1.2],
                ['label' => 'Unité', 'width' => 0.6, 'align' => 'center'],
                ['label' => 'Statut', 'width' => 0.8, 'align' => 'center'],
                ['label' => 'Quantité', 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => 'CUMP (' . currency() . ')', 'width' => 0.9, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Valeur stock (' . currency() . ')', 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Seuil min', 'width' => 0.7, 'align' => 'right', 'type' => 'qty'],
                ['label' => 'Positionnement', 'width' => 1.8],
            ],
            rows: $rows,
            totals: [null, null, null, null, null, null, $totalQty, null, $totalValue, null, null],
            landscape: true,
            currency: currency(),
        );

        return export_response($format, $document);
    }

    /** @param array<string, mixed> $filters */
    private function exportMovements(array $filters, string $format): Response
    {
        $movements = (new PdoDocumentRepository())->movements($this->movementFilters($filters, 1000));

        $rows = [];
        $totalQty = 0.0;
        $totalValue = 0.0;
        $inQty = 0.0;
        $outQty = 0.0;
        foreach ($movements as $movement) {
            $totalQty += $movement->qty();
            $totalValue += $movement->value();
            if ($movement->isIn()) {
                $inQty += $movement->qty();
            } else {
                $outQty += $movement->qty();
            }

            $rows[] = [
                date('d/m/Y H:i', strtotime($movement->occurredAt())),
                $movement->numero() ?? '',
                doc_type_label($movement->type()),
                $movement->product(),
                $movement->sku(),
                $movement->location(),
                $movement->isIn() ? 'Entrée' : 'Sortie',
                $movement->qty(),
                $movement->unitCost(),
                $movement->value(),
                $movement->userName(),
            ];
        }

        $document = new ExportDocument(
            filename: 'rapport-mouvements-' . date('Ymd-Hi'),
            title: 'Rapport des mouvements de stock',
            subtitle: 'Historique détaillé et valorisé des entrées, sorties, transferts et inventaires.',
            meta: $this->movementMeta($filters),
            summary: [
                ['label' => 'Mouvements', 'value' => (string) count($movements)],
                ['label' => 'Quantité entrée', 'value' => qty($inQty)],
                ['label' => 'Quantité sortie', 'value' => qty($outQty)],
                ['label' => 'Valeur totale', 'value' => money($totalValue)],
            ],
            columns: [
                ['label' => 'Date', 'width' => 1.0],
                ['label' => 'Document', 'width' => 1.0],
                ['label' => 'Type', 'width' => 0.8],
                ['label' => 'Produit', 'width' => 2.2],
                ['label' => 'SKU', 'width' => 0.9],
                ['label' => 'Site', 'width' => 1.3],
                ['label' => 'Sens', 'width' => 0.6, 'align' => 'center'],
                ['label' => 'Quantité', 'width' => 0.75, 'align' => 'right', 'type' => 'qty'],
                ['label' => 'Coût unitaire (' . currency() . ')', 'width' => 0.95, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Valeur (' . currency() . ')', 'width' => 0.95, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Opérateur', 'width' => 1.0],
            ],
            rows: $rows,
            totals: [null, null, null, null, null, null, null, $totalQty, null, $totalValue, null],
            landscape: true,
            currency: currency(),
        );

        return export_response($format, $document);
    }

    /** @return array{q: string, movement_type: string, direction: string, location_id: int, category_id: int, brand_id: int, status: string, date_from: string, date_to: string} */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('statut', '');
        if (!in_array($status, ['', 'out', 'low', 'ok'], true)) {
            $status = '';
        }
        $direction = (string) $request->query('sens', '');
        if (!in_array($direction, ['', 'in', 'out'], true)) {
            $direction = '';
        }

        return [
            'q'             => trim((string) $request->query('q', '')),
            'movement_type' => trim((string) $request->query('type', '')),
            'direction'     => $direction,
            'location_id'   => (int) $request->query('site', 0),
            'category_id'   => (int) $request->query('categorie', 0),
            'brand_id'      => (int) $request->query('marque', 0),
            'status'        => $status,
            'date_from'     => trim((string) $request->query('du', '')),
            'date_to'       => trim((string) $request->query('au', '')),
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */
    private function productFilters(array $filters): array
    {
        return [
            'category_id' => $filters['category_id'],
            'brand_id'    => $filters['brand_id'],
            'location_id' => $filters['location_id'],
            'status'      => $filters['status'],
            'sort'        => 'name',
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */
    private function movementFilters(array $filters, int $limit): array
    {
        return [
            'q'           => $filters['q'],
            'type'        => $filters['movement_type'],
            'direction'   => $filters['direction'],
            'location_id' => $filters['location_id'],
            'category_id' => $filters['category_id'],
            'brand_id'    => $filters['brand_id'],
            'date_from'   => $filters['date_from'],
            'date_to'     => $filters['date_to'],
            'limit'       => $limit,
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, string>
     */
    private function filterMeta(array $filters): array
    {
        $refs = new PdoReferenceRepository();
        $meta = ['Site : ' . $this->locationLabel($refs->locations(true), (int) $filters['location_id'])];
        if ((int) $filters['category_id'] > 0) {
            $meta[] = 'Catégorie : ' . $this->nameLabel($refs->categories(true), (int) $filters['category_id']);
        }
        if ((int) $filters['brand_id'] > 0) {
            $meta[] = 'Marque : ' . $this->nameLabel($refs->brands(true), (int) $filters['brand_id']);
        }
        if ((string) $filters['status'] !== '') {
            $meta[] = 'Statut : ' . match ((string) $filters['status']) {
                'out' => 'Rupture',
                'low' => 'Seuil bas',
                'ok' => 'En stock',
                default => 'Tous',
            };
        }
        if ((string) $filters['q'] !== '') {
            $meta[] = 'Recherche : « ' . (string) $filters['q'] . ' »';
        }

        return $meta;
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, string>
     */
    private function movementMeta(array $filters): array
    {
        $meta = $this->filterMeta($filters);
        if ((string) $filters['movement_type'] !== '') {
            $meta[] = 'Type : ' . doc_type_label((string) $filters['movement_type']);
        }
        if ((string) $filters['direction'] !== '') {
            $meta[] = 'Sens : ' . ((string) $filters['direction'] === 'in' ? 'Entrées' : 'Sorties');
        }
        if ((string) $filters['date_from'] !== '' || (string) $filters['date_to'] !== '') {
            $meta[] = 'Période : ' . $this->dateRangeLabel((string) $filters['date_from'], (string) $filters['date_to']);
        }

        return $meta;
    }

    /** @param array<int, Location> $items */
    private function locationLabel(array $items, int $id): string
    {
        if ($id <= 0) {
            return 'Tous les sites';
        }
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item->name();
            }
        }

        return 'Site #' . $id;
    }

    /** @param array<int, Category>|array<int, Brand> $items */
    private function nameLabel(array $items, int $id): string
    {
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item->name();
            }
        }

        return '#' . $id;
    }

    private function dateRangeLabel(string $from, string $to): string
    {
        if ($from !== '' && $to !== '') {
            return 'du ' . date('d/m/Y', strtotime($from)) . ' au ' . date('d/m/Y', strtotime($to));
        }
        if ($from !== '') {
            return 'depuis le ' . date('d/m/Y', strtotime($from));
        }

        return 'jusqu’au ' . date('d/m/Y', strtotime($to));
    }
}

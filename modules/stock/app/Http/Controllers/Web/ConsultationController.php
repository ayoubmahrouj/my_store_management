<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Infrastructure\Export\ExportDocument;
use App\Infrastructure\Persistence\PdoProductRepository;
use App\Infrastructure\Persistence\PdoReferenceRepository;

final class ConsultationController
{
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $repo = new PdoProductRepository();

        $totals = $repo->consultationSummary($filters);
        $pages = max(1, (int) ceil($totals['total'] / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $pages);
        $rows = $repo->consultation($filters + [
            'limit'  => self::PER_PAGE,
            'offset' => ($page - 1) * self::PER_PAGE,
        ]);

        return View::render('consultation/index', [
            'title'      => 'Consultation',
            'rows'       => $rows,
            'page'       => $page,
            'pages'      => $pages,
            'filters'    => $filters,
            'locations'  => (new PdoReferenceRepository())->locations(true),
            'categories' => (new PdoReferenceRepository())->categories(true),
            'brands'     => (new PdoReferenceRepository())->brands(true),
            'units'      => (new PdoReferenceRepository())->units(true),
            'summary'    => [
                'products'  => $totals['total'],
                'quantity'  => $totals['quantity'],
                'value'     => $totals['value'],
                'saleValue' => $totals['sale_value'],
                'margin'    => $totals['margin'],
                'alerts'    => $totals['alerts'],
            ],
            'canManage'  => in_array(auth_user()?->roleCode(), ['admin', 'responsable'], true),
        ]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $rows = (new PdoProductRepository())->consultation($filters);

        $quantity = 0.0;
        $value = 0.0;
        $saleValue = 0.0;
        $margin = 0.0;
        $alerts = 0;
        $dataRows = [];
        $totalQty = 0.0;
        $totalCost = 0.0;
        $totalSale = 0.0;
        $totalMargin = 0.0;

        foreach ($rows as $row) {
            $rowQty = (float) $row['quantity'];
            $rowCost = (float) $row['stock_value'];
            $rowSale = (float) $row['sale_value'];
            $rowMargin = (float) $row['margin_value'];
            $status = $this->statusLabel($rowQty, (float) $row['min_stock']);

            $quantity += $rowQty;
            $value += $rowCost;
            $saleValue += $rowSale;
            $margin += $rowMargin;
            $totalQty += $rowQty;
            $totalCost += $rowCost;
            $totalSale += $rowSale;
            $totalMargin += $rowMargin;
            if ($rowQty <= (float) $row['min_stock']) {
                $alerts++;
            }

            $dataRows[] = [
                $row['sku'],
                $row['designation'],
                $row['brand'],
                $row['category'],
                $row['unit'],
                $rowQty,
                (float) $row['purchase_cost'],
                $rowCost,
                (float) $row['sale_price'],
                $rowSale,
                $rowMargin,
                (float) $row['min_stock'],
                $row['positions'],
                $status,
            ];
        }

        $references = [
            'locations'  => (new PdoReferenceRepository())->locations(true),
            'categories' => (new PdoReferenceRepository())->categories(true),
            'brands'     => (new PdoReferenceRepository())->brands(true),
            'units'      => (new PdoReferenceRepository())->units(true),
        ];

        $document = new ExportDocument(
            filename: 'consultation-stock-' . date('Ymd-Hi'),
            title: 'Consultation du stock',
            subtitle: 'Positionnement par produit, coût moyen pondéré (CUMP) et valorisation du stock.',
            meta: $this->meta($filters, $references),
            summary: [
                ['label' => 'Produits', 'value' => (string) count($rows)],
                ['label' => 'Quantité totale', 'value' => qty($quantity)],
                ['label' => 'Capital stock', 'value' => money($value)],
                ['label' => 'Valeur potentielle', 'value' => money($saleValue)],
                ['label' => 'Alertes seuil', 'value' => (string) $alerts],
            ],
            columns: [
                ['label' => 'SKU', 'width' => 0.9, 'type' => 'text'],
                ['label' => 'Désignation', 'width' => 2.4, 'type' => 'text'],
                ['label' => 'Marque', 'width' => 1.0, 'type' => 'text'],
                ['label' => 'Catégorie', 'width' => 1.1, 'type' => 'text'],
                ['label' => 'Unité', 'width' => 0.6, 'align' => 'center', 'type' => 'text'],
                ['label' => 'Quantité', 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => 'CUMP (' . currency() . ')', 'width' => 0.9, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Valeur stock (' . currency() . ')', 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Prix vente (' . currency() . ')', 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Valeur potentielle (' . currency() . ')', 'width' => 1.15, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Marge potentielle (' . currency() . ')', 'width' => 1.15, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Seuil min', 'width' => 0.7, 'align' => 'right', 'type' => 'qty'],
                ['label' => 'Positionnement', 'width' => 1.7, 'type' => 'text'],
                ['label' => 'Statut', 'width' => 0.8, 'align' => 'center', 'type' => 'text'],
            ],
            rows: $dataRows,
            totals: [
                null, null, null, null, null,
                $totalQty,
                null,
                $totalCost,
                null,
                $totalSale,
                $totalMargin,
                null, null, null,
            ],
            landscape: true,
            currency: currency(),
        );

        return export_response((string) $request->query('format', 'xls'), $document);
    }

    /** @return array{q: string, location_id: int, category_id: int, brand_id: int, unit_id: int, status: string, min_qty: string, max_qty: string, min_value: string, max_value: string, min_margin: string, max_margin: string, sort: string} */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('statut', '');
        if (!in_array($status, ['', 'out', 'low', 'ok'], true)) {
            $status = '';
        }

        $sort = (string) $request->query('tri', 'value');
        if (!in_array($sort, ['value', 'value_asc', 'qty_desc', 'qty_asc', 'name', 'low', 'margin'], true)) {
            $sort = 'value';
        }

        return [
            'q'           => trim((string) $request->query('q', '')),
            'location_id' => (int) $request->query('site', 0),
            'category_id' => (int) $request->query('categorie', 0),
            'brand_id'    => (int) $request->query('marque', 0),
            'unit_id'     => (int) $request->query('unite', 0),
            'status'      => $status,
            'min_qty'     => trim((string) $request->query('qte_min', '')),
            'max_qty'     => trim((string) $request->query('qte_max', '')),
            'min_value'   => trim((string) $request->query('valeur_min', '')),
            'max_value'   => trim((string) $request->query('valeur_max', '')),
            'min_margin'  => trim((string) $request->query('marge_min', '')),
            'max_margin'  => trim((string) $request->query('marge_max', '')),
            'sort'        => $sort,
        ];
    }

    private function statusLabel(float $quantity, float $minStock): string
    {
        if ($quantity <= 0) {
            return 'Rupture';
        }
        if ($quantity <= $minStock) {
            return 'Seuil bas';
        }

        return 'En stock';
    }

    /** @param array<string, mixed> $filters
     *  @param array{locations: array<int, \App\Domain\Entities\Location>, categories: array<int, \App\Domain\Entities\Category>, brands: array<int, \Gos\Domain\Entities\Marque>, units: array<int, \App\Domain\Entities\Unit>} $references
     *  @return array<int, string>
     */
    private function meta(array $filters, array $references): array
    {
        $meta = [];
        $meta[] = 'Site : ' . $this->locationLabel($references['locations'], (int) $filters['location_id']);
        if ((int) $filters['category_id'] > 0) {
            $meta[] = 'Catégorie : ' . $this->namedLabel($references['categories'], (int) $filters['category_id']);
        }
        if ((int) $filters['brand_id'] > 0) {
            $meta[] = 'Marque : ' . $this->namedLabel($references['brands'], (int) $filters['brand_id']);
        }
        if ((int) $filters['unit_id'] > 0) {
            $meta[] = 'Unité : ' . $this->unitLabel($references['units'], (int) $filters['unit_id']);
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
        if ((string) $filters['min_qty'] !== '' || (string) $filters['max_qty'] !== '') {
            $meta[] = 'Quantité : ' . $this->rangeLabel((string) $filters['min_qty'], (string) $filters['max_qty']);
        }
        if ((string) $filters['min_value'] !== '' || (string) $filters['max_value'] !== '') {
            $meta[] = 'Valeur : ' . $this->rangeLabel((string) $filters['min_value'], (string) $filters['max_value'], currency());
        }
        if ((string) $filters['min_margin'] !== '' || (string) $filters['max_margin'] !== '') {
            $meta[] = 'Marge : ' . $this->rangeLabel((string) $filters['min_margin'], (string) $filters['max_margin'], currency());
        }
        $meta[] = 'Tri : ' . match ((string) $filters['sort']) {
            'name' => 'Désignation',
            'qty_desc' => 'Quantité décroissante',
            'qty_asc' => 'Quantité croissante',
            'value_asc' => 'Valeur croissante',
            'low' => 'Seuil le plus bas',
            'margin' => 'Marge potentielle',
            default => 'Valeur décroissante',
        };

        return $meta;
    }

    /** @param array<int, \App\Domain\Entities\Location> $items */
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

    /** @param array<int, \App\Domain\Entities\Category>|array<int, \Gos\Domain\Entities\Marque> $items */
    private function namedLabel(array $items, int $id): string
    {
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item->name();
            }
        }

        return '#' . $id;
    }

    /** @param array<int, \App\Domain\Entities\Unit> $items */
    private function unitLabel(array $items, int $id): string
    {
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item->code() . ' · ' . $item->label();
            }
        }

        return '#' . $id;
    }

    private function rangeLabel(string $min, string $max, string $suffix = ''): string
    {
        $suffix = $suffix !== '' ? ' ' . $suffix : '';
        if ($min !== '' && $max !== '') {
            return 'de ' . $min . ' à ' . $max . $suffix;
        }
        if ($min !== '') {
            return '≥ ' . $min . $suffix;
        }

        return '≤ ' . $max . $suffix;
    }
}

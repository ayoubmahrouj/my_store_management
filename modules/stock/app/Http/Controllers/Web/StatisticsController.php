<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Entities\Brand;
use App\Domain\Entities\Category;
use App\Domain\Entities\Location;
use App\Infrastructure\Export\ExportDocument;
use App\Infrastructure\Persistence\PdoDocumentRepository;
use App\Infrastructure\Persistence\PdoProductRepository;
use App\Infrastructure\Persistence\PdoReferenceRepository;
use App\Infrastructure\Persistence\PdoStatisticsRepository;

final class StatisticsController
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $refs = new PdoReferenceRepository();
        $locations = $refs->locations(true);
        $categories = $refs->categories(true);
        $brands = $refs->brands(true);

        $statistics = new PdoStatisticsRepository();
        $history = $statistics->history($filters['days'], $filters['location_id'], $filters['category_id'], $filters['brand_id']);
        $summary = $statistics->summary($filters['days'], $filters['location_id'], $filters['category_id'], $filters['brand_id']);
        $categoryBreakdown = $statistics->categoryBreakdown($filters['location_id'], $filters['category_id'], $filters['brand_id']);
        $topProducts = $statistics->topProducts(10, $filters['location_id'], $filters['category_id'], $filters['brand_id']);
        $byLocation = (new PdoProductRepository())->stockByLocation($filters);
        $movements = (new PdoDocumentRepository())->movements([
            'location_id' => $filters['location_id'],
            'category_id' => $filters['category_id'],
            'brand_id'    => $filters['brand_id'],
            'date_from'   => $history['start_date'],
            'date_to'     => $history['end_date'],
            'limit'       => 8,
        ]);

        return View::render('statistics/index', [
            'title'         => 'Statistiques',
            'days'          => $filters['days'],
            'locationId'    => $filters['location_id'],
            'locationName'  => $this->locationLabel($locations, $filters['location_id']),
            'categoryId'    => $filters['category_id'],
            'categoryName'  => $filters['category_id'] > 0 ? $this->nameLabel($categories, $filters['category_id']) : 'Toutes les catégories',
            'brandId'       => $filters['brand_id'],
            'brandName'     => $filters['brand_id'] > 0 ? $this->nameLabel($brands, $filters['brand_id']) : 'Toutes les marques',
            'locations'     => $locations,
            'categoriesList' => $categories,
            'brands'        => $brands,
            'filters'       => $filters,
            'summary'       => $summary,
            'history'       => $history,
            'categories'    => $categoryBreakdown,
            'topProducts'   => $topProducts,
            'byLocation'    => $byLocation,
            'movements'     => $movements,
        ]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $kind = (string) $request->query('export', 'history');
        $format = (string) $request->query('format', 'xls');

        $statistics = new PdoStatisticsRepository();
        $history = $statistics->history($filters['days'], $filters['location_id'], $filters['category_id'], $filters['brand_id']);
        $summary = $statistics->summary($filters['days'], $filters['location_id'], $filters['category_id'], $filters['brand_id']);
        $categories = $statistics->categoryBreakdown($filters['location_id'], $filters['category_id'], $filters['brand_id']);
        $topProducts = $statistics->topProducts(50, $filters['location_id'], $filters['category_id'], $filters['brand_id']);
        $byLocation = (new PdoProductRepository())->stockByLocation($filters);

        $refs = new PdoReferenceRepository();
        $meta = [
            'Période : ' . date('d/m/Y', strtotime($history['start_date'])) . ' → ' . date('d/m/Y', strtotime($history['end_date'])),
            'Site : ' . $this->locationLabel($refs->locations(true), $filters['location_id']),
        ];
        if ($filters['category_id'] > 0) {
            $meta[] = 'Catégorie : ' . $this->nameLabel($refs->categories(true), $filters['category_id']);
        }
        if ($filters['brand_id'] > 0) {
            $meta[] = 'Marque : ' . $this->nameLabel($refs->brands(true), $filters['brand_id']);
        }

        $summaryBoxes = [
            ['label' => 'Capital stock', 'value' => money($summary['total_value'])],
            ['label' => 'Quantité', 'value' => qty($summary['total_quantity'])],
            ['label' => 'Valeur potentielle', 'value' => money($summary['potential_value'])],
            ['label' => 'Marge potentielle', 'value' => money($summary['margin_value'])],
        ];

        if ($kind === 'sites') {
            $totalCapital = 0.0;
            foreach ($byLocation as $row) {
                $totalCapital += (float) $row['value'];
            }
            $rows = [];
            foreach ($byLocation as $row) {
                $share = $totalCapital > 0 ? ((float) $row['value'] / $totalCapital) * 100 : 0.0;
                $rows[] = [
                    $row['name'],
                    $row['type'] === 'depot' ? 'Dépôt' : 'Point de vente',
                    (float) $row['quantity'],
                    (float) $row['value'],
                    $share,
                ];
            }

            $document = new ExportDocument(
                filename: 'statistiques-sites-' . date('Ymd-Hi'),
                title: 'Capital par site',
                subtitle: 'Répartition du capital stock entre les dépôts et points de vente.',
                meta: $meta,
                summary: $summaryBoxes,
                columns: [
                    ['label' => 'Site', 'width' => 2.2],
                    ['label' => 'Type', 'width' => 1.0],
                    ['label' => 'Quantité', 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                    ['label' => 'Capital (' . currency() . ')', 'width' => 1.2, 'align' => 'right', 'type' => 'money'],
                    ['label' => 'Part (%)', 'width' => 0.8, 'align' => 'right', 'type' => 'number'],
                ],
                rows: $rows,
                totals: [null, null, null, $totalCapital, null],
                landscape: false,
                currency: currency(),
            );

            return export_response($format, $document);
        }

        if ($kind === 'categories') {
            $totalCapital = 0.0;
            foreach ($categories as $row) {
                $totalCapital += (float) $row['value'];
            }
            $rows = [];
            foreach ($categories as $row) {
                $share = $totalCapital > 0 ? ((float) $row['value'] / $totalCapital) * 100 : 0.0;
                $rows[] = [
                    $row['name'],
                    (int) $row['products'],
                    (float) $row['quantity'],
                    (float) $row['value'],
                    $share,
                ];
            }

            $document = new ExportDocument(
                filename: 'statistiques-categories-' . date('Ymd-Hi'),
                title: 'Capital par catégorie',
                subtitle: 'Répartition du capital stock et des quantités par famille de produits.',
                meta: $meta,
                summary: $summaryBoxes,
                columns: [
                    ['label' => 'Catégorie', 'width' => 2.2],
                    ['label' => 'Produits', 'width' => 0.8, 'align' => 'right', 'type' => 'number'],
                    ['label' => 'Quantité', 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                    ['label' => 'Capital (' . currency() . ')', 'width' => 1.2, 'align' => 'right', 'type' => 'money'],
                    ['label' => 'Part (%)', 'width' => 0.8, 'align' => 'right', 'type' => 'number'],
                ],
                rows: $rows,
                totals: [null, null, null, $totalCapital, null],
                landscape: false,
                currency: currency(),
            );

            return export_response($format, $document);
        }

        if ($kind === 'products') {
            $rows = [];
            $totalQty = 0.0;
            $totalValue = 0.0;
            $totalSale = 0.0;
            foreach ($topProducts as $row) {
                $totalQty += (float) $row['quantity'];
                $totalValue += (float) $row['value'];
                $totalSale += (float) $row['sale_value'];
                $rows[] = [
                    $row['sku'],
                    $row['designation'],
                    $row['category'],
                    $row['unit'],
                    (float) $row['quantity'],
                    (float) $row['value'],
                    (float) $row['sale_value'],
                    (float) $row['sale_value'] - (float) $row['value'],
                ];
            }

            $document = new ExportDocument(
                filename: 'statistiques-produits-' . date('Ymd-Hi'),
                title: 'Produits les plus valorisés',
                subtitle: 'Classement du stock par capital immobilisé, avec quantité et marge potentielle.',
                meta: $meta,
                summary: $summaryBoxes,
                columns: [
                    ['label' => 'SKU', 'width' => 0.9],
                    ['label' => 'Désignation', 'width' => 2.4],
                    ['label' => 'Catégorie', 'width' => 1.2],
                    ['label' => 'Unité', 'width' => 0.6, 'align' => 'center'],
                    ['label' => 'Quantité', 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                    ['label' => 'Capital (' . currency() . ')', 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                    ['label' => 'Valeur potentielle (' . currency() . ')', 'width' => 1.2, 'align' => 'right', 'type' => 'money'],
                    ['label' => 'Marge potentielle (' . currency() . ')', 'width' => 1.2, 'align' => 'right', 'type' => 'money'],
                ],
                rows: $rows,
                totals: [null, null, null, null, $totalQty, $totalValue, $totalSale, $totalSale - $totalValue],
                landscape: true,
                currency: currency(),
            );

            return export_response($format, $document);
        }

        $rows = [];
        foreach ($history['labels'] as $index => $label) {
            $rows[] = [
                $label,
                (float) ($history['capital'][$index] ?? 0),
                (float) ($history['quantity'][$index] ?? 0),
                (float) ($history['in_value'][$index] ?? 0),
                (float) ($history['out_value'][$index] ?? 0),
            ];
        }

        $document = new ExportDocument(
            filename: 'statistiques-capital-' . date('Ymd-Hi'),
            title: 'Évolution du capital et des mouvements',
            subtitle: 'Reconstitution quotidienne du capital valorisé au CUMP sur la période sélectionnée.',
            meta: $meta,
            summary: $summaryBoxes,
            columns: [
                ['label' => 'Date', 'width' => 0.8, 'align' => 'center'],
                ['label' => 'Capital (' . currency() . ')', 'width' => 1.2, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Quantité', 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => 'Entrées (' . currency() . ')', 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ['label' => 'Sorties (' . currency() . ')', 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
            ],
            rows: $rows,
            totals: [],
            landscape: false,
            currency: currency(),
        );

        return export_response($format, $document);
    }

    /** @return array{days: int, location_id: int, category_id: int, brand_id: int} */
    private function filters(Request $request): array
    {
        $days = (int) $request->query('periode', 30);
        if (!in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }

        return [
            'days'        => $days,
            'location_id' => (int) $request->query('site', 0),
            'category_id' => (int) $request->query('categorie', 0),
            'brand_id'    => (int) $request->query('marque', 0),
        ];
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
}

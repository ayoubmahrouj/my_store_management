<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Infrastructure\Persistence\PdoProductRepository;
use App\Infrastructure\Persistence\PdoReferenceRepository;
use RuntimeException;
use Throwable;

final class ProductController
{
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $repo = new PdoProductRepository();
        $refs = new PdoReferenceRepository();
        $term = $filters['q'] !== '' ? $filters['q'] : null;
        $activeOnly = !$filters['show_inactive'];

        $summary = $repo->searchSummary($term, null, $activeOnly, $filters);
        $pages = max(1, (int) ceil($summary['total'] / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $pages);

        $products = $repo->search($term, null, $activeOnly, $filters + [
            'limit'  => self::PER_PAGE,
            'offset' => ($page - 1) * self::PER_PAGE,
        ]);

        return View::render('products/index', [
            'title'        => t('Produits'),
            'term'         => $filters['q'],
            'products'     => $products,
            'summary'      => $summary,
            'page'         => $page,
            'pages'        => $pages,
            'showInactive' => $filters['show_inactive'],
            'filters'      => $filters,
            'locations'    => $refs->locations(true),
            'brands'       => $refs->brands(true),
            'categories'   => $refs->categories(true),
            'units'        => $refs->units(true),
            'canManage'    => Auth::isRole('admin', 'responsable'),
        ]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $repo = new PdoProductRepository();
        $refs = new PdoReferenceRepository();
        $products = $repo->search($filters['q'] !== '' ? $filters['q'] : null, null, !$filters['show_inactive'], $filters);

        $totalQty = 0.0;
        $totalValue = 0.0;
        $low = 0;
        $rows = [];
        foreach ($products as $product) {
            $status = !$product->isActive()
                ? t('Inactif')
                : ($product->isOutOfStock() ? t('Rupture') : ($product->isLowStock() ? t('Seuil bas') : t('En stock')));
            $totalQty += $product->quantity();
            $totalValue += $product->stockValue();
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
                $product->salePrice(),
                $product->minStock(),
                $product->positions(),
            ];
        }

        $document = new \App\Infrastructure\Export\ExportDocument(
            filename: 'produits-' . date('Ymd-Hi'),
            title: t('Référentiel produits & positionnement'),
            subtitle: t('Fiche consolidée des produits, du CUMP, des seuils et du stock par site.'),
            meta: $this->meta($filters, $refs),
            summary: [
                ['label' => t('Produits'), 'value' => (string) count($products)],
                ['label' => t('Quantité totale'), 'value' => qty($totalQty)],
                ['label' => t('Capital stock'), 'value' => money($totalValue)],
                ['label' => t('Alertes seuil'), 'value' => (string) $low],
            ],
            columns: [
                ['label' => 'SKU', 'width' => 0.9],
                ['label' => t('Désignation'), 'width' => 2.4],
                ['label' => t('Marque'), 'width' => 1.1],
                ['label' => t('Catégorie'), 'width' => 1.2],
                ['label' => t('Unité'), 'width' => 0.6, 'align' => 'center'],
                ['label' => t('Statut'), 'width' => 0.8, 'align' => 'center'],
                ['label' => t('Quantité'), 'width' => 0.8, 'align' => 'right', 'type' => 'qty'],
                ['label' => t('CUMP (:currency)', ['currency' => currency()]), 'width' => 0.9, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Valeur stock (:currency)', ['currency' => currency()]), 'width' => 1.1, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Prix vente (:currency)', ['currency' => currency()]), 'width' => 1.0, 'align' => 'right', 'type' => 'money'],
                ['label' => t('Seuil min'), 'width' => 0.7, 'align' => 'right', 'type' => 'qty'],
                ['label' => t('Positionnement'), 'width' => 1.9],
            ],
            rows: $rows,
            totals: [null, null, null, null, null, null, $totalQty, null, $totalValue, null, null, null],
            landscape: true,
            currency: currency(),
        );

        return export_response((string) $request->query('format', 'xls'), $document);
    }

    /** @return array{q: string, show_inactive: bool, brand_id: int, category_id: int, unit_id: int, location_id: int, status: string, min_qty: string, max_qty: string, min_value: string, max_value: string, sort: string} */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('statut', '');
        if (!in_array($status, ['', 'out', 'low', 'ok'], true)) {
            $status = '';
        }
        $sort = (string) $request->query('tri', 'name');
        if (!in_array($sort, ['name', 'value', 'qty', 'low'], true)) {
            $sort = 'name';
        }

        return [
            'q'              => trim((string) $request->query('q', '')),
            'show_inactive'  => $request->query('inactifs') === '1',
            'brand_id'       => (int) $request->query('marque', 0),
            'category_id'    => (int) $request->query('categorie', 0),
            'unit_id'        => (int) $request->query('unite', 0),
            'location_id'    => (int) $request->query('site', 0),
            'status'         => $status,
            'min_qty'        => trim((string) $request->query('qte_min', '')),
            'max_qty'        => trim((string) $request->query('qte_max', '')),
            'min_value'      => trim((string) $request->query('valeur_min', '')),
            'max_value'      => trim((string) $request->query('valeur_max', '')),
            'sort'           => $sort,
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, string>
     */
    private function meta(array $filters, PdoReferenceRepository $refs): array
    {
        $meta = [];
        $meta[] = t('Site : :site', ['site' => $this->locationLabel($refs->locations(true), (int) $filters['location_id'])]);
        if ((int) $filters['category_id'] > 0) {
            $meta[] = t('Catégorie : :category', ['category' => $this->nameLabel($refs->categories(true), (int) $filters['category_id'])]);
        }
        if ((int) $filters['brand_id'] > 0) {
            $meta[] = t('Marque : :brand', ['brand' => $this->nameLabel($refs->brands(true), (int) $filters['brand_id'])]);
        }
        if ((int) $filters['unit_id'] > 0) {
            $unit = '#' . (int) $filters['unit_id'];
            foreach ($refs->units(true) as $item) {
                if ($item->id() === (int) $filters['unit_id']) {
                    $unit = $item->code();
                    break;
                }
            }
            $meta[] = t('Unité : :unit', ['unit' => $unit]);
        }
        if ((string) $filters['status'] !== '') {
            $meta[] = t('Statut : :status', ['status' => match ((string) $filters['status']) {
                'out' => t('Rupture'),
                'low' => t('Seuil bas'),
                'ok' => t('En stock'),
                default => t('Tous'),
            }]);
        }
        if ((string) $filters['q'] !== '') {
            $meta[] = t('Recherche : « :term »', ['term' => (string) $filters['q']]);
        }
        if ((string) $filters['min_qty'] !== '' || (string) $filters['max_qty'] !== '') {
            $meta[] = t('Quantité : :range', ['range' => $this->rangeLabel((string) $filters['min_qty'], (string) $filters['max_qty'])]);
        }
        if ((string) $filters['min_value'] !== '' || (string) $filters['max_value'] !== '') {
            $meta[] = t('Valeur : :range', ['range' => $this->rangeLabel((string) $filters['min_value'], (string) $filters['max_value'], currency())]);
        }
        if ($filters['show_inactive']) {
            $meta[] = t('Produits inactifs inclus');
        }

        return $meta;
    }

    /** @param array<int, \App\Domain\Entities\Location> $items */
    private function locationLabel(array $items, int $id): string
    {
        if ($id <= 0) {
            return t('Tous les sites');
        }
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item->name();
            }
        }

        return t('Site #:id', ['id' => $id]);
    }

    /** @param array<int, \App\Domain\Entities\Category>|array<int, \Gos\Domain\Entities\Marque> $items */
    private function nameLabel(array $items, int $id): string
    {
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item->name();
            }
        }

        return '#' . $id;
    }

    private function rangeLabel(string $min, string $max, string $suffix = ''): string
    {
        $suffix = $suffix !== '' ? ' ' . $suffix : '';
        if ($min !== '' && $max !== '') {
            return t('de :min à :max', ['min' => $min, 'max' => $max]) . $suffix;
        }
        if ($min !== '') {
            return t('≥ :min', ['min' => $min]) . $suffix;
        }

        return t('≤ :max', ['max' => $max]) . $suffix;
    }

    public function create(Request $request): Response
    {
        return $this->formView(null, [], []);
    }

    public function store(Request $request): Response
    {
        try {
            $data = $this->validated($request);
            (new PdoProductRepository())->create($data);
            flash('success', t('Produit créé. Enregistrez une entrée de stock pour l’approvisionner.'));
            return Response::redirect(url('/produits'));
        } catch (Throwable $e) {
            return $this->formView(null, $request->all(), [$e->getMessage()]);
        }
    }

    public function edit(Request $request, string $id): Response
    {
        $product = (new PdoProductRepository())->findRaw((int) $id);
        if ($product === null) {
            return View::render('errors/404', ['title' => t('Produit introuvable')])->withStatus(404);
        }

        return $this->formView($product, $product, []);
    }

    public function update(Request $request, string $id): Response
    {
        $repo = new PdoProductRepository();
        $product = $repo->findRaw((int) $id);
        if ($product === null) {
            return View::render('errors/404', ['title' => t('Produit introuvable')])->withStatus(404);
        }

        try {
            $data = $this->validated($request);
            $repo->update((int) $id, $data);
            flash('success', t('Produit mis à jour.'));
            return Response::redirect(url('/produits'));
        } catch (Throwable $e) {
            return $this->formView($product, $request->all(), [$e->getMessage()]);
        }
    }

    public function toggle(Request $request, string $id): Response
    {
        try {
            (new PdoProductRepository())->toggle((int) $id);
            flash('success', t('Statut du produit modifié.'));
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }

        return Response::redirect(url('/produits'));
    }

    /**
     * @param array<string, mixed>|null $product
     * @param array<string, mixed> $old
     * @param array<int, string> $errors
     */
    private function formView(?array $product, array $old, array $errors): Response
    {
        $refs = new PdoReferenceRepository();

        return View::render('products/form', [
            'title'      => $product === null ? t('Nouveau produit') : t('Modifier le produit'),
            'product'    => $product,
            'old'        => $old,
            'errors'     => $errors,
            'brands'     => $refs->brands(true),
            'categories' => $refs->categories(true),
            'units'      => $refs->units(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = [
            'sku'           => trim((string) $request->input('sku', '')),
            'barcode'       => trim((string) $request->input('barcode', '')),
            'designation'   => trim((string) $request->input('designation', '')),
            'brand_id'      => (int) $request->input('brand_id', 0),
            'category_id'   => (int) $request->input('category_id', 0),
            'unit_id'       => (int) $request->input('unit_id', 0),
            'purchase_cost' => $this->number($request->input('purchase_cost', 0), 0),
            'sale_price'    => $this->number($request->input('sale_price', 0), 0),
            'min_stock'     => $this->number($request->input('min_stock', 0), 0),
            'active'        => $request->input('active') === '1',
        ];

        if ($data['sku'] === '') {
            throw new RuntimeException(t('Le SKU est obligatoire.'));
        }
        if ($data['designation'] === '') {
            throw new RuntimeException(t('La désignation est obligatoire.'));
        }

        $refs = new PdoReferenceRepository();
        if (!$refs->exists('brand', $data['brand_id'])) {
            throw new RuntimeException(t('Sélectionnez une marque active.'));
        }
        if (!$refs->exists('category', $data['category_id'])) {
            throw new RuntimeException(t('Sélectionnez une catégorie active.'));
        }
        if (!$refs->exists('unit', $data['unit_id'])) {
            throw new RuntimeException(t('Sélectionnez une unité.'));
        }

        return $data;
    }

    private function number(mixed $value, float $default): float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return $default;
        }
        $raw = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $raw);
        if (!is_numeric($raw) || (float) $raw < 0) {
            throw new RuntimeException(t('Les valeurs numériques doivent être positives.'));
        }

        return (float) $raw;
    }
}

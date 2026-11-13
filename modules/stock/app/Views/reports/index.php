<?php
/** @var array{total_value: float, total_quantity: float, products: int, low_stock: int, locations: int} $stats */
/** @var array<int, \Gos\Domain\Entities\Produit> $products */
/** @var array<int, \Gos\Domain\Entities\Produit> $lowStock */
/** @var array<int, array{name: string, products: int, quantity: float, value: float}> $categories */
/** @var array<int, array{name: string, type: string, quantity: float, value: float}> $byLocation */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<int, \Gos\Domain\Entities\Marque> $brands */
/** @var array<int, \App\Domain\Entities\Category> $categoriesList */
/** @var array<int, \App\Domain\Entities\StockMovement> $movements */
/** @var array{q: string, movement_type: string, direction: string, location_id: int, category_id: int, brand_id: int, status: string, date_from: string, date_to: string} $filters */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var int $movementPage */
/** @var int $movementPages */
/** @var int $movementsTotal */
$query = http_build_query(array_filter([
    'q'         => $filters['q'],
    'type'      => $filters['movement_type'],
    'sens'      => $filters['direction'],
    'site'      => $filters['location_id'] ?: '',
    'categorie' => $filters['category_id'] ?: '',
    'marque'    => $filters['brand_id'] ?: '',
    'statut'    => $filters['status'],
    'du'        => $filters['date_from'],
    'au'        => $filters['date_to'],
], static fn (mixed $value): bool => $value !== '' && $value !== null));
$pageUrl = static fn (int $target): string => url('/rapports?' . ($query !== '' ? $query . '&' : '') . 'page=' . $target);
$movementPageUrl = static fn (int $target): string => url('/rapports?' . ($query !== '' ? $query . '&' : '') . 'page=' . $page . '&mp=' . $target);
$renderPager = static function (int $page, int $pages, int $total, callable $pageUrl): void {
    include __DIR__ . '/../partials/pager.php';
};
$exportLinks = [
    ['label' => 'Rapport de stock', 'format' => 'xls', 'description' => 'Produits, quantités et valorisation', 'url' => url('/rapports/export?export=stock&' . $query . '&format=xls')],
    ['label' => 'Rapport de stock', 'format' => 'pdf', 'description' => 'Synthèse imprimable du stock', 'url' => url('/rapports/export?export=stock&' . $query . '&format=pdf')],
    ['label' => 'Rapport de stock', 'format' => 'csv', 'description' => 'Données brutes du stock', 'url' => url('/rapports/export?export=stock&' . $query . '&format=csv')],
    ['label' => 'Journal des mouvements', 'format' => 'xls', 'description' => 'Tous les flux valorisés et filtrés', 'url' => url('/rapports/export?export=movements&' . $query . '&format=xls')],
    ['label' => 'Journal des mouvements', 'format' => 'pdf', 'description' => 'Journal détaillé prêt à archiver', 'url' => url('/rapports/export?export=movements&' . $query . '&format=pdf')],
    ['label' => 'Journal des mouvements', 'format' => 'csv', 'description' => 'Historique exploitable', 'url' => url('/rapports/export?export=movements&' . $query . '&format=csv')],
];
$maxLocation = 0.0;
foreach ($byLocation as $location) {
    $maxLocation = max($maxLocation, (float) $location['value']);
}
if ($maxLocation <= 0) {
    $maxLocation = 1;
}
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Analyse</span>
        <h2>Rapports &amp; exports</h2>
        <p>Valorisation, répartition multi-sites, alertes de seuil et journal des mouvements.</p>
    </div>
    <div class="page-actions">
        <?php include __DIR__ . '/../partials/export-menu.php'; ?>
        <button class="btn btn-soft" type="button" data-print><i class="bi bi-printer"></i></button>
    </div>
</div>

<section class="panel">
    <div class="filter-panel">
        <form class="filter-form advanced" method="get" action="<?= url('/rapports') ?>">
            <div class="filter-field wide">
                <label for="report-q">Recherche</label>
                <div class="input-icon compact">
                    <i class="bi bi-search"></i>
                    <input id="report-q" class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Produit, SKU, document, site, référence…">
                </div>
            </div>
            <div class="filter-field">
                <label for="report-site">Site</label>
                <select id="report-site" class="form-select" name="site" data-quick="location">
                    <option value="">Tous les sites</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= $filters['location_id'] === $location->id() ? 'selected' : '' ?>><?= e($location->code()) ?> · <?= e($location->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="report-category">Catégorie</label>
                <select id="report-category" class="form-select" name="categorie" data-quick="category">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categoriesList as $category): ?>
                        <option value="<?= $category->id() ?>" <?= $filters['category_id'] === $category->id() ? 'selected' : '' ?>><?= e($category->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="report-brand">Marque</label>
                <select id="report-brand" class="form-select" name="marque" data-quick="brand">
                    <option value="">Toutes les marques</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand->id() ?>" <?= $filters['brand_id'] === $brand->id() ? 'selected' : '' ?>><?= e($brand->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="report-status">Statut produit</label>
                <select id="report-status" class="form-select" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="ok" <?= $filters['status'] === 'ok' ? 'selected' : '' ?>>En stock</option>
                    <option value="low" <?= $filters['status'] === 'low' ? 'selected' : '' ?>>Seuil bas</option>
                    <option value="out" <?= $filters['status'] === 'out' ? 'selected' : '' ?>>Rupture</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="report-type">Type de flux</label>
                <select id="report-type" class="form-select" name="type">
                    <option value="">Tous types</option>
                    <?php foreach (['entree' => 'Entrées', 'sortie' => 'Sorties', 'transfert' => 'Transferts', 'inventaire' => 'Inventaires'] as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= $filters['movement_type'] === $value ? 'selected' : '' ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="report-direction">Sens</label>
                <select id="report-direction" class="form-select" name="sens">
                    <option value="">Entrées et sorties</option>
                    <option value="in" <?= $filters['direction'] === 'in' ? 'selected' : '' ?>>Entrées</option>
                    <option value="out" <?= $filters['direction'] === 'out' ? 'selected' : '' ?>>Sorties</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="report-from">Du</label>
                <input id="report-from" class="form-control" type="date" name="du" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="filter-field">
                <label for="report-to">Au</label>
                <input id="report-to" class="form-control" type="date" name="au" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="filter-actions">
                <a class="btn btn-ghost" href="<?= url('/rapports') ?>"><i class="bi bi-x-lg"></i> Réinitialiser</a>
                <button class="btn btn-soft" type="submit"><i class="bi bi-funnel"></i> Appliquer les filtres</button>
            </div>
        </form>
    </div>
</section>

<div class="kpi-grid">
    <div class="kpi-card">
        <span class="kpi-icon accent"><i class="bi bi-cash-stack"></i></span>
        <div><span class="kpi-label">Valeur du stock</span><strong><?= e(money($stats['total_value'])) ?></strong></div>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon blue"><i class="bi bi-boxes"></i></span>
        <div><span class="kpi-label">Quantité totale</span><strong><?= e(qty($stats['total_quantity'])) ?></strong></div>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon violet"><i class="bi bi-box-seam"></i></span>
        <div><span class="kpi-label">Produits actifs</span><strong><?= e((string) $stats['products']) ?></strong></div>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon warning"><i class="bi bi-exclamation-triangle"></i></span>
        <div><span class="kpi-label">Alertes seuil</span><strong><?= e((string) $stats['low_stock']) ?></strong></div>
    </div>
</div>

<div class="report-grid">
    <section class="panel">
        <div class="panel-head"><div><h3>Répartition par site</h3><p>Valeur du stock par emplacement</p></div></div>
        <?php if ($byLocation === []): ?>
            <p class="muted">Aucun site.</p>
        <?php endif; ?>
        <?php foreach ($byLocation as $location): ?>
            <div class="bar-row">
                <div class="bar-row-head"><span><?= e((string) $location['name']) ?></span><strong><?= e(money((float) $location['value'])) ?></strong></div>
                <div class="bar-track"><div class="bar-fill" style="width: <?= e((string) max(2, round(((float) $location['value'] / $maxLocation) * 100))) ?>%"></div></div>
                <small class="muted"><?= e(qty((float) $location['quantity'])) ?> unités · <?= $location['type'] === 'depot' ? 'Dépôt' : 'Point de vente' ?></small>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h3>Répartition par catégorie</h3><p>Poids de chaque famille</p></div></div>
        <?php if ($categories === []): ?>
            <p class="muted">Aucune catégorie.</p>
        <?php endif; ?>
        <?php foreach ($categories as $category): ?>
            <div class="bar-row">
                <div class="bar-row-head"><span><?= e((string) $category['name']) ?></span><strong><?= e(money((float) $category['value'])) ?></strong></div>
                <div class="bar-track"><div class="bar-fill blue" style="width: <?= e((string) max(2, round(((float) $category['value'] / max(1, (float) $stats['total_value'])) * 100))) ?>%"></div></div>
                <small class="muted"><?= e((string) $category['products']) ?> produit(s) · <?= e(qty((float) $category['quantity'])) ?> unités</small>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div><h3>Stock valorisé</h3><p><?= (int) $total ?> produit(s) correspondant aux filtres</p></div>
        <a class="btn btn-ghost btn-sm" href="<?= url('/rapports/export?export=stock&' . $query . '&format=xls') ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
    </div>
    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead>
            <tr>
                <th>SKU</th>
                <th>Produit</th>
                <th>Catégorie</th>
                <th class="text-end">Qté</th>
                <th class="text-end">CUMP</th>
                <th class="text-end">Valeur</th>
                <th>Statut</th>
                <th>Positionnement</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($products === []): ?>
                <tr><td colspan="8" class="text-center muted py-4">Aucun produit actif.</td></tr>
            <?php endif; ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><span class="code-chip"><?= e($product->sku()) ?></span></td>
                    <td><strong><?= e($product->designation()) ?></strong><small class="d-block muted"><?= e($product->brand()) ?></small></td>
                    <td><?= e($product->category()) ?></td>
                    <td class="text-end"><?= e(qty($product->quantity())) ?> <small class="muted"><?= e($product->unit()) ?></small></td>
                    <td class="text-end"><?= e(money($product->purchaseCost())) ?></td>
                    <td class="text-end"><strong><?= e(money($product->stockValue())) ?></strong></td>
                    <td>
                        <?php if ($product->isOutOfStock()): ?>
                            <span class="pill pill-danger">Rupture</span>
                        <?php elseif ($product->isLowStock()): ?>
                            <span class="pill pill-warning">Seuil bas</span>
                        <?php else: ?>
                            <span class="pill pill-success">En stock</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="muted"><?= e($product->positions()) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../partials/pager.php'; ?>
</section>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div><h3>Journal des mouvements</h3><p>Entrées, sorties, transferts et ajustements d’inventaire</p></div>
        <a class="btn btn-ghost btn-sm" href="<?= url('/rapports/export?export=movements&' . $query . '&format=xls') ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
    </div>
    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead>
            <tr>
                <th>Date</th>
                <th>Document</th>
                <th>Produit</th>
                <th>Site</th>
                <th>Sens</th>
                <th class="text-end">Qté</th>
                <th class="text-end">Valeur</th>
                <th>Opérateur</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($movements === []): ?>
                <tr><td colspan="8" class="text-center muted py-4">Aucun mouvement sur la période.</td></tr>
            <?php endif; ?>
            <?php foreach ($movements as $movement): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i', strtotime($movement->occurredAt()))) ?></td>
                    <td>
                        <span class="code-chip"><?= e($movement->numero() ?? '—') ?></span>
                        <small class="d-block muted"><?= e(doc_type_label($movement->type())) ?><?= $movement->motif() !== null ? ' · ' . e($movement->motif()) : '' ?></small>
                    </td>
                    <td><strong><?= e($movement->product()) ?></strong><small class="d-block muted"><?= e($movement->sku()) ?></small></td>
                    <td><?= e($movement->location()) ?></td>
                    <td>
                        <?php if ($movement->isIn()): ?>
                            <span class="pill pill-success"><i class="bi bi-arrow-down"></i> Entrée</span>
                        <?php else: ?>
                            <span class="pill pill-danger"><i class="bi bi-arrow-up"></i> Sortie</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= e(qty($movement->qty())) ?> <small class="muted"><?= e($movement->unit()) ?></small></td>
                    <td class="text-end"><?= e(money($movement->value())) ?></td>
                    <td><?= e($movement->userName()) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $renderPager($movementPage, $movementPages, $movementsTotal, $movementPageUrl); ?>
</section>

<?php if ($lowStock !== []): ?>
    <section class="panel">
        <div class="panel-head"><div><h3>Alertes de seuil</h3><p>Produits à réapprovisionner en priorité</p></div></div>
        <div class="alert-list">
            <?php foreach ($lowStock as $product): ?>
                <div class="alert-row">
                    <div>
                        <strong><?= e($product->designation()) ?></strong>
                        <small><?= e($product->sku()) ?> · seuil <?= e(qty($product->minStock())) ?> <?= e($product->unit()) ?></small>
                    </div>
                    <span class="pill <?= $product->isOutOfStock() ? 'pill-danger' : 'pill-warning' ?>">
                        <?= e(qty($product->quantity())) ?> en stock
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

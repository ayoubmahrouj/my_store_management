<?php
/** @var array<int, array{id: int, sku: string, designation: string, brand: string, category: string, unit: string, purchase_cost: float, sale_price: float, min_stock: float, quantity: float, stock_value: float, sale_value: float, margin_value: float, positions: string}> $rows */
/** @var array{q: string, location_id: int, category_id: int, brand_id: int, unit_id: int, status: string, min_qty: string, max_qty: string, min_value: string, max_value: string, min_margin: string, max_margin: string, sort: string} $filters */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<int, \App\Domain\Entities\Category> $categories */
/** @var array<int, \App\Domain\Entities\Brand> $brands */
/** @var array<int, \App\Domain\Entities\Unit> $units */
/** @var array{products: int, quantity: float, value: float, saleValue: float, margin: float, alerts: int} $summary */
/** @var bool $canManage */
$query = http_build_query(array_filter([
    'q'           => $filters['q'],
    'site'        => $filters['location_id'] ?: '',
    'categorie'   => $filters['category_id'] ?: '',
    'marque'      => $filters['brand_id'] ?: '',
    'unite'       => $filters['unit_id'] ?: '',
    'statut'      => $filters['status'],
    'qte_min'     => $filters['min_qty'],
    'qte_max'     => $filters['max_qty'],
    'valeur_min'  => $filters['min_value'],
    'valeur_max'  => $filters['max_value'],
    'marge_min'   => $filters['min_margin'],
    'marge_max'   => $filters['max_margin'],
    'tri'         => $filters['sort'],
], static fn (mixed $value): bool => $value !== '' && $value !== null));
$exportLinks = [
    ['label' => 'État du stock', 'format' => 'xls', 'description' => 'Filtres, quantités, CUMP et valorisation', 'url' => url('/consultation/export?' . $query . '&format=xls')],
    ['label' => 'État du stock', 'format' => 'pdf', 'description' => 'Synthèse imprimable pour contrôle', 'url' => url('/consultation/export?' . $query . '&format=pdf')],
    ['label' => 'État du stock', 'format' => 'csv', 'description' => 'Export brut pour retraitement', 'url' => url('/consultation/export?' . $query . '&format=csv')],
];
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Consultation rapide</span>
        <h2>État du stock</h2>
        <p>Recherchez un produit, vérifiez son positionnement et sa valeur avant toute opération.</p>
    </div>
    <div class="page-actions">
        <?php include __DIR__ . '/../partials/export-menu.php'; ?>
        <a class="btn btn-accent" href="<?= url('/statistiques' . ($filters['location_id'] ? '?site=' . $filters['location_id'] : '')) ?>">
            <i class="bi bi-graph-up-arrow"></i> Statistiques
        </a>
    </div>
</div>

<div class="mini-stats cols-4">
    <div><span>Produits affichés</span><strong><?= e((string) $summary['products']) ?></strong></div>
    <div><span>Quantité affichée</span><strong><?= e(qty($summary['quantity'])) ?></strong></div>
    <div><span>Valeur affichée</span><strong><?= e(money($summary['value'])) ?></strong></div>
    <div><span>Alertes seuil</span><strong class="<?= $summary['alerts'] > 0 ? 'text-warning' : '' ?>"><?= e((string) $summary['alerts']) ?></strong></div>
</div>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3>Recherche de stock</h3>
            <p>Les quantités et valeurs sont recalculées selon le site et les critères sélectionnés.</p>
        </div>
        <div class="export-kpi-row">
            <span class="export-kpi"><i class="bi bi-wallet2"></i> Potentiel <strong><?= e(money($summary['saleValue'])) ?></strong></span>
            <span class="export-kpi"><i class="bi bi-graph-up"></i> Marge <strong><?= e(money($summary['margin'])) ?></strong></span>
        </div>
    </div>
    <div class="filter-panel">
        <form class="filter-form advanced" method="get" action="<?= url('/consultation') ?>">
            <div class="filter-field wide">
                <label for="consult-q">Recherche</label>
                <div class="input-icon compact">
                    <i class="bi bi-search"></i>
                    <input id="consult-q" class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="SKU, produit, marque, catégorie, code-barres…">
                </div>
            </div>
            <div class="filter-field">
                <label for="consult-site">Site</label>
                <select id="consult-site" class="form-select" name="site" data-quick="location">
                    <option value="">Tous les sites</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= $filters['location_id'] === $location->id() ? 'selected' : '' ?>><?= e($location->code()) ?> · <?= e($location->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="consult-category">Catégorie</label>
                <select id="consult-category" class="form-select" name="categorie" data-quick="category">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category->id() ?>" <?= $filters['category_id'] === $category->id() ? 'selected' : '' ?>><?= e($category->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="consult-brand">Marque</label>
                <select id="consult-brand" class="form-select" name="marque" data-quick="brand">
                    <option value="">Toutes les marques</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand->id() ?>" <?= $filters['brand_id'] === $brand->id() ? 'selected' : '' ?>><?= e($brand->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="consult-unit">Unité</label>
                <select id="consult-unit" class="form-select" name="unite" data-quick="unit">
                    <option value="">Toutes les unités</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= $unit->id() ?>" <?= $filters['unit_id'] === $unit->id() ? 'selected' : '' ?>><?= e($unit->code()) ?> · <?= e($unit->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="consult-status">Statut</label>
                <select id="consult-status" class="form-select" name="statut">
                    <option value="">Tous les statuts</option>
                    <?php foreach (['ok' => 'En stock', 'low' => 'Seuil bas', 'out' => 'Rupture'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="consult-min-qty">Qté min</label>
                <input id="consult-min-qty" class="form-control" type="number" step="any" min="0" name="qte_min" value="<?= e($filters['min_qty']) ?>" placeholder="0">
            </div>
            <div class="filter-field">
                <label for="consult-max-qty">Qté max</label>
                <input id="consult-max-qty" class="form-control" type="number" step="any" min="0" name="qte_max" value="<?= e($filters['max_qty']) ?>" placeholder="Illimité">
            </div>
            <div class="filter-field">
                <label for="consult-min-value">Valeur min (<?= e(currency()) ?>)</label>
                <input id="consult-min-value" class="form-control" type="number" step="any" min="0" name="valeur_min" value="<?= e($filters['min_value']) ?>" placeholder="0">
            </div>
            <div class="filter-field">
                <label for="consult-max-value">Valeur max (<?= e(currency()) ?>)</label>
                <input id="consult-max-value" class="form-control" type="number" step="any" min="0" name="valeur_max" value="<?= e($filters['max_value']) ?>" placeholder="Illimité">
            </div>
            <div class="filter-field">
                <label for="consult-min-margin">Marge min (<?= e(currency()) ?>)</label>
                <input id="consult-min-margin" class="form-control" type="number" step="any" name="marge_min" value="<?= e($filters['min_margin']) ?>" placeholder="0">
            </div>
            <div class="filter-field">
                <label for="consult-max-margin">Marge max (<?= e(currency()) ?>)</label>
                <input id="consult-max-margin" class="form-control" type="number" step="any" name="marge_max" value="<?= e($filters['max_margin']) ?>" placeholder="Illimité">
            </div>
            <div class="filter-field">
                <label for="consult-sort">Tri</label>
                <select id="consult-sort" class="form-select" name="tri">
                    <?php foreach ([
                        'value' => 'Valeur décroissante', 'value_asc' => 'Valeur croissante',
                        'qty_desc' => 'Quantité décroissante', 'qty_asc' => 'Quantité croissante',
                        'low' => 'Alertes d’abord', 'name' => 'Nom A → Z', 'margin' => 'Marge potentielle',
                    ] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <a class="btn btn-ghost" href="<?= url('/consultation') ?>"><i class="bi bi-x-lg"></i> Réinitialiser</a>
                <button class="btn btn-soft" type="submit"><i class="bi bi-funnel"></i> Appliquer les filtres</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle consultation-table">
            <thead>
            <tr>
                <th>Produit</th>
                <th>Catégorie</th>
                <th>Positionnement</th>
                <th class="text-end">Qté</th>
                <th class="text-end">CUMP</th>
                <th class="text-end">Valeur stock</th>
                <th class="text-end">Prix / marge</th>
                <th>Statut</th>
                <?php if ($canManage): ?><th class="text-end"></th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr>
                    <td colspan="<?= $canManage ? '9' : '8' ?>">
                        <div class="empty-state">
                            <i class="bi bi-search"></i>
                            <h4>Aucun produit trouvé</h4>
                            <p>Modifiez les filtres ou créez les produits et référentiels nécessaires.</p>
                            <?php if ($canManage): ?>
                                <a class="btn btn-accent btn-add mt-3" href="<?= url('/produits/nouveau') ?>" data-modal
                                   title="Nouveau produit" aria-label="Nouveau produit">
                                    <i class="bi bi-plus-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $isOut = (float) $row['quantity'] <= 0;
                $isLow = !$isOut && (float) $row['quantity'] <= (float) $row['min_stock'];
                $marginRate = (float) $row['purchase_cost'] > 0
                    ? (((float) $row['sale_price'] - (float) $row['purchase_cost']) / (float) $row['purchase_cost']) * 100
                    : 0.0;
                $positionParts = array_values(array_filter(array_map('trim', explode('·', (string) $row['positions']))));
                ?>
                <tr>
                    <td>
                        <div class="product-cell">
                            <span class="product-avatar"><?= e(mb_substr($row['designation'], 0, 1)) ?></span>
                            <div>
                                <strong><?= e($row['designation']) ?></strong>
                                <small><?= e($row['sku']) ?> · <?= e($row['brand']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><span class="pill pill-muted"><?= e($row['category']) ?></span></td>
                    <td>
                        <div class="position-chips">
                            <?php foreach ($positionParts as $part): ?>
                                <span class="position-chip"><?= e($part) ?></span>
                            <?php endforeach; ?>
                            <?php if ($positionParts === []): ?><span class="muted">—</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="text-end">
                        <strong><?= e(qty($row['quantity'])) ?></strong>
                        <small class="d-block muted"><?= e($row['unit']) ?></small>
                    </td>
                    <td class="text-end"><?= e(money($row['purchase_cost'])) ?></td>
                    <td class="text-end">
                        <strong><?= e(money($row['stock_value'])) ?></strong>
                        <small class="d-block muted">pot. <?= e(money($row['sale_value'])) ?></small>
                    </td>
                    <td class="text-end">
                        <?= e(money($row['sale_price'])) ?>
                        <small class="d-block <?= $marginRate < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $marginRate >= 0 ? '+' : '' ?><?= e(number_format($marginRate, 1, ',', ' ')) ?> %
                        </small>
                    </td>
                    <td>
                        <?php if ($isOut): ?>
                            <span class="pill pill-danger">Rupture</span>
                        <?php elseif ($isLow): ?>
                            <span class="pill pill-warning">Seuil bas</span>
                        <?php else: ?>
                            <span class="pill pill-success">En stock</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canManage): ?>
                        <td class="text-end">
                            <a class="btn btn-icon btn-sm" href="<?= url('/produits/' . (int) $row['id'] . '/modifier') ?>" title="Modifier">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

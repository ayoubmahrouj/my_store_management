<?php
/** @var int $days */
/** @var int $locationId */
/** @var string $locationName */
/** @var int $categoryId */
/** @var string $categoryName */
/** @var int $brandId */
/** @var string $brandName */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<int, \App\Domain\Entities\Category> $categoriesList */
/** @var array<int, \Gos\Domain\Entities\Marque> $brands */
/** @var array<string, mixed> $filters */
/** @var array{
 *     total_value: float, total_quantity: float, potential_value: float, margin_value: float,
 *     products: int, stocked_products: int, locations: int, low_stock: int,
 *     in_qty: float, out_qty: float, in_value: float, out_value: float, net_value: float,
 *     days: int, start_date: string, end_date: string
 * } $summary */
/** @var array{labels: array<int, string>, capital: array<int, float>, quantity: array<int, float>, in_value: array<int, float>, out_value: array<int, float>, start_date: string, end_date: string} $history */
/** @var array<int, array{name: string, products: int, quantity: float, value: float}> $categories */
/** @var array<int, array{sku: string, designation: string, category: string, unit: string, quantity: float, value: float, sale_value: float}> $topProducts */
/** @var array<int, array{name: string, type: string, quantity: float, value: float}> $byLocation */
/** @var array<int, \App\Domain\Entities\StockMovement> $movements */
$maxLocationValue = 0.0;
foreach ($byLocation as $site) {
    $maxLocationValue = max($maxLocationValue, (float) $site['value']);
}
if ($maxLocationValue <= 0) {
    $maxLocationValue = 1;
}

$filterQuery = http_build_query(array_filter([
    'site'      => $locationId ?: '',
    'periode'   => $days,
    'categorie' => $categoryId ?: '',
    'marque'    => $brandId ?: '',
], static fn (mixed $value): bool => $value !== '' && $value !== null));
$exportBase = url('/statistiques/export?' . $filterQuery);
$exportLinks = [
    ['label' => t('Capital & mouvements'), 'format' => 'xls', 'description' => t('Évolution quotidienne sur la période'), 'url' => $exportBase . '&export=history&format=xls'],
    ['label' => t('Capital & mouvements'), 'format' => 'pdf', 'description' => t('Courbe et flux sous forme de tableau'), 'url' => $exportBase . '&export=history&format=pdf'],
    ['label' => t('Capital par site'), 'format' => 'xls', 'description' => t('Dépôts et points de vente'), 'url' => $exportBase . '&export=sites&format=xls'],
    ['label' => t('Capital par site'), 'format' => 'pdf', 'description' => t('Répartition du capital par site'), 'url' => $exportBase . '&export=sites&format=pdf'],
    ['label' => t('Capital par catégorie'), 'format' => 'xls', 'description' => t('Poids de chaque famille'), 'url' => $exportBase . '&export=categories&format=xls'],
    ['label' => t('Capital par catégorie'), 'format' => 'pdf', 'description' => t('Synthèse par catégorie'), 'url' => $exportBase . '&export=categories&format=pdf'],
    ['label' => t('Top produits'), 'format' => 'xls', 'description' => t('Classement par valeur immobilisée'), 'url' => $exportBase . '&export=products&format=xls'],
    ['label' => t('Top produits'), 'format' => 'pdf', 'description' => t('Produits les plus valorisés'), 'url' => $exportBase . '&export=products&format=pdf'],
];

$chartCategories = array_values(array_filter($categories, static fn (array $category): bool => (float) $category['value'] > 0));
$chartData = [
    'labels'      => $history['labels'],
    'capital'     => $history['capital'],
    'inValues'    => $history['in_value'],
    'outValues'   => $history['out_value'],
    'categories'  => [
        'labels' => array_map(static fn (array $category): string => (string) $category['name'], $chartCategories),
        'values' => array_map(static fn (array $category): float => (float) $category['value'], $chartCategories),
    ],
];
$jsonOptions = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= e(t('Pilotage financier')) ?></span>
        <h2><?= e(t('Statistiques & capital')) ?></h2>
        <p><?= e(t('Suivez la valeur immobilisée, les flux et la performance de votre stock dans le temps.')) ?></p>
    </div>
    <div class="page-actions">
        <?php include __DIR__ . '/../partials/export-menu.php'; ?>
        <a class="btn btn-accent" href="<?= url('/consultation?' . http_build_query(array_filter([
            'site' => $locationId ?: '', 'categorie' => $categoryId ?: '', 'marque' => $brandId ?: '',
        ]))) ?>">
            <i class="bi bi-search"></i> <?= e(t('Consulter le stock')) ?>
        </a>
    </div>
</div>

<section class="panel">
    <div class="filter-panel">
        <form class="filter-form advanced" method="get" action="<?= url('/statistiques') ?>">
            <div class="filter-field">
                <label for="stats-site"><?= e(t('Site')) ?></label>
                <select id="stats-site" class="form-select" name="site" data-quick="location">
                    <option value=""><?= e(t('Tous les sites')) ?></option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= $locationId === $location->id() ? 'selected' : '' ?>><?= e($location->code()) ?> · <?= e($location->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="stats-category"><?= e(t('Catégorie')) ?></label>
                <select id="stats-category" class="form-select" name="categorie" data-quick="category">
                    <option value=""><?= e(t('Toutes les catégories')) ?></option>
                    <?php foreach ($categoriesList as $category): ?>
                        <option value="<?= $category->id() ?>" <?= $categoryId === $category->id() ? 'selected' : '' ?>><?= e($category->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="stats-brand"><?= e(t('Marque')) ?></label>
                <select id="stats-brand" class="form-select" name="marque" data-quick="brand">
                    <option value=""><?= e(t('Toutes les marques')) ?></option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand->id() ?>" <?= $brandId === $brand->id() ? 'selected' : '' ?>><?= e($brand->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="stats-period"><?= e(t('Période')) ?></label>
                <select id="stats-period" class="form-select" name="periode">
                    <?php foreach ([7 => '7 derniers jours', 30 => '30 derniers jours', 90 => '90 derniers jours', 365 => '12 derniers mois'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $days === $value ? 'selected' : '' ?>><?= e(t($label)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <a class="btn btn-ghost" href="<?= url('/statistiques') ?>"><i class="bi bi-x-lg"></i> <?= e(t('Réinitialiser')) ?></a>
                <button class="btn btn-soft" type="submit"><i class="bi bi-arrow-repeat"></i> <?= e(t('Actualiser')) ?></button>
            </div>
        </form>
    </div>
</section>

<div class="stats-context">
    <span><i class="bi bi-geo-alt"></i> <?= e($locationName) ?></span>
    <span><i class="bi bi-tag"></i> <?= e($categoryName) ?></span>
    <span><i class="bi bi-award"></i> <?= e($brandName) ?></span>
    <span><i class="bi bi-calendar3"></i> <?= e(date('d/m/Y', strtotime($summary['start_date']))) ?> → <?= e(date('d/m/Y', strtotime($summary['end_date']))) ?></span>
    <a href="<?= url('/consultation' . ($filterQuery !== '' ? '?' . $filterQuery : '')) ?>"><?= e(t('Consulter le stock')) ?> <i class="bi bi-arrow-right"></i></a>
</div>

<div class="kpi-grid">
    <article class="kpi-card accent">
        <div class="kpi-icon"><i class="bi bi-safe2"></i></div>
        <div class="kpi-body">
            <span class="kpi-label"><?= e(t('Capital stock')) ?></span>
            <strong class="kpi-value"><?= e(money($summary['total_value'])) ?></strong>
            <span class="kpi-foot"><?= e(qty($summary['total_quantity'])) ?> <?= e(t('unités valorisées au CUMP')) ?></span>
        </div>
    </article>

    <article class="kpi-card">
        <div class="kpi-icon <?= $summary['net_value'] < 0 ? 'amber' : 'blue' ?>"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="kpi-body">
            <span class="kpi-label"><?= e(t('Variation sur la période')) ?></span>
            <strong class="kpi-value <?= $summary['net_value'] < 0 ? 'text-danger' : 'text-success' ?>">
                <?= ($summary['net_value'] > 0 ? '+' : '') . e(money($summary['net_value'])) ?>
            </strong>
            <span class="kpi-foot">+<?= e(money($summary['in_value'])) ?> <?= e(t('entrées')) ?> · −<?= e(money($summary['out_value'])) ?> <?= e(t('sorties')) ?></span>
        </div>
    </article>

    <article class="kpi-card">
        <div class="kpi-icon violet"><i class="bi bi-boxes"></i></div>
        <div class="kpi-body">
            <span class="kpi-label"><?= e(t('Quantité en stock')) ?></span>
            <strong class="kpi-value"><?= e(qty($summary['total_quantity'])) ?></strong>
            <span class="kpi-foot"><?= e((string) $summary['stocked_products']) ?> <?= e(t('produit(s) localisé(s)')) ?> · <?= e((string) $summary['products']) ?> <?= e(t('actif(s)')) ?></span>
        </div>
    </article>

    <article class="kpi-card">
        <div class="kpi-icon amber"><i class="bi bi-tags"></i></div>
        <div class="kpi-body">
            <span class="kpi-label"><?= e(t('Valeur potentielle')) ?></span>
            <strong class="kpi-value"><?= e(money($summary['potential_value'])) ?></strong>
            <span class="kpi-foot"><?= e(t('Marge brute théorique')) ?> <?= e(money($summary['margin_value'])) ?> · <?= e((string) $summary['low_stock']) ?> <?= e(t('alerte(s)')) ?></span>
        </div>
    </article>
</div>

<section class="panel chart-panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3><?= e(t('Évolution du capital')) ?></h3>
            <p><?= e(t('Reconstitution quotidienne à partir des mouvements valorisés')) ?></p>
        </div>
        <div class="chart-legend-pills">
            <span class="pill pill-success"><i class="bi bi-circle-fill"></i> <?= e(t('Capital')) ?></span>
            <span class="pill pill-muted"><?= e($locationName) ?></span>
        </div>
    </div>
    <div class="chart-wrap chart-lg">
        <canvas id="capitalChart" aria-label="<?= e(t('Évolution du capital')) ?>" role="img"></canvas>
    </div>
    <?php if (array_sum($history['capital']) <= 0): ?>
        <p class="chart-hint"><i class="bi bi-info-circle"></i> <?= e(t('Les mouvements validés alimenteront automatiquement cette courbe.')) ?></p>
    <?php endif; ?>
</section>

<div class="report-grid">
    <section class="panel chart-panel">
        <div class="panel-head">
            <div><h3><?= e(t('Flux de la période')) ?></h3><p><?= e(t('Valeur des entrées et sorties validées')) ?></p></div>
        </div>
        <div class="chart-wrap">
            <canvas id="flowChart" aria-label="<?= e(t('Flux de stock')) ?>" role="img"></canvas>
        </div>
    </section>

    <section class="panel chart-panel">
        <div class="panel-head">
            <div><h3><?= e(t('Répartition par catégorie')) ?></h3><p><?= e(t('Poids de chaque famille dans le capital')) ?></p></div>
        </div>
        <div class="chart-wrap">
            <canvas id="categoryChart" aria-label="<?= e(t('Répartition par catégorie')) ?>" role="img"></canvas>
        </div>
    </section>
</div>

<div class="grid-2">
    <section class="panel">
        <div class="panel-head">
            <div><h3><?= e(t('Capital par site')) ?></h3><p><?= e(t('Valeur immobilisée sur l’ensemble du réseau')) ?></p></div>
            <span class="pill pill-muted"><?= count($byLocation) ?> <?= e(t('site(s)')) ?></span>
        </div>
        <div class="site-list">
            <?php if ($byLocation === []): ?>
                <div class="empty-state compact"><i class="bi bi-geo"></i><p><?= e(t('Aucun site configuré.')) ?></p></div>
            <?php endif; ?>
            <?php foreach ($byLocation as $site): ?>
                <?php $width = max(2, (int) round(((float) $site['value'] / $maxLocationValue) * 100)); ?>
                <div class="site-row">
                    <div class="site-info">
                        <span class="site-icon <?= $site['type'] === 'depot' ? 'depot' : 'pos' ?>">
                            <i class="bi bi-<?= $site['type'] === 'depot' ? 'building' : 'shop' ?>"></i>
                        </span>
                        <div>
                            <strong><?= e($site['name']) ?></strong>
                            <small><?= e(qty((float) $site['quantity'])) ?> <?= e(t('unités')) ?> · <?= $site['type'] === 'depot' ? e(t('Dépôt')) : e(t('Point de vente')) ?></small>
                        </div>
                    </div>
                    <div class="site-value">
                        <strong><?= e(money((float) $site['value'])) ?></strong>
                        <span class="bar"><i style="width: <?= $width ?>%"></i></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3><?= e(t('Top produits')) ?></h3><p><?= e(t('Les plus fortes valeurs immobilisées')) ?></p></div>
            <a class="btn btn-ghost btn-sm" href="<?= url('/consultation') ?>"><?= e(t('Tout consulter')) ?></a>
        </div>
        <div class="table-responsive">
            <table class="table table-premium align-middle">
                <thead><tr><th><?= e(t('Produit')) ?></th><th class="text-end"><?= e(t('Qté')) ?></th><th class="text-end"><?= e(t('Valeur')) ?></th></tr></thead>
                <tbody>
                <?php if ($topProducts === []): ?>
                    <tr><td colspan="3" class="text-center muted py-4"><?= e(t('Aucun stock valorisé.')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($topProducts as $product): ?>
                    <tr>
                        <td>
                            <strong><?= e($product['designation']) ?></strong>
                            <small class="d-block muted"><?= e($product['sku']) ?> · <?= e($product['category']) ?></small>
                        </td>
                        <td class="text-end"><?= e(qty($product['quantity'])) ?> <small class="muted"><?= e($product['unit']) ?></small></td>
                        <td class="text-end"><strong><?= e(money($product['value'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h3><?= e(t('Derniers mouvements')) ?></h3><p><?= e(t('Activité récente sur la période sélectionnée')) ?></p></div>
        <a class="btn btn-ghost btn-sm" href="<?= url('/rapports') ?>"><?= e(t('Journal complet')) ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead><tr><th><?= e(t('Date')) ?></th><th><?= e(t('Document')) ?></th><th><?= e(t('Produit')) ?></th><th><?= e(t('Site')) ?></th><th><?= e(t('Sens')) ?></th><th class="text-end"><?= e(t('Qté')) ?></th><th class="text-end"><?= e(t('Valeur')) ?></th></tr></thead>
            <tbody>
            <?php if ($movements === []): ?>
                <tr><td colspan="7" class="text-center muted py-4"><?= e(t('Aucun mouvement sur la période.')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($movements as $movement): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i', strtotime($movement->occurredAt()))) ?></td>
                    <td><span class="code-chip"><?= e($movement->numero() ?? '—') ?></span></td>
                    <td><strong><?= e($movement->product()) ?></strong><small class="d-block muted"><?= e($movement->sku()) ?></small></td>
                    <td><?= e($movement->location()) ?></td>
                    <td>
                        <span class="pill <?= $movement->isIn() ? 'pill-success' : 'pill-danger' ?>">
                            <i class="bi bi-arrow-<?= $movement->isIn() ? 'down' : 'up' ?>"></i> <?= $movement->isIn() ? e(t('Entrée')) : e(t('Sortie')) ?>
                        </span>
                    </td>
                    <td class="text-end"><?= e(qty($movement->qty())) ?></td>
                    <td class="text-end"><?= e(money($movement->value())) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script type="application/json" id="statsData"><?= json_encode($chartData, $jsonOptions) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/statistics.js') ?>"></script>

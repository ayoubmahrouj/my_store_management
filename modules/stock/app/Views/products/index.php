<?php
/** @var string $term */
/** @var array<int, \Gos\Domain\Entities\Produit> $products */
/** @var array{total: int, quantity: float, value: float, low: int} $summary */
/** @var int $page */
/** @var int $pages */
/** @var bool $showInactive */
/** @var bool $canManage */
/** @var array<string, mixed> $filters */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<int, \Gos\Domain\Entities\Marque> $brands */
/** @var array<int, \App\Domain\Entities\Category> $categories */
/** @var array<int, \App\Domain\Entities\Unit> $units */
$filterQuery = http_build_query(array_filter([
    'q'          => $filters['q'],
    'marque'     => $filters['brand_id'] ?: '',
    'categorie'  => $filters['category_id'] ?: '',
    'unite'      => $filters['unit_id'] ?: '',
    'site'       => $filters['location_id'] ?: '',
    'statut'     => $filters['status'],
    'qte_min'    => $filters['min_qty'],
    'qte_max'    => $filters['max_qty'],
    'valeur_min' => $filters['min_value'],
    'valeur_max' => $filters['max_value'],
    'tri'        => $filters['sort'],
    'inactifs'   => $showInactive ? '1' : '',
], static fn (mixed $value): bool => $value !== '' && $value !== null));
$exportLinks = [
    ['label' => t('Liste des produits'), 'format' => 'xls', 'description' => t('Classeur Excel avec filtres et totaux'), 'url' => url('/produits/export?' . $filterQuery . '&format=xls')],
    ['label' => t('Liste des produits'), 'format' => 'pdf', 'description' => t('Document imprimable, prêt à archiver'), 'url' => url('/produits/export?' . $filterQuery . '&format=pdf')],
    ['label' => t('Liste des produits'), 'format' => 'csv', 'description' => t('Données brutes compatibles tableur'), 'url' => url('/produits/export?' . $filterQuery . '&format=csv')],
];
$pageUrl = static fn (int $target): string => url('/produits?' . ($filterQuery !== '' ? $filterQuery . '&' : '') . 'page=' . $target);
$total = $summary['total'];
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= t('Référentiel') ?></span>
        <h2><?= e(t('Produits & positionnement')) ?></h2>
        <p><?= t('Chaque produit, son CUMP, sa quantité et l’emplacement exact de son stock.') ?></p>
    </div>
    <div class="page-actions">
        <?php include __DIR__ . '/../partials/export-menu.php'; ?>
        <?php if ($canManage): ?>
            <a class="btn btn-accent btn-add" href="<?= url('/produits/nouveau') ?>" data-modal
               title="<?= t('Nouveau produit') ?>" aria-label="<?= t('Nouveau produit') ?>">
                <i class="bi bi-plus-lg"></i>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="mini-stats">
    <div><span><?= t('Produits (filtrés)') ?></span><strong><?= e((string) $summary['total']) ?></strong></div>
    <div><span><?= t('Valeur (filtrée)') ?></span><strong><?= e(money($summary['value'])) ?></strong></div>
    <div><span><?= t('Sous le seuil') ?></span><strong class="<?= $summary['low'] > 0 ? 'text-warning' : '' ?>"><?= e((string) $summary['low']) ?></strong></div>
</div>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3><?= t('Liste des produits') ?></h3>
            <p><?= $term !== '' ? e(t('Résultats pour « :term »', ['term' => $term])) : ($showInactive ? t('Tous les produits, y compris inactifs') : t('Tous les produits actifs')) ?></p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= url('/produits?' . http_build_query(array_filter([
            'q' => $filters['q'], 'marque' => $filters['brand_id'] ?: '', 'categorie' => $filters['category_id'] ?: '',
            'unite' => $filters['unit_id'] ?: '', 'site' => $filters['location_id'] ?: '', 'statut' => $filters['status'],
            'qte_min' => $filters['min_qty'], 'qte_max' => $filters['max_qty'], 'valeur_min' => $filters['min_value'],
            'valeur_max' => $filters['max_value'], 'tri' => $filters['sort'], 'inactifs' => $showInactive ? '' : '1',
        ], static fn (mixed $value): bool => $value !== '' && $value !== null))) ?>">
            <i class="bi <?= $showInactive ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
            <?= $showInactive ? t('Masquer les inactifs') : t('Afficher les inactifs') ?>
        </a>
    </div>
    <div class="filter-panel">
        <form class="filter-form advanced" method="get" action="<?= url('/produits') ?>">
            <?php if ($showInactive): ?><input type="hidden" name="inactifs" value="1"><?php endif; ?>
            <div class="filter-field wide">
                <label for="product-q"><?= t('Recherche') ?></label>
                <div class="input-icon compact">
                    <i class="bi bi-search"></i>
                    <input id="product-q" class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('SKU, désignation, marque, catégorie, code-barres…')) ?>">
                </div>
            </div>
            <div class="filter-field">
                <label for="product-brand"><?= t('Marque') ?></label>
                <select id="product-brand" class="form-select" name="marque" data-quick="brand">
                    <option value=""><?= t('Toutes les marques') ?></option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand->id() ?>" <?= (int) $filters['brand_id'] === $brand->id() ? 'selected' : '' ?>><?= e($brand->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="product-category"><?= t('Catégorie') ?></label>
                <select id="product-category" class="form-select" name="categorie" data-quick="category">
                    <option value=""><?= t('Toutes les catégories') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category->id() ?>" <?= (int) $filters['category_id'] === $category->id() ? 'selected' : '' ?>><?= e($category->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="product-unit"><?= t('Unité') ?></label>
                <select id="product-unit" class="form-select" name="unite" data-quick="unit">
                    <option value=""><?= t('Toutes les unités') ?></option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= $unit->id() ?>" <?= (int) $filters['unit_id'] === $unit->id() ? 'selected' : '' ?>><?= e($unit->code()) ?> · <?= e($unit->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="product-site"><?= t('Site') ?></label>
                <select id="product-site" class="form-select" name="site" data-quick="location">
                    <option value=""><?= t('Tous les sites') ?></option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= (int) $filters['location_id'] === $location->id() ? 'selected' : '' ?>><?= e($location->code()) ?> · <?= e($location->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="product-status"><?= t('Statut') ?></label>
                <select id="product-status" class="form-select" name="statut">
                    <option value=""><?= t('Tous les statuts') ?></option>
                    <option value="ok" <?= $filters['status'] === 'ok' ? 'selected' : '' ?>><?= t('En stock') ?></option>
                    <option value="low" <?= $filters['status'] === 'low' ? 'selected' : '' ?>><?= t('Seuil bas') ?></option>
                    <option value="out" <?= $filters['status'] === 'out' ? 'selected' : '' ?>><?= t('Rupture') ?></option>
                </select>
            </div>
            <div class="filter-field">
                <label for="product-min-qty"><?= t('Qté min') ?></label>
                <input id="product-min-qty" class="form-control" type="number" step="any" min="0" name="qte_min" value="<?= e($filters['min_qty']) ?>" placeholder="0">
            </div>
            <div class="filter-field">
                <label for="product-max-qty"><?= t('Qté max') ?></label>
                <input id="product-max-qty" class="form-control" type="number" step="any" min="0" name="qte_max" value="<?= e($filters['max_qty']) ?>" placeholder="<?= e(t('Illimité')) ?>">
            </div>
            <div class="filter-field">
                <label for="product-min-value"><?= e(t('Valeur min (:currency)', ['currency' => currency()])) ?></label>
                <input id="product-min-value" class="form-control" type="number" step="any" min="0" name="valeur_min" value="<?= e($filters['min_value']) ?>" placeholder="0">
            </div>
            <div class="filter-field">
                <label for="product-max-value"><?= e(t('Valeur max (:currency)', ['currency' => currency()])) ?></label>
                <input id="product-max-value" class="form-control" type="number" step="any" min="0" name="valeur_max" value="<?= e($filters['max_value']) ?>" placeholder="<?= e(t('Illimité')) ?>">
            </div>
            <div class="filter-field">
                <label for="product-sort"><?= t('Tri') ?></label>
                <select id="product-sort" class="form-select" name="tri">
                    <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>><?= t('Désignation') ?></option>
                    <option value="value" <?= $filters['sort'] === 'value' ? 'selected' : '' ?>><?= t('Valeur décroissante') ?></option>
                    <option value="qty" <?= $filters['sort'] === 'qty' ? 'selected' : '' ?>><?= t('Quantité décroissante') ?></option>
                    <option value="low" <?= $filters['sort'] === 'low' ? 'selected' : '' ?>><?= t('Seuil le plus bas') ?></option>
                </select>
            </div>
            <div class="filter-actions">
                <a class="btn btn-ghost" href="<?= url('/produits') ?>"><i class="bi bi-x-lg"></i> <?= t('Réinitialiser') ?></a>
                <button class="btn btn-soft" type="submit"><i class="bi bi-funnel"></i> <?= t('Appliquer les filtres') ?></button>
            </div>
        </form>
    </div>


    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead>
            <tr>
                <th><?= t('Produit') ?></th>
                <th><?= t('Marque / catégorie') ?></th>
                <th><?= t('Positionnement') ?></th>
                <th class="text-end"><?= t('Qté') ?></th>
                <th class="text-end"><?= t('CUMP') ?></th>
                <th class="text-end"><?= t('Valeur') ?></th>
                <th><?= t('Statut') ?></th>
                <?php if ($canManage): ?><th class="text-end"><?= t('Actions') ?></th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php if ($products === []): ?>
                <tr>
                    <td colspan="<?= $canManage ? '8' : '7' ?>">
                        <div class="empty-state">
                            <i class="bi bi-box-seam"></i>
                            <h4><?= t('Aucun produit') ?></h4>
                            <p><?= t('Créez votre premier produit après avoir renseigné les référentiels.') ?></p>
                            <?php if ($canManage): ?>
                                <a class="btn btn-accent btn-add mt-3" href="<?= url('/produits/nouveau') ?>" data-modal
                                   title="<?= t('Nouveau produit') ?>" aria-label="<?= t('Nouveau produit') ?>">
                                    <i class="bi bi-plus-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($products as $product): ?>
                <tr class="<?= $product->isActive() ? '' : 'row-inactive' ?>">
                    <td>
                        <div class="product-cell">
                            <span class="product-avatar"><?= e(mb_substr($product->designation(), 0, 1)) ?></span>
                            <div>
                                <strong><?= e($product->designation()) ?></strong>
                                <small><?= e($product->sku()) ?> · <?= e($product->unit()) ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="strong-soft"><?= e($product->brand()) ?></span>
                        <small class="d-block muted"><?= e($product->category()) ?></small>
                    </td>
                    <td><span class="positions"><?= e($product->positions()) ?></span></td>
                    <td class="text-end"><strong><?= e(qty($product->quantity())) ?></strong></td>
                    <td class="text-end"><?= e(money($product->purchaseCost())) ?></td>
                    <td class="text-end"><strong><?= e(money($product->stockValue())) ?></strong></td>
                    <td>
                        <?php if (!$product->isActive()): ?>
                            <span class="pill pill-muted"><?= t('Inactif') ?></span>
                        <?php elseif ($product->isOutOfStock()): ?>
                            <span class="pill pill-danger"><?= t('Rupture') ?></span>
                        <?php elseif ($product->isLowStock()): ?>
                            <span class="pill pill-warning"><?= t('Seuil bas') ?></span>
                        <?php else: ?>
                            <span class="pill pill-success"><?= t('En stock') ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canManage): ?>
                        <td class="text-end">
                            <div class="table-actions">
                                <a class="btn btn-icon btn-sm" href="<?= url('/produits/' . $product->id() . '/modifier') ?>" title="<?= t('Modifier') ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post" action="<?= url('/produits/' . $product->id() . '/basculer') ?>"
                                      data-confirm="<?= $product->isActive() ? t('Désactiver ce produit ?') : t('Réactiver ce produit ?') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-icon btn-sm" type="submit" title="<?= $product->isActive() ? t('Désactiver') : t('Réactiver') ?>">
                                        <i class="bi <?= $product->isActive() ? 'bi-toggle-on text-success' : 'bi-toggle-off' ?>"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../partials/pager.php'; ?>
</section>

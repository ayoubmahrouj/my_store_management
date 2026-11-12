<?php
/** @var array{total_value: float, total_quantity: float, products: int, low_stock: int, locations: int} $stats */
/** @var array<int, array{name: string, type: string, quantity: float, value: float}> $locations */
/** @var array<int, \App\Domain\Entities\Product> $topProducts */
/** @var array<int, \App\Domain\Entities\Product> $alerts */
/** @var array<string, int> $pending */
/** @var array<int, \App\Domain\Entities\StockMovement> $movements */
/** @var array{locations: int, brands: int, categories: int, units: int, products: int} $setup */
$maxLocationValue = 0.0;
foreach ($locations as $location) {
    $maxLocationValue = max($maxLocationValue, (float) $location['value']);
}
if ($maxLocationValue <= 0) {
    $maxLocationValue = 1;
}
$user = auth_user();
$setupComplete = $setup['locations'] > 0 && $setup['brands'] > 0 && $setup['categories'] > 0 && $setup['units'] > 0 && $setup['products'] > 0;
$pendingTotal = array_sum($pending);
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Vue d’ensemble</span>
        <h2>Bonjour, <?= e($user?->name() ?? '') ?>.</h2>
        <p>Voici l’état de votre stock aujourd’hui.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-soft" href="<?= url('/statistiques') ?>">
            <i class="bi bi-graph-up-arrow"></i> Statistiques
        </a>
        <a class="btn btn-soft" href="<?= url('/rapports/export?export=stock') ?>">
            <i class="bi bi-download"></i> Exporter
        </a>
        <a class="btn btn-accent btn-add" href="<?= url('/entrees/nouveau') ?>" data-modal
           title="Nouvelle entrée" aria-label="Nouvelle entrée">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>
</div>

<?php if (!$setupComplete): ?>
    <section class="panel onboarding-panel">
        <div class="panel-head">
            <div>
                <h3>Démarrez votre stock</h3>
                <p>La base est vide : complétez ces étapes dans l’ordre pour activer tous les modules.</p>
            </div>
            <span class="pill pill-warning">Configuration</span>
        </div>
        <div class="checklist">
            <a class="check-item <?= $setup['locations'] > 0 ? 'done' : '' ?>" href="<?= url('/referentiels') ?>">
                <span class="check-dot"><?= $setup['locations'] > 0 ? '<i class="bi bi-check-lg"></i>' : '1' ?></span>
                <span><strong>Créer un site</strong><small>Dépôt ou point de vente</small></span>
                <em><?= e((string) $setup['locations']) ?></em>
            </a>
            <a class="check-item <?= $setup['brands'] > 0 ? 'done' : '' ?>" href="<?= url('/referentiels') ?>">
                <span class="check-dot"><?= $setup['brands'] > 0 ? '<i class="bi bi-check-lg"></i>' : '2' ?></span>
                <span><strong>Créer une marque</strong><small>Référentiel produits</small></span>
                <em><?= e((string) $setup['brands']) ?></em>
            </a>
            <a class="check-item <?= $setup['categories'] > 0 ? 'done' : '' ?>" href="<?= url('/referentiels') ?>">
                <span class="check-dot"><?= $setup['categories'] > 0 ? '<i class="bi bi-check-lg"></i>' : '3' ?></span>
                <span><strong>Créer une catégorie</strong><small>Famille de produits</small></span>
                <em><?= e((string) $setup['categories']) ?></em>
            </a>
            <a class="check-item <?= $setup['units'] > 0 ? 'done' : '' ?>" href="<?= url('/referentiels') ?>">
                <span class="check-dot"><?= $setup['units'] > 0 ? '<i class="bi bi-check-lg"></i>' : '4' ?></span>
                <span><strong>Créer une unité</strong><small>Pièce, kg, litre…</small></span>
                <em><?= e((string) $setup['units']) ?></em>
            </a>
            <a class="check-item <?= $setup['products'] > 0 ? 'done' : '' ?>" href="<?= url('/produits/nouveau') ?>" data-modal>
                <span class="check-dot"><?= $setup['products'] > 0 ? '<i class="bi bi-check-lg"></i>' : '5' ?></span>
                <span><strong>Créer un produit</strong><small>SKU, coût, prix, seuil</small></span>
                <em><?= e((string) $setup['products']) ?></em>
            </a>
        </div>
    </section>
<?php endif; ?>

<div class="kpi-grid">
    <article class="kpi-card accent">
        <div class="kpi-icon"><i class="bi bi-safe2"></i></div>
        <div class="kpi-body">
            <span class="kpi-label">Capital stock</span>
            <strong class="kpi-value"><?= e(money($stats['total_value'])) ?></strong>
            <a class="kpi-foot" href="<?= url('/statistiques') ?>"><i class="bi bi-arrow-up-right"></i> Suivre l’évolution du capital</a>
        </div>
    </article>

    <article class="kpi-card">
        <div class="kpi-icon blue"><i class="bi bi-box-seam"></i></div>
        <div class="kpi-body">
            <span class="kpi-label">Produits actifs</span>
            <strong class="kpi-value"><?= e((string) $stats['products']) ?></strong>
            <span class="kpi-foot"><?= e(qty($stats['total_quantity'])) ?> unités en stock</span>
        </div>
    </article>

    <article class="kpi-card">
        <div class="kpi-icon amber"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="kpi-body">
            <span class="kpi-label">Alertes stock</span>
            <strong class="kpi-value"><?= e((string) $stats['low_stock']) ?></strong>
            <span class="kpi-foot"><?= $stats['low_stock'] > 0 ? 'Produits sous le seuil' : 'Aucune alerte' ?></span>
        </div>
    </article>

    <article class="kpi-card">
        <div class="kpi-icon violet"><i class="bi bi-geo-alt"></i></div>
        <div class="kpi-body">
            <span class="kpi-label">Sites actifs</span>
            <strong class="kpi-value"><?= e((string) $stats['locations']) ?></strong>
            <span class="kpi-foot">Dépôts &amp; points de vente</span>
        </div>
    </article>
</div>

<div class="grid-2">
    <section class="panel">
        <div class="panel-head">
            <div><h3>Répartition par site</h3><p>Valeur du stock immobilisé</p></div>
            <span class="pill pill-muted"><?= count($locations) ?> site(s)</span>
        </div>
        <div class="site-list">
            <?php if ($locations === []): ?>
                <div class="empty-state compact"><i class="bi bi-geo"></i><p>Aucun site configuré.</p></div>
            <?php endif; ?>
            <?php foreach ($locations as $location): ?>
                <?php $width = max(2, (int) round(((float) $location['value'] / $maxLocationValue) * 100)); ?>
                <div class="site-row">
                    <div class="site-info">
                        <span class="site-icon <?= $location['type'] === 'depot' ? 'depot' : 'pos' ?>">
                            <i class="bi bi-<?= $location['type'] === 'depot' ? 'building' : 'shop' ?>"></i>
                        </span>
                        <div>
                            <strong><?= e($location['name']) ?></strong>
                            <small><?= e(qty($location['quantity'])) ?> unités · <?= $location['type'] === 'depot' ? 'Dépôt' : 'Point de vente' ?></small>
                        </div>
                    </div>
                    <div class="site-value">
                        <strong><?= e(money($location['value'])) ?></strong>
                        <span class="bar"><i style="width: <?= $width ?>%"></i></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3>Alertes de stock</h3><p>Produits au seuil ou en rupture</p></div>
            <?php if ($alerts !== []): ?><span class="pill pill-warning"><?= count($alerts) ?> à traiter</span><?php endif; ?>
        </div>
        <div class="alert-list">
            <?php if ($alerts === []): ?>
                <div class="empty-state compact"><i class="bi bi-shield-check"></i><p>Tous les stocks sont au-dessus du seuil.</p></div>
            <?php endif; ?>
            <?php foreach ($alerts as $product): ?>
                <div class="alert-row">
                    <span class="alert-icon <?= $product->isOutOfStock() ? 'danger' : 'warning' ?>">
                        <i class="bi bi-<?= $product->isOutOfStock() ? 'x-octagon' : 'exclamation-triangle' ?>"></i>
                    </span>
                    <div class="alert-info">
                        <strong><?= e($product->designation()) ?></strong>
                        <small><?= e($product->sku()) ?> · seuil <?= e(qty($product->minStock())) ?> <?= e($product->unit()) ?></small>
                    </div>
                    <span class="qty-badge <?= $product->isOutOfStock() ? 'danger' : 'warning' ?>"><?= e(qty($product->quantity())) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="grid-2">
    <section class="panel">
        <div class="panel-head">
            <div><h3>À traiter</h3><p>Brouillons en attente de validation</p></div>
            <span class="pill <?= $pendingTotal > 0 ? 'pill-warning' : 'pill-muted' ?>"><?= e((string) $pendingTotal) ?></span>
        </div>
        <div class="pending-grid">
            <a class="pending-card" href="<?= url('/entrees?statut=brouillon') ?>">
                <i class="bi bi-box-arrow-in-down"></i>
                <span>Entrées</span>
                <strong><?= e((string) ($pending['entree'] ?? 0)) ?></strong>
            </a>
            <a class="pending-card" href="<?= url('/sorties?statut=brouillon') ?>">
                <i class="bi bi-box-arrow-up"></i>
                <span>Sorties</span>
                <strong><?= e((string) ($pending['sortie'] ?? 0)) ?></strong>
            </a>
            <a class="pending-card" href="<?= url('/transferts?statut=brouillon') ?>">
                <i class="bi bi-arrow-left-right"></i>
                <span>Transferts</span>
                <strong><?= e((string) ($pending['transfert'] ?? 0)) ?></strong>
            </a>
            <a class="pending-card" href="<?= url('/inventaire') ?>">
                <i class="bi bi-clipboard-check"></i>
                <span>Inventaires</span>
                <strong><?= e((string) ($pending['inventaire'] ?? 0)) ?></strong>
            </a>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h3>Derniers mouvements</h3><p>Activité récente du stock</p></div>
            <a class="link-arrow" href="<?= url('/rapports') ?>">Journal <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="movement-list">
            <?php if ($movements === []): ?>
                <div class="empty-state compact"><i class="bi bi-activity"></i><p>Aucun mouvement pour le moment.</p></div>
            <?php endif; ?>
            <?php foreach ($movements as $movement): ?>
                <div class="movement-row">
                    <span class="movement-icon <?= $movement->isIn() ? 'in' : 'out' ?>">
                        <i class="bi bi-arrow-<?= $movement->isIn() ? 'down' : 'up' ?>"></i>
                    </span>
                    <div class="movement-info">
                        <strong><?= e($movement->product()) ?></strong>
                        <small><?= e($movement->location()) ?> · <?= e(date('d/m/Y H:i', strtotime($movement->occurredAt()))) ?></small>
                    </div>
                    <span class="movement-qty <?= $movement->isIn() ? 'in' : 'out' ?>">
                        <?= $movement->isIn() ? '+' : '−' ?><?= e(qty($movement->qty())) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h3>Top produits par valeur</h3><p>Les plus gros postes de votre capital stock</p></div>
        <a class="link-arrow" href="<?= url('/produits') ?>">Voir tous les produits <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="table-responsive">
        <table class="table table-premium align-middle">
            <thead>
            <tr>
                <th>Produit</th>
                <th>Positionnement</th>
                <th class="text-end">Quantité</th>
                <th class="text-end">Valeur</th>
                <th>Statut</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($topProducts === []): ?>
                <tr><td colspan="5"><div class="empty-state compact"><i class="bi bi-box"></i><p>Aucun produit pour le moment.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($topProducts as $product): ?>
                <tr>
                    <td>
                        <div class="product-cell">
                            <span class="product-avatar"><?= e(mb_substr($product->designation(), 0, 1)) ?></span>
                            <div>
                                <strong><?= e($product->designation()) ?></strong>
                                <small><?= e($product->sku()) ?> · <?= e($product->brand()) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><span class="positions"><?= e($product->positions()) ?></span></td>
                    <td class="text-end"><strong><?= e(qty($product->quantity())) ?></strong> <small class="muted"><?= e($product->unit()) ?></small></td>
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
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

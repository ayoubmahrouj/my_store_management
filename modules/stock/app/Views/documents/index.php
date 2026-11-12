<?php
/** @var string $type */
/** @var string $label */
/** @var string $route */
/** @var array<int, \App\Domain\Entities\StockDocument> $documents */
/** @var array{draft: int, done: int, shipped: int, total: int, total_cost: float} $stats */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<int, \App\Domain\Entities\Supplier> $suppliers */
/** @var array<int, array{id: int, sku: string, designation: string, unit: string, purchase_cost: float, sale_price: float}> $products */
/** @var array<string, string> $motifs */
/** @var array{q: string, status: string, location_id: int, date_from: string, date_to: string, motif: string, supplier_id: int, product_id: int, amount_from: string, amount_to: string} $filters */
$createLabel = match ($type) {
    'entree' => 'Nouvelle entrée',
    'sortie' => 'Nouvelle sortie',
    'transfert' => 'Nouveau transfert',
    default => 'Nouveau document',
};
$counterparty = match ($type) {
    'entree' => 'Fournisseur',
    'transfert' => 'Destination',
    default => 'Motif',
};
$filterQuery = http_build_query(array_filter([
    'q'            => $filters['q'],
    'statut'       => $filters['status'],
    'site'         => $filters['location_id'] ?: '',
    'du'           => $filters['date_from'],
    'au'           => $filters['date_to'],
    'motif'        => $filters['motif'],
    'fournisseur'  => $filters['supplier_id'] ?: '',
    'produit'      => $filters['product_id'] ?: '',
    'montant_min'  => $filters['amount_from'],
    'montant_max'  => $filters['amount_to'],
], static fn (mixed $value): bool => $value !== '' && $value !== null));
$baseExport = url($route . '/export?' . $filterQuery);
$exportLinks = [
    ['label' => 'Registre des documents', 'format' => 'xls', 'description' => 'En-têtes, montants et statuts', 'url' => $baseExport . '&format=xls'],
    ['label' => 'Registre des documents', 'format' => 'pdf', 'description' => 'Version imprimable du registre', 'url' => $baseExport . '&format=pdf'],
    ['label' => 'Registre des documents', 'format' => 'csv', 'description' => 'Données brutes du registre', 'url' => $baseExport . '&format=csv'],
    ['label' => 'Détail des lignes', 'format' => 'xls', 'description' => 'Un produit par ligne, quantités et prix', 'url' => $baseExport . '&detail=1&format=xls'],
    ['label' => 'Détail des lignes', 'format' => 'pdf', 'description' => 'Capital détaillé par produit et site', 'url' => $baseExport . '&detail=1&format=pdf'],
    ['label' => 'Détail des lignes', 'format' => 'csv', 'description' => 'Détail exploitable dans un tableur', 'url' => $baseExport . '&detail=1&format=csv'],
];
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Flux de stock</span>
        <h2><?= e($label) ?></h2>
        <p><?= $type === 'transfert'
            ? 'Expédiez du stock vers un autre site, puis réceptionnez-le avec traçabilité complète.'
            : 'Chaque document impacte le stock réel uniquement après validation.' ?></p>
    </div>
    <div class="page-actions">
        <?php include __DIR__ . '/../partials/export-menu.php'; ?>
        <a class="btn btn-accent btn-add" href="<?= url($route . '/nouveau') ?>" data-modal
           title="<?= e($createLabel) ?>" aria-label="<?= e($createLabel) ?>">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>
</div>

<div class="mini-stats cols-4">
    <div><span>Brouillons</span><strong class="<?= $stats['draft'] > 0 ? 'text-warning' : '' ?>"><?= e((string) $stats['draft']) ?></strong></div>
    <div><span><?= $type === 'transfert' ? 'Expédiés / reçus' : 'Validés' ?></span><strong><?= e((string) ($stats['done'] + ($type === 'transfert' ? $stats['shipped'] : 0))) ?></strong></div>
    <div><span>Total documents</span><strong><?= e((string) $stats['total']) ?></strong></div>
    <div><span>Valeur traitée</span><strong><?= e(money($stats['total_cost'])) ?></strong></div>
</div>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3>Registre des <?= e(mb_strtolower($label)) ?></h3>
            <p><?= count($documents) ?> document(s) affiché(s) selon les critères</p>
        </div>
    </div>
    <div class="filter-panel">
        <form class="filter-form advanced" method="get" action="<?= url($route) ?>">
            <div class="filter-field wide">
                <label for="doc-q">Recherche</label>
                <div class="input-icon compact">
                    <i class="bi bi-search"></i>
                    <input id="doc-q" class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="N°, référence, site, fournisseur, produit, SKU…">
                </div>
            </div>
            <div class="filter-field">
                <label for="doc-status">Statut</label>
                <select id="doc-status" class="form-select" name="statut">
                    <option value="">Tous les statuts</option>
                    <?php foreach (['brouillon' => 'Brouillon', 'valide' => 'Validé', 'expedie' => 'Expédié', 'recu' => 'Reçu', 'annule' => 'Annulé'] as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="doc-site">Site</label>
                <select id="doc-site" class="form-select" name="site" data-quick="location">
                    <option value="">Tous les sites</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= $filters['location_id'] === $location->id() ? 'selected' : '' ?>><?= e($location->code()) ?> · <?= e($location->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($type === 'entree'): ?>
                <div class="filter-field">
                    <label for="doc-supplier">Fournisseur</label>
                    <select id="doc-supplier" class="form-select" name="fournisseur" data-quick="supplier">
                        <option value="">Tous les fournisseurs</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier->id() ?>" <?= $filters['supplier_id'] === $supplier->id() ? 'selected' : '' ?>><?= e($supplier->name()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="filter-field wide">
                <label for="doc-product">Produit concerné</label>
                <select id="doc-product" class="form-select" name="produit" data-quick="product">
                    <option value="">Tous les produits</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id'] ?>" <?= $filters['product_id'] === (int) $product['id'] ? 'selected' : '' ?>>
                            <?= e($product['sku']) ?> · <?= e($product['designation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="doc-motif">Motif</label>
                <select id="doc-motif" class="form-select" name="motif">
                    <option value="">Tous les motifs</option>
                    <?php foreach ($motifs as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= $filters['motif'] === $value ? 'selected' : '' ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="doc-from">Du</label>
                <input id="doc-from" class="form-control" type="date" name="du" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="filter-field">
                <label for="doc-to">Au</label>
                <input id="doc-to" class="form-control" type="date" name="au" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="filter-field">
                <label for="doc-amount-min">Montant min (<?= e(currency()) ?>)</label>
                <input id="doc-amount-min" class="form-control" type="number" step="any" min="0" name="montant_min" value="<?= e($filters['amount_from']) ?>" placeholder="0">
            </div>
            <div class="filter-field">
                <label for="doc-amount-max">Montant max (<?= e(currency()) ?>)</label>
                <input id="doc-amount-max" class="form-control" type="number" step="any" min="0" name="montant_max" value="<?= e($filters['amount_to']) ?>" placeholder="Illimité">
            </div>
            <div class="filter-actions">
                <a class="btn btn-ghost" href="<?= url($route) ?>"><i class="bi bi-x-lg"></i> Réinitialiser</a>
                <button class="btn btn-soft" type="submit"><i class="bi bi-funnel"></i> Appliquer les filtres</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead>
            <tr>
                <th>Document</th>
                <th>Date</th>
                <th>Motif</th>
                <th>Site</th>
                <th><?= e($counterparty) ?></th>
                <th class="text-end">Lignes</th>
                <th class="text-end">Valeur</th>
                <th>Statut</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($documents === []): ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="bi bi-inboxes"></i>
                            <h4>Aucun document pour le moment</h4>
                            <p>Créez votre premier document pour démarrer le suivi du stock.</p>
                            <a class="btn btn-accent btn-add mt-3" href="<?= url($route . '/nouveau') ?>" data-modal
                               title="<?= e($createLabel) ?>" aria-label="<?= e($createLabel) ?>">
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($documents as $document): ?>
                <tr>
                    <td>
                        <a class="doc-number" href="<?= url($route . '/' . $document->id()) ?>"><?= e($document->numero()) ?></a>
                        <?php if ($document->reference() !== null): ?><small class="d-block muted"><?= e($document->reference()) ?></small><?php endif; ?>
                    </td>
                    <td><?= e(date('d/m/Y', strtotime($document->docDate()))) ?></td>
                    <td><span class="strong-soft"><?= e($document->motif() ?? '—') ?></span></td>
                    <td><?= e($document->locationName()) ?></td>
                    <td>
                        <?php if ($type === 'entree'): ?>
                            <?= e($document->supplierName() ?? '—') ?>
                        <?php elseif ($type === 'transfert'): ?>
                            <span class="dest-cell"><i class="bi bi-arrow-right"></i> <?= e($document->destLocationName() ?? '—') ?></span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= e((string) $document->lineCount()) ?></td>
                    <td class="text-end"><strong><?= e(money($document->totalCost())) ?></strong></td>
                    <td><span class="pill <?= e(status_class($document->status())) ?>"><?= e($document->statusLabel()) ?></span></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a class="btn btn-ghost btn-sm" href="<?= url($route . '/' . $document->id() . '/export?format=xls') ?>" title="Exporter en Excel">
                                <i class="bi bi-file-earmark-excel"></i>
                            </a>
                            <a class="btn btn-ghost btn-sm" href="<?= url($route . '/' . $document->id() . '/export?format=pdf') ?>" title="Exporter en PDF">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                            <a class="btn btn-soft btn-sm" href="<?= url($route . '/' . $document->id()) ?>">
                                <i class="bi bi-eye"></i> Voir
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

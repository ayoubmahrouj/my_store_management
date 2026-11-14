<?php
/** @var string $type */
/** @var string $label */
/** @var string $route */
/** @var array<int, \App\Domain\Entities\StockDocument> $documents */
/** @var array{draft: int, done: int, shipped: int, total: int, total_cost: float} $stats */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array<int, \Gos\Domain\Entities\Fournisseur> $suppliers */
/** @var array<string, string> $motifs */
/** @var array{q: string, status: string, location_id: int, date_from: string, date_to: string, motif: string, supplier_id: int, product_q: string, amount_from: string, amount_to: string} $filters */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
$createLabel = match ($type) {
    'entree' => t('Nouvelle entrée'),
    'sortie' => t('Nouvelle sortie'),
    'transfert' => t('Nouveau transfert'),
    default => t('Nouveau document'),
};
$counterparty = match ($type) {
    'entree' => t('Fournisseur'),
    'transfert' => t('Destination'),
    default => t('Motif'),
};
$filterQuery = http_build_query(array_filter([
    'q'            => $filters['q'],
    'statut'       => $filters['status'],
    'site'         => $filters['location_id'] ?: '',
    'du'           => $filters['date_from'],
    'au'           => $filters['date_to'],
    'motif'        => $filters['motif'],
    'fournisseur'  => $filters['supplier_id'] ?: '',
    'produit'      => $filters['product_q'],
    'montant_min'  => $filters['amount_from'],
    'montant_max'  => $filters['amount_to'],
], static fn (mixed $value): bool => $value !== '' && $value !== null));
$baseExport = url($route . '/export?' . $filterQuery);
$exportLinks = [
    ['label' => t('Registre des documents'), 'format' => 'xls', 'description' => t('En-têtes, montants et statuts'), 'url' => $baseExport . '&format=xls'],
    ['label' => t('Registre des documents'), 'format' => 'pdf', 'description' => t('Version imprimable du registre'), 'url' => $baseExport . '&format=pdf'],
    ['label' => t('Registre des documents'), 'format' => 'csv', 'description' => t('Données brutes du registre'), 'url' => $baseExport . '&format=csv'],
    ['label' => t('Détail des lignes'), 'format' => 'xls', 'description' => t('Un produit par ligne, quantités et prix'), 'url' => $baseExport . '&detail=1&format=xls'],
    ['label' => t('Détail des lignes'), 'format' => 'pdf', 'description' => t('Capital détaillé par produit et site'), 'url' => $baseExport . '&detail=1&format=pdf'],
    ['label' => t('Détail des lignes'), 'format' => 'csv', 'description' => t('Détail exploitable dans un tableur'), 'url' => $baseExport . '&detail=1&format=csv'],
];
$pageUrl = static fn (int $target): string => url($route . '?' . ($filterQuery !== '' ? $filterQuery . '&' : '') . 'page=' . $target);
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= t('Flux de stock') ?></span>
        <h2><?= e($label) ?></h2>
        <p><?= $type === 'transfert'
            ? t('Expédiez du stock vers un autre site, puis réceptionnez-le avec traçabilité complète.')
            : t('Chaque document impacte le stock réel uniquement après validation.') ?></p>
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
    <div><span><?= t('Brouillons') ?></span><strong class="<?= $stats['draft'] > 0 ? 'text-warning' : '' ?>"><?= e((string) $stats['draft']) ?></strong></div>
    <div><span><?= $type === 'transfert' ? t('Expédiés / reçus') : t('Validés') ?></span><strong><?= e((string) ($stats['done'] + ($type === 'transfert' ? $stats['shipped'] : 0))) ?></strong></div>
    <div><span><?= t('Total documents') ?></span><strong><?= e((string) $stats['total']) ?></strong></div>
    <div><span><?= t('Valeur traitée') ?></span><strong><?= e(money($stats['total_cost'])) ?></strong></div>
</div>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3><?= e(t('Registre des :label', ['label' => mb_strtolower($label)])) ?></h3>
            <p><?= t(':count document(s) affiché(s) selon les critères', ['count' => count($documents)]) ?></p>
        </div>
    </div>
    <div class="filter-panel">
        <form class="filter-form advanced" method="get" action="<?= url($route) ?>">
            <div class="filter-field wide">
                <label for="doc-q"><?= t('Recherche') ?></label>
                <div class="input-icon compact">
                    <i class="bi bi-search"></i>
                    <input id="doc-q" class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('N°, référence, site, fournisseur, produit, SKU…')) ?>">
                </div>
            </div>
            <div class="filter-field">
                <label for="doc-status"><?= t('Statut') ?></label>
                <select id="doc-status" class="form-select" name="statut">
                    <option value=""><?= t('Tous les statuts') ?></option>
                    <?php foreach (['brouillon' => t('Brouillon'), 'valide' => t('Validé'), 'expedie' => t('Expédié'), 'recu' => t('Reçu'), 'annule' => t('Annulé')] as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="doc-site"><?= t('Site') ?></label>
                <select id="doc-site" class="form-select" name="site" data-quick="location">
                    <option value=""><?= t('Tous les sites') ?></option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= $filters['location_id'] === $location->id() ? 'selected' : '' ?>><?= e($location->code()) ?> · <?= e($location->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($type === 'entree'): ?>
                <div class="filter-field">
                    <label for="doc-supplier"><?= t('Fournisseur') ?></label>
                    <select id="doc-supplier" class="form-select" name="fournisseur" data-quick="supplier">
                        <option value=""><?= t('Tous les fournisseurs') ?></option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier->id() ?>" <?= $filters['supplier_id'] === $supplier->id() ? 'selected' : '' ?>><?= e($supplier->name()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="filter-field wide">
                <label for="doc-product"><?= t('Produit concerné') ?></label>
                <input id="doc-product" class="form-control" type="text" name="produit"
                       value="<?= e($filters['product_q']) ?>" placeholder="<?= e(t('SKU ou nom du produit…')) ?>">
            </div>
            <div class="filter-field">
                <label for="doc-motif"><?= t('Motif') ?></label>
                <select id="doc-motif" class="form-select" name="motif">
                    <option value=""><?= t('Tous les motifs') ?></option>
                    <?php foreach ($motifs as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= $filters['motif'] === $value ? 'selected' : '' ?>><?= e(t($text)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="doc-from"><?= t('Du') ?></label>
                <input id="doc-from" class="form-control" type="date" name="du" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="filter-field">
                <label for="doc-to"><?= t('Au') ?></label>
                <input id="doc-to" class="form-control" type="date" name="au" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="filter-field">
                <label for="doc-amount-min"><?= e(t('Montant min (:currency)', ['currency' => currency()])) ?></label>
                <input id="doc-amount-min" class="form-control" type="number" step="any" min="0" name="montant_min" value="<?= e($filters['amount_from']) ?>" placeholder="0">
            </div>
            <div class="filter-field">
                <label for="doc-amount-max"><?= e(t('Montant max (:currency)', ['currency' => currency()])) ?></label>
                <input id="doc-amount-max" class="form-control" type="number" step="any" min="0" name="montant_max" value="<?= e($filters['amount_to']) ?>" placeholder="<?= e(t('Illimité')) ?>">
            </div>
            <div class="filter-actions">
                <a class="btn btn-ghost" href="<?= url($route) ?>"><i class="bi bi-x-lg"></i> <?= t('Réinitialiser') ?></a>
                <button class="btn btn-soft" type="submit"><i class="bi bi-funnel"></i> <?= t('Appliquer les filtres') ?></button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead>
            <tr>
                <th><?= t('Document') ?></th>
                <th><?= t('Date') ?></th>
                <th><?= t('Motif') ?></th>
                <th><?= t('Site') ?></th>
                <th><?= e($counterparty) ?></th>
                <th class="text-end"><?= t('Lignes') ?></th>
                <th class="text-end"><?= t('Valeur') ?></th>
                <th><?= t('Statut') ?></th>
                <th class="text-end"><?= t('Action') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($documents === []): ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="bi bi-inboxes"></i>
                            <h4><?= t('Aucun document pour le moment') ?></h4>
                            <p><?= t('Créez votre premier document pour démarrer le suivi du stock.') ?></p>
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
                            <a class="btn btn-ghost btn-sm" href="<?= url($route . '/' . $document->id() . '/export?format=xls') ?>" title="<?= e(t('Exporter en Excel')) ?>">
                                <i class="bi bi-file-earmark-excel"></i>
                            </a>
                            <a class="btn btn-ghost btn-sm" href="<?= url($route . '/' . $document->id() . '/export?format=pdf') ?>" title="<?= e(t('Exporter en PDF')) ?>">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                            <a class="btn btn-soft btn-sm" href="<?= url($route . '/' . $document->id()) ?>">
                                <i class="bi bi-eye"></i> <?= t('Voir') ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include __DIR__ . '/../partials/pager.php'; ?>
</section>

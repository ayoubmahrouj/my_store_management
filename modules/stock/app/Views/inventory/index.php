<?php
/** @var array<int, \App\Domain\Entities\InventorySession> $sessions */
/** @var array{open: int, counting: int, validated: int, total: int} $stats */
/** @var array<int, \App\Domain\Entities\Location> $locations */
/** @var array{q: string, status: string, location_id: int} $filters */
$filterQuery = http_build_query(array_filter([
    'q'      => $filters['q'],
    'statut' => $filters['status'],
    'site'   => $filters['location_id'] ?: '',
], static fn (mixed $value): bool => $value !== '' && $value !== null));
$exportBase = url('/inventaire/export?' . $filterQuery);
$exportLinks = [
    ['label' => t('Registre des inventaires'), 'format' => 'xls', 'description' => t('Sessions, avancement et écarts'), 'url' => $exportBase . '&format=xls'],
    ['label' => t('Registre des inventaires'), 'format' => 'pdf', 'description' => t('Synthèse imprimable des sessions'), 'url' => $exportBase . '&format=pdf'],
    ['label' => t('Registre des inventaires'), 'format' => 'csv', 'description' => t('Données brutes des sessions'), 'url' => $exportBase . '&format=csv'],
    ['label' => t('Détail des comptages'), 'format' => 'xls', 'description' => t('Théorique, compté et écart par produit'), 'url' => $exportBase . '&detail=1&format=xls'],
    ['label' => t('Détail des comptages'), 'format' => 'pdf', 'description' => t('Écarts valorisés ligne par ligne'), 'url' => $exportBase . '&detail=1&format=pdf'],
    ['label' => t('Détail des comptages'), 'format' => 'csv', 'description' => t('Détail exploitable dans un tableur'), 'url' => $exportBase . '&detail=1&format=csv'],
];
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= t('Contrôle physique') ?></span>
        <h2><?= t('Inventaires') ?></h2>
        <p><?= t('Comptez le stock d’un site, analysez les écarts et validez les ajustements.') ?></p>
    </div>
    <div class="page-actions">
        <?php include __DIR__ . '/../partials/export-menu.php'; ?>
        <a class="btn btn-accent btn-add" href="<?= url('/inventaire/nouveau') ?>" data-modal
           title="<?= e(t('Nouvel inventaire')) ?>" aria-label="<?= e(t('Nouvel inventaire')) ?>">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>
</div>

<div class="mini-stats cols-4">
    <div><span><?= t('Ouverts') ?></span><strong class="<?= $stats['open'] > 0 ? 'text-warning' : '' ?>"><?= e((string) $stats['open']) ?></strong></div>
    <div><span><?= t('En comptage') ?></span><strong class="<?= $stats['counting'] > 0 ? 'text-warning' : '' ?>"><?= e((string) $stats['counting']) ?></strong></div>
    <div><span><?= t('Validés') ?></span><strong><?= e((string) $stats['validated']) ?></strong></div>
    <div><span><?= t('Total sessions') ?></span><strong><?= e((string) $stats['total']) ?></strong></div>
</div>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3><?= t('Historique des inventaires') ?></h3>
            <p><?= t(':count session(s) affichée(s)', ['count' => count($sessions)]) ?></p>
        </div>
    </div>
    <div class="filter-panel">
        <form class="filter-form advanced" method="get" action="<?= url('/inventaire') ?>">
            <div class="filter-field wide">
                <label for="inventory-q"><?= t('Recherche') ?></label>
                <div class="input-icon compact">
                    <i class="bi bi-search"></i>
                    <input id="inventory-q" class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('N° d’inventaire ou site…')) ?>">
                </div>
            </div>
            <div class="filter-field">
                <label for="inventory-status"><?= t('Statut') ?></label>
                <select id="inventory-status" class="form-select" name="statut">
                    <option value=""><?= t('Tous les statuts') ?></option>
                    <?php foreach (['ouvert' => 'Ouvert', 'comptage' => 'Comptage', 'valide' => 'Validé', 'annule' => 'Annulé'] as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e(t($text)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="inventory-site"><?= t('Site') ?></label>
                <select id="inventory-site" class="form-select" name="site" data-quick="location">
                    <option value=""><?= t('Tous les sites') ?></option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= $filters['location_id'] === $location->id() ? 'selected' : '' ?>><?= e($location->code()) ?> · <?= e($location->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <a class="btn btn-ghost" href="<?= url('/inventaire') ?>"><i class="bi bi-x-lg"></i> <?= t('Réinitialiser') ?></a>
                <button class="btn btn-soft" type="submit"><i class="bi bi-funnel"></i> <?= t('Appliquer les filtres') ?></button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead>
            <tr>
                <th><?= t('Inventaire') ?></th>
                <th><?= t('Site') ?></th>
                <th><?= t('Démarré par') ?></th>
                <th><?= t('Date') ?></th>
                <th class="text-end"><?= t('Comptés / lignes') ?></th>
                <th class="text-end"><?= t('Écart valorisé') ?></th>
                <th><?= t('Statut') ?></th>
                <th class="text-end"><?= t('Action') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($sessions === []): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="bi bi-clipboard-check"></i>
                            <h4><?= t('Aucun inventaire') ?></h4>
                            <p><?= t('Lancez une session pour comparer le stock théorique au stock physique.') ?></p>
                            <a class="btn btn-accent btn-add mt-3" href="<?= url('/inventaire/nouveau') ?>" data-modal
                               title="<?= e(t('Nouvel inventaire')) ?>" aria-label="<?= e(t('Nouvel inventaire')) ?>">
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><a class="doc-number" href="<?= url('/inventaire/' . $session->id()) ?>"><?= e($session->numero()) ?></a></td>
                    <td><?= e($session->locationName()) ?></td>
                    <td><?= e($session->startedByName()) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($session->startedAt()))) ?></td>
                    <td class="text-end"><?= e((string) $session->countedLineCount()) ?> / <?= e((string) $session->lineCount()) ?></td>
                    <td class="text-end <?= $session->gapValueTotal() < 0 ? 'text-danger' : '' ?>">
                        <strong><?= e(money($session->gapValueTotal())) ?></strong>
                    </td>
                    <td><span class="pill <?= e(status_class($session->status())) ?>"><?= e($session->statusLabel()) ?></span></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a class="btn btn-ghost btn-sm" href="<?= url('/inventaire/' . $session->id() . '/export?format=xls') ?>" title="<?= e(t('Exporter en Excel')) ?>">
                                <i class="bi bi-file-earmark-excel"></i>
                            </a>
                            <a class="btn btn-ghost btn-sm" href="<?= url('/inventaire/' . $session->id() . '/export?format=pdf') ?>" title="<?= e(t('Exporter en PDF')) ?>">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                            <a class="btn btn-soft btn-sm" href="<?= url('/inventaire/' . $session->id()) ?>">
                                <i class="bi bi-eye"></i> <?= t('Ouvrir') ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

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
    ['label' => 'Registre des inventaires', 'format' => 'xls', 'description' => 'Sessions, avancement et écarts', 'url' => $exportBase . '&format=xls'],
    ['label' => 'Registre des inventaires', 'format' => 'pdf', 'description' => 'Synthèse imprimable des sessions', 'url' => $exportBase . '&format=pdf'],
    ['label' => 'Registre des inventaires', 'format' => 'csv', 'description' => 'Données brutes des sessions', 'url' => $exportBase . '&format=csv'],
    ['label' => 'Détail des comptages', 'format' => 'xls', 'description' => 'Théorique, compté et écart par produit', 'url' => $exportBase . '&detail=1&format=xls'],
    ['label' => 'Détail des comptages', 'format' => 'pdf', 'description' => 'Écarts valorisés ligne par ligne', 'url' => $exportBase . '&detail=1&format=pdf'],
    ['label' => 'Détail des comptages', 'format' => 'csv', 'description' => 'Détail exploitable dans un tableur', 'url' => $exportBase . '&detail=1&format=csv'],
];
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Contrôle physique</span>
        <h2>Inventaires</h2>
        <p>Comptez le stock d’un site, analysez les écarts et validez les ajustements.</p>
    </div>
    <div class="page-actions">
        <?php include __DIR__ . '/../partials/export-menu.php'; ?>
        <a class="btn btn-accent btn-add" href="<?= url('/inventaire/nouveau') ?>" data-modal
           title="Nouvel inventaire" aria-label="Nouvel inventaire">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>
</div>

<div class="mini-stats cols-4">
    <div><span>Ouverts</span><strong class="<?= $stats['open'] > 0 ? 'text-warning' : '' ?>"><?= e((string) $stats['open']) ?></strong></div>
    <div><span>En comptage</span><strong class="<?= $stats['counting'] > 0 ? 'text-warning' : '' ?>"><?= e((string) $stats['counting']) ?></strong></div>
    <div><span>Validés</span><strong><?= e((string) $stats['validated']) ?></strong></div>
    <div><span>Total sessions</span><strong><?= e((string) $stats['total']) ?></strong></div>
</div>

<section class="panel">
    <div class="panel-head panel-head-wrap">
        <div>
            <h3>Historique des inventaires</h3>
            <p><?= count($sessions) ?> session(s) affichée(s)</p>
        </div>
    </div>
    <div class="filter-panel">
        <form class="filter-form advanced" method="get" action="<?= url('/inventaire') ?>">
            <div class="filter-field wide">
                <label for="inventory-q">Recherche</label>
                <div class="input-icon compact">
                    <i class="bi bi-search"></i>
                    <input id="inventory-q" class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="N° d’inventaire ou site…">
                </div>
            </div>
            <div class="filter-field">
                <label for="inventory-status">Statut</label>
                <select id="inventory-status" class="form-select" name="statut">
                    <option value="">Tous les statuts</option>
                    <?php foreach (['ouvert' => 'Ouvert', 'comptage' => 'Comptage', 'valide' => 'Validé', 'annule' => 'Annulé'] as $value => $text): ?>
                        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="inventory-site">Site</label>
                <select id="inventory-site" class="form-select" name="site" data-quick="location">
                    <option value="">Tous les sites</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= $location->id() ?>" <?= $filters['location_id'] === $location->id() ? 'selected' : '' ?>><?= e($location->code()) ?> · <?= e($location->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <a class="btn btn-ghost" href="<?= url('/inventaire') ?>"><i class="bi bi-x-lg"></i> Réinitialiser</a>
                <button class="btn btn-soft" type="submit"><i class="bi bi-funnel"></i> Appliquer les filtres</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-premium table-hover align-middle">
            <thead>
            <tr>
                <th>Inventaire</th>
                <th>Site</th>
                <th>Démarré par</th>
                <th>Date</th>
                <th class="text-end">Comptés / lignes</th>
                <th class="text-end">Écart valorisé</th>
                <th>Statut</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($sessions === []): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="bi bi-clipboard-check"></i>
                            <h4>Aucun inventaire</h4>
                            <p>Lancez une session pour comparer le stock théorique au stock physique.</p>
                            <a class="btn btn-accent btn-add mt-3" href="<?= url('/inventaire/nouveau') ?>" data-modal
                               title="Nouvel inventaire" aria-label="Nouvel inventaire">
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
                            <a class="btn btn-ghost btn-sm" href="<?= url('/inventaire/' . $session->id() . '/export?format=xls') ?>" title="Exporter en Excel">
                                <i class="bi bi-file-earmark-excel"></i>
                            </a>
                            <a class="btn btn-ghost btn-sm" href="<?= url('/inventaire/' . $session->id() . '/export?format=pdf') ?>" title="Exporter en PDF">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                            <a class="btn btn-soft btn-sm" href="<?= url('/inventaire/' . $session->id()) ?>">
                                <i class="bi bi-eye"></i> Ouvrir
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

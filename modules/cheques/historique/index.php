<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Historique';
$active = 'historique';

$q = trim((string) param('q'));
$entite = trim((string) param('entite'));
$action = trim((string) param('action'));
$utilisateur = trim((string) param('utilisateur'));
$dateFrom = (string) param('date_from');
$dateTo = (string) param('date_to');
$page = max(0, int_param('page'));
$size = int_param('size', 25);
if (!in_array($size, [25, 50, 100], true)) {
    $size = 25;
}

$conditions = [];
$params = [];
if ($q !== '') {
    $conditions[] = '(details LIKE ? OR utilisateur LIKE ? OR action LIKE ? OR entite LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($entite !== '') {
    $conditions[] = 'entite = ?';
    $params[] = $entite;
}
if ($action !== '') {
    $conditions[] = 'action = ?';
    $params[] = $action;
}
if ($utilisateur !== '') {
    $conditions[] = 'utilisateur LIKE ?';
    $params[] = '%' . $utilisateur . '%';
}
if (isValidDate($dateFrom)) {
    $conditions[] = 'date_action >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if (isValidDate($dateTo)) {
    $conditions[] = 'date_action <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$total = (int) scalar('SELECT COUNT(*) FROM audit_log' . $where, $params, 0);
$totalPages = max(1, (int) ceil($total / $size));
if ($page >= $totalPages) {
    $page = $totalPages - 1;
}
$offset = $page * $size;

$rows = fetch_all(
    'SELECT * FROM audit_log' . $where . ' ORDER BY date_action DESC, id DESC LIMIT ' . $size . ' OFFSET ' . $offset,
    $params
);

function audit_action_label(string $action): string
{
    $labels = [
        'CREATION' => 'Création',
        'MODIFICATION' => 'Modification',
        'SUPPRESSION' => 'Suppression',
        'REGLEMENT' => 'Règlement',
        'DE_REGLEMENT' => 'Dé-règlement',
        'REJET' => 'Rejet',
        'ANNULATION_REJET' => 'Annulation de rejet',
        'CONNEXION' => 'Connexion',
        'DECONNEXION' => 'Déconnexion',
        'EXPORT' => 'Export',
    ];
    return t($labels[$action] ?? $action);
}

function audit_action_class(string $action): string
{
    return match ($action) {
        'SUPPRESSION', 'REJET' => 'badge badge-late',
        'REGLEMENT' => 'badge badge-valid',
        'CREATION' => 'badge badge-cheque',
        default => 'badge badge-neutral',
    };
}

function audit_entite_label(string $entite): string
{
    $labels = [
        'DOCUMENT' => 'Document',
        'CARNET' => 'Carnet',
        'PERSONNE' => 'Personne',
        'BENEFICIAIRE' => 'Bénéficiaire',
        'SOCIETE' => 'Société',
        'UTILISATEUR' => 'Utilisateur',
        'ALERTE' => 'Alerte',
    ];
    return t($labels[$entite] ?? $entite);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Historique')) ?></h1>
        <p class="page-sub"><?= h(t('Journal des créations, modifications et suppressions.')) ?></p>
    </div>
    <div class="page-actions">
        <span class="badge badge-neutral"><?= number_format($total, 0, ',', ' ') ?> <?= h(t('événement(s)')) ?></span>
    </div>
</div>

<div class="card">
    <div class="filters">
        <form method="get" action="<?= h(url('historique/index.php')) ?>" data-auto-submit>
            <input type="hidden" name="size" value="<?= $size ?>">
            <div class="filters-main historique-filters">
                <div class="field">
                    <label for="auditEntite"><?= h(t('Entité')) ?></label>
                    <select id="auditEntite" name="entite" class="select">
                        <option value=""><?= h(t('Toutes')) ?></option>
                        <?php foreach (['DOCUMENT' => 'Documents', 'CARNET' => 'Carnets', 'PERSONNE' => 'Personnes', 'BENEFICIAIRE' => 'Bénéficiaires', 'SOCIETE' => 'Société', 'UTILISATEUR' => 'Utilisateur', 'ALERTE' => 'Alerte'] as $value => $label): ?>
                            <option value="<?= h($value) ?>"<?= $entite === $value ? ' selected' : '' ?>><?= h(t($label)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="auditAction"><?= h(t('Action')) ?></label>
                    <select id="auditAction" name="action" class="select">
                        <option value=""><?= h(t('Toutes')) ?></option>
                        <?php foreach (['CREATION' => 'Création', 'MODIFICATION' => 'Modification', 'SUPPRESSION' => 'Suppression', 'REGLEMENT' => 'Règlement', 'DE_REGLEMENT' => 'Dé-règlement', 'REJET' => 'Rejet', 'ANNULATION_REJET' => 'Annulation de rejet', 'EXPORT' => 'Export'] as $value => $label): ?>
                            <option value="<?= h($value) ?>"<?= $action === $value ? ' selected' : '' ?>><?= h(t($label)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="auditUser"><?= h(t('Utilisateur')) ?></label>
                    <input id="auditUser" name="utilisateur" type="text" class="input" value="<?= h($utilisateur) ?>" placeholder="<?= h(t('Rechercher…')) ?>">
                </div>

                <div class="field">
                    <label for="auditFrom"><?= h(t('Du')) ?></label>
                    <input id="auditFrom" name="date_from" type="date" class="input" value="<?= h($dateFrom) ?>">
                </div>

                <div class="field">
                    <label for="auditTo"><?= h(t('Au')) ?></label>
                    <input id="auditTo" name="date_to" type="date" class="input" value="<?= h($dateTo) ?>">
                </div>

                <div class="field">
                    <label for="auditQ"><?= h(t('Recherche')) ?></label>
                    <input id="auditQ" name="q" type="text" class="input" value="<?= h($q) ?>" placeholder="<?= h(t('Détails, utilisateur…')) ?>">
                </div>

                <div class="filters-actions">
                    <a class="btn btn-ghost" href="<?= h(url('historique/index.php')) ?>"><?= h(t('Réinitialiser')) ?></a>
                    <button type="submit" class="btn btn-primary"><?= h(t('Filtrer')) ?></button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data historique-table">
            <thead>
            <tr>
                <th><?= h(t('Date')) ?></th>
                <th><?= h(t('Utilisateur')) ?></th>
                <th><?= h(t('Action')) ?></th>
                <th><?= h(t('Entité')) ?></th>
                <th><?= h(t('Détails')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state compact">
                            <div class="empty-title"><?= h(t('Aucun événement pour ces filtres.')) ?></div>
                            <div class="empty-text"><?= h(t('Modifiez les filtres ou réinitialisez la recherche.')) ?></div>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="nowrap mono"><?= h(datetime_fr($row['date_action'])) ?></td>
                        <td><?= h($row['utilisateur'] ?: '—') ?></td>
                        <td><span class="<?= h(audit_action_class((string) $row['action'])) ?>"><?= h(audit_action_label((string) $row['action'])) ?></span></td>
                        <td>
                            <?= h(audit_entite_label((string) $row['entite'])) ?>
                            <?php if (!empty($row['entite_id'])): ?>
                                <span class="muted">#<?= (int) $row['entite_id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="details-cell"><?= h($row['details'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= pagination($page, $totalPages, $total, $size) ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

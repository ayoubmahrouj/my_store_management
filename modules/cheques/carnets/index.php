<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Carnets & registres';
$active = 'carnets';

$q = trim((string) param('q'));
$type = (string) param('type');
$page = max(0, int_param('page', 0));
$size = int_param('size', 25);
if (!in_array($size, [10, 25, 50, 100], true)) {
    $size = 25;
}

$conditions = [];
$params = [];
if ($q !== '') {
    $conditions[] = '(c.libelle LIKE ? OR c.banque LIKE ? OR c.code LIKE ? OR p.nom LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($type === 'CHEQUE' || $type === 'EFFET') {
    $conditions[] = 'c.type = ?';
    $params[] = $type;
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$total = (int) scalar('SELECT COUNT(*) FROM carnet c LEFT JOIN personne p ON p.id = c.personne_id' . $where, $params, 0);
$totalPages = $total > 0 ? (int) ceil($total / $size) : 1;
if ($page >= $totalPages) {
    $page = max(0, $totalPages - 1);
}
$offset = $page * $size;

$rows = fetch_all(
    'SELECT c.*, p.nom AS personne_nom, p.role AS personne_role,
            (SELECT COUNT(*) FROM document_paiement d WHERE d.carnet_id = c.id) AS nb_documents,
            (SELECT COALESCE(SUM(d.montant), 0) FROM document_paiement d WHERE d.carnet_id = c.id) AS montant_total
     FROM carnet c
     LEFT JOIN personne p ON p.id = c.personne_id'
    . $where . ' ORDER BY c.libelle ASC LIMIT ' . $size . ' OFFSET ' . $offset,
    $params
);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Carnets & registres')) ?></h1>
        <p class="page-sub"><?= h(t('Chaque carnet de chèques ou registre d’effets appartient à un propriétaire.')) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= h(query_url('rapports/pdf.php', array_merge($_GET, ['rapport' => 'carnets']))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            <?= h(t('Exporter PDF')) ?>
        </a>
        <a class="btn btn-primary" href="<?= h(url('carnets/form.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            <?= h(t('Nouveau carnet')) ?>
        </a>
    </div>
</div>

<div class="card">
    <form class="filters" method="get" action="<?= h(url('carnets/index.php')) ?>" data-auto-submit>
        <div class="filters-main">
            <div class="field search-box">
                <label for="search"><?= h(t('Recherche')) ?></label>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                </svg>
                <input id="search" name="q" type="text" class="input" value="<?= h($q) ?>"
                       placeholder="<?= h(t('Référence, code, banque, propriétaire…')) ?>">
            </div>
            <div class="field">
                <label for="type"><?= h(t('Type')) ?></label>
                <select id="type" name="type" class="select">
                    <option value=""><?= h(t('Tous les types')) ?></option>
                    <option value="CHEQUE"<?= $type === 'CHEQUE' ? ' selected' : '' ?>><?= h(t('Chèques')) ?></option>
                    <option value="EFFET"<?= $type === 'EFFET' ? ' selected' : '' ?>><?= h(t('Effets')) ?></option>
                </select>
            </div>
            <div class="filters-actions">
                <button type="submit" class="btn btn-ghost"><?= h(t('Rechercher')) ?></button>
            </div>
        </div>
        <div class="filters-footer">
            <div class="totals-strip">
                <span><strong><?= number_format($total, 0, ',', ' ') ?></strong> <?= h(t('carnet(s)')) ?></span>
            </div>
            <a class="link-btn" href="<?= h(url('carnets/index.php')) ?>"><?= h(t('Effacer les filtres')) ?></a>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th><?= h(t('Carnet / registre')) ?></th>
                <th><?= h(t('Code')) ?></th>
                <th><?= h(t('Type')) ?></th>
                <th><?= h(t('Banque')) ?></th>
                <th><?= h(t('Propriétaire')) ?></th>
                <th class="text-right"><?= h(t('Documents')) ?></th>
                <th class="text-right"><?= h(t('Montant total')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M5 4h11a2 2 0 0 1 2 2v14H7a2 2 0 0 1-2-2z"/><path d="M5 4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h1"/>
                                </svg>
                            </div>
                            <div class="empty-title"><?= h(t('Aucun carnet trouvé')) ?></div>
                            <div class="empty-text"><?= h(t('Créez un carnet avant d’enregistrer des documents.')) ?></div>
                            <a class="btn btn-primary btn-sm mt-16" href="<?= h(url('carnets/form.php')) ?>"><?= h(t('Nouveau carnet')) ?></a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $c): ?>
                    <tr>
                        <td>
                            <div class="cell-main"><?= h($c['libelle']) ?></div>
                            <div class="cell-sub"><?= h(t('Créé le')) ?> <?= h(date_fr(substr((string) $c['created_at'], 0, 10))) ?></div>
                        </td>
                        <td class="mono"><?= $c['code'] !== null ? h($c['code']) : '—' ?></td>
                        <td><span class="badge <?= $c['type'] === 'CHEQUE' ? 'badge-cheque' : 'badge-effet' ?>"><?= h(type_label($c['type'])) ?></span></td>
                        <td><?= h($c['banque'] ?: '—') ?></td>
                        <td>
                            <?php if ($c['personne_nom']): ?>
                                <div class="cell-main"><?= h($c['personne_nom']) ?></div>
                                <span class="badge badge-neutral"><?= h(role_label($c['personne_role'])) ?></span>
                            <?php else: ?>
                                <span class="cell-sub"><?= h(t('Non renseigné')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right mono"><?= (int) $c['nb_documents'] ?></td>
                        <td class="text-right mono strong nowrap"><?= h(money($c['montant_total'])) ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="icon-btn" title="<?= h(t('Voir les documents')) ?>" href="<?= h(query_url('documents/index.php', ['carnet' => (int) $c['id']])) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>
                                <a class="icon-btn" title="<?= h(t('Modifier')) ?>" href="<?= h(url('carnets/form.php?id=' . (int) $c['id'])) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>
                                    </svg>
                                </a>
                                <form method="post" action="<?= h(url('carnets/delete.php')) ?>" onsubmit="return confirm('<?= h(t('Supprimer ce carnet ?')) ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="icon-btn danger" title="<?= h(t('Supprimer')) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= pagination($page, $totalPages, $total, $size) ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

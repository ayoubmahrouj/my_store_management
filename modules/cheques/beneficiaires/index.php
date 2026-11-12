<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Bénéficiaires';
$active = 'beneficiaires';

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
    $conditions[] = '(b.nom LIKE ? OR b.ice LIKE ? OR b.telephone LIKE ? OR b.email LIKE ? OR b.adresse LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($type === 'SOCIETE' || $type === 'PERSONNE') {
    $conditions[] = 'b.type = ?';
    $params[] = $type;
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$total = (int) scalar('SELECT COUNT(*) FROM beneficiaire b' . $where, $params, 0);
$totalPages = $total > 0 ? (int) ceil($total / $size) : 1;
if ($page >= $totalPages) {
    $page = max(0, $totalPages - 1);
}
$offset = $page * $size;

$rows = fetch_all(
    'SELECT b.*,
            (SELECT COUNT(*) FROM document_paiement d WHERE d.beneficiaire_id = b.id) AS nb_documents,
            (SELECT COALESCE(SUM(d.montant), 0) FROM document_paiement d WHERE d.beneficiaire_id = b.id) AS montant_total
     FROM beneficiaire b' . $where . ' ORDER BY b.nom ASC LIMIT ' . $size . ' OFFSET ' . $offset,
    $params
);

$activeFilters = ($q !== '' ? 1 : 0) + ($type !== '' ? 1 : 0);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Bénéficiaires')) ?></h1>
        <p class="page-sub"><?= h(t('Gérez les clients et les sociétés liés à vos documents.')) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= h(query_url('rapports/pdf.php', array_merge($_GET, ['rapport' => 'beneficiaires']))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            <?= h(t('Exporter PDF')) ?>
        </a>
        <a class="btn btn-primary" href="<?= h(url('beneficiaires/form.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            <?= h(t('Nouveau bénéficiaire')) ?>
        </a>
    </div>
</div>

<div class="card">
    <form class="filters" method="get" action="<?= h(url('beneficiaires/index.php')) ?>" data-auto-submit>
        <div class="filters-main">
            <div class="field search-box">
                <label for="search"><?= h(t('Recherche')) ?></label>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                </svg>
                <input id="search" name="q" type="text" class="input" value="<?= h($q) ?>"
                       placeholder="<?= h(t('Nom, ICE, téléphone, email, adresse…')) ?>">
            </div>
            <div class="field">
                <label for="type"><?= h(t('Type')) ?></label>
                <select id="type" name="type" class="select">
                    <option value=""><?= h(t('Tous les types')) ?></option>
                    <option value="SOCIETE"<?= $type === 'SOCIETE' ? ' selected' : '' ?>><?= h(t('Société')) ?></option>
                    <option value="PERSONNE"<?= $type === 'PERSONNE' ? ' selected' : '' ?>><?= h(t('Personne')) ?></option>
                </select>
            </div>
            <div class="filters-actions">
                <button type="submit" class="btn btn-ghost"><?= h(t('Rechercher')) ?></button>
            </div>
        </div>
        <div class="filters-footer">
            <div class="totals-strip">
                <span><strong><?= number_format($total, 0, ',', ' ') ?></strong> <?= h(t('bénéficiaire(s)')) ?></span>
            </div>
            <?php if ($activeFilters > 0): ?>
                <a class="link-btn" href="<?= h(url('beneficiaires/index.php')) ?>"><?= h(t('Effacer les filtres')) ?></a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th><?= h(t('Nom')) ?></th>
                <th><?= h(t('Type')) ?></th>
                <th>ICE</th>
                <th><?= h(t('Téléphone')) ?></th>
                <th><?= h(t('Email')) ?></th>
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
                                    <circle cx="9" cy="8" r="3.2"/><path d="M2.8 20c0-3.4 2.8-5.6 6.2-5.6s6.2 2.2 6.2 5.6"/>
                                </svg>
                            </div>
                            <div class="empty-title"><?= h(t('Aucun bénéficiaire trouvé')) ?></div>
                            <div class="empty-text"><?= h(t('Ajoutez votre premier client pour commencer.')) ?></div>
                            <a class="btn btn-primary btn-sm mt-16" href="<?= h(url('beneficiaires/form.php')) ?>"><?= h(t('Nouveau bénéficiaire')) ?></a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $b): ?>
                    <tr>
                        <td>
                            <div class="cell-main"><?= h($b['nom']) ?></div>
                            <?php if ($b['adresse']): ?><div class="cell-sub"><?= h(str_limit($b['adresse'], 50)) ?></div><?php endif; ?>
                        </td>
                        <td><span class="badge <?= $b['type'] === 'SOCIETE' ? 'badge-societe' : 'badge-personne' ?>"><?= h(beneficiaire_type_label($b['type'])) ?></span></td>
                        <td class="mono"><?= h($b['ice'] ?: '—') ?></td>
                        <td class="nowrap mono"><?= h($b['telephone'] ?: '—') ?></td>
                        <td><?= h($b['email'] ?: '—') ?></td>
                        <td class="text-right mono"><?= (int) $b['nb_documents'] ?></td>
                        <td class="text-right mono strong nowrap"><?= h(money($b['montant_total'])) ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="icon-btn" title="<?= h(t('Modifier')) ?>" href="<?= h(url('beneficiaires/form.php?id=' . (int) $b['id'])) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>
                                    </svg>
                                </a>
                                <form method="post" action="<?= h(url('beneficiaires/delete.php')) ?>" onsubmit="return confirm('<?= h(t('Supprimer ce bénéficiaire ?')) ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
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

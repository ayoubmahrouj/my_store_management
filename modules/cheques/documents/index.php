<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Chèques & effets';
$active = 'documents';

$beneficiaires = fetch_all('SELECT id, nom, type FROM beneficiaire ORDER BY nom');
$carnets = fetch_all('SELECT id, libelle, code FROM carnet ORDER BY libelle');

$q = trim((string) param('q'));
$type = (string) param('type');
$statut = (string) param('statut');
$beneficiaireId = int_param('beneficiaire');
$moisEcheance = (string) param('mois_echeance');
$moisEmission = (string) param('mois_emission');
$carnetId = int_param('carnet');
$dateFrom = (string) param('date_from');
$dateTo = (string) param('date_to');
$echeanceFrom = (string) param('echeance_from');
$echeanceTo = (string) param('echeance_to');
$jours = int_param('jours');
$montantMin = float_param('montant_min');
$montantMax = float_param('montant_max');

$sort = (string) param('sort', 'date_echeance');
$dir = strtolower((string) param('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
$page = max(0, int_param('page', 0));
$size = int_param('size', 25);
if (!in_array($size, [10, 25, 50, 100], true)) {
    $size = 25;
}

$allowedSort = [
    'carnet'       => 'c.libelle',
    'numero'       => 'd.numero',
    'type'         => 'd.type',
    'beneficiaire' => 'b.nom',
    'personne'     => 'p.nom',
    'montant'      => 'd.montant',
    'date_emission' => 'd.date_emission',
    'date_echeance' => 'd.date_echeance',
    'regle'        => 'd.regle',
];
if (!isset($allowedSort[$sort])) {
    $sort = 'date_echeance';
}
$orderBy = $allowedSort[$sort] . ' ' . strtoupper($dir) . ', d.id ' . strtoupper($dir);

$conditions = [];
$params = [];

if ($q !== '') {
    $conditions[] = '(d.numero LIKE ? OR b.nom LIKE ? OR p.nom LIKE ? OR d.banque LIKE ? OR c.libelle LIKE ? OR d.remarque LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($type === 'CHEQUE' || $type === 'EFFET') {
    $conditions[] = 'd.type = ?';
    $params[] = $type;
}
if ($statut === 'true') {
    $conditions[] = 'd.regle = 1';
} elseif ($statut === 'false') {
    $conditions[] = 'd.regle = 0 AND d.rejete = 0';
} elseif ($statut === 'rejete') {
    $conditions[] = 'd.rejete = 1';
}
if ($beneficiaireId > 0) {
    $conditions[] = 'd.beneficiaire_id = ?';
    $params[] = $beneficiaireId;
}
if (preg_match('/^\d{4}-\d{2}$/', $moisEcheance)) {
    $conditions[] = "DATE_FORMAT(d.date_echeance, '%Y-%m') = ?";
    $params[] = $moisEcheance;
}
if (preg_match('/^\d{4}-\d{2}$/', $moisEmission)) {
    $conditions[] = "DATE_FORMAT(d.date_emission, '%Y-%m') = ?";
    $params[] = $moisEmission;
}
if ($carnetId > 0) {
    $conditions[] = 'd.carnet_id = ?';
    $params[] = $carnetId;
}
if (isValidDate($dateFrom)) {
    $conditions[] = 'd.date_emission >= ?';
    $params[] = $dateFrom;
}
if (isValidDate($dateTo)) {
    $conditions[] = 'd.date_emission <= ?';
    $params[] = $dateTo;
}
if (isValidDate($echeanceFrom)) {
    $conditions[] = 'd.date_echeance >= ?';
    $params[] = $echeanceFrom;
}
if (isValidDate($echeanceTo)) {
    $conditions[] = 'd.date_echeance <= ?';
    $params[] = $echeanceTo;
}
if (in_array($jours, [7, 15, 30, 60, 90], true)) {
    $conditions[] = 'd.date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . $jours . ' DAY)';
}
if ($montantMin !== null) {
    $conditions[] = 'd.montant >= ?';
    $params[] = $montantMin;
}
if ($montantMax !== null) {
    $conditions[] = 'd.montant <= ?';
    $params[] = $montantMax;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$joins = ' FROM document_paiement d
           JOIN beneficiaire b ON b.id = d.beneficiaire_id
           LEFT JOIN personne p ON p.id = d.personne_id
           LEFT JOIN carnet c ON c.id = d.carnet_id';

$totals = fetch_one("SELECT COUNT(*) AS n, COALESCE(SUM(d.montant), 0) AS montant" . $joins . $where, $params);
$totalElements = (int) ($totals['n'] ?? 0);
$totalMontant = (float) ($totals['montant'] ?? 0);
$totalPages = $totalElements > 0 ? (int) ceil($totalElements / $size) : 1;
if ($page >= $totalPages) {
    $page = max(0, $totalPages - 1);
}
$offset = $page * $size;

$rows = fetch_all(
    "SELECT d.*, b.nom AS beneficiaire_nom, b.type AS beneficiaire_type,
            p.nom AS personne_nom, p.role AS personne_role,
            c.libelle AS carnet_libelle, c.code AS carnet_code"
    . $joins . $where
    . " ORDER BY " . $orderBy . " LIMIT " . $size . " OFFSET " . $offset,
    $params
);

$activeFilters = 0;
foreach (['q', 'type', 'statut', 'beneficiaire', 'mois_echeance', 'mois_emission', 'carnet', 'date_from', 'date_to', 'echeance_from', 'echeance_to', 'jours', 'montant_min', 'montant_max'] as $key) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $activeFilters++;
    }
}

function doc_sort_url(string $column): string
{
    $params = $_GET;
    $current = (string) ($params['sort'] ?? 'date_echeance');
    $currentDir = strtolower((string) ($params['dir'] ?? 'asc'));
    $params['sort'] = $column;
    $params['dir'] = ($current === $column && $currentDir === 'asc') ? 'desc' : 'asc';
    $params['page'] = 0;
    return query_url('documents/index.php', $params);
}

function doc_sort_ind(string $column): string
{
    if ((string) ($_GET['sort'] ?? '') !== $column) {
        return '';
    }
    return strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? '▼' : '▲';
}

function doc_reset_url(): string
{
    return url('documents/index.php');
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Chèques & effets')) ?></h1>
        <p class="page-sub"><?= h(t('Suivez, filtrez et gérez tous vos documents de paiement.')) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= h(query_url('documents/export.php', array_merge($_GET, ['format' => 'xlsx']))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            <?= h(t('Exporter Excel')) ?>
        </a>
        <a class="btn btn-ghost" href="<?= h(query_url('documents/export.php', array_merge($_GET, ['format' => 'csv']))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            <?= h(t('Exporter CSV')) ?>
        </a>
        <a class="btn btn-ghost" href="<?= h(query_url('rapports/pdf.php', array_merge($_GET, ['rapport' => 'documents']))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            <?= h(t('Rapport PDF')) ?>
        </a>
        <a class="btn btn-primary" href="<?= h(url('documents/form.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            <?= h(t('Nouveau document')) ?>
        </a>
    </div>
</div>

<div class="card">
    <form class="filters" method="get" action="<?= h(url('documents/index.php')) ?>" data-auto-submit>
        <div class="filters-main">
            <div class="field search-box">
                <label for="search"><?= h(t('Recherche')) ?></label>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                </svg>
                <input id="search" name="q" type="text" class="input" value="<?= h($q) ?>"
                       placeholder="<?= h(t('Numéro, bénéficiaire, personne, banque, carnet…')) ?>">
            </div>

            <div class="field">
                <label for="type"><?= h(t('Type')) ?></label>
                <select id="type" name="type" class="select">
                    <option value=""><?= h(t('Tous les types')) ?></option>
                    <option value="CHEQUE"<?= $type === 'CHEQUE' ? ' selected' : '' ?>><?= h(t('Chèques')) ?></option>
                    <option value="EFFET"<?= $type === 'EFFET' ? ' selected' : '' ?>><?= h(t('Effets')) ?></option>
                </select>
            </div>

            <div class="field">
                <label for="statut"><?= h(t('Statut')) ?></label>
                <select id="statut" name="statut" class="select">
                    <option value=""><?= h(t('Tous')) ?></option>
                    <option value="true"<?= $statut === 'true' ? ' selected' : '' ?>><?= h(t('Réglés')) ?></option>
                    <option value="false"<?= $statut === 'false' ? ' selected' : '' ?>><?= h(t('Non réglés')) ?></option>
                    <option value="rejete"<?= $statut === 'rejete' ? ' selected' : '' ?>><?= h(t('Rejetés')) ?></option>
                </select>
            </div>

            <div class="field">
                <label for="beneficiaire"><?= h(t('Bénéficiaire')) ?></label>
                <select id="beneficiaire" name="beneficiaire" class="select" data-quick="client">
                    <option value=""><?= h(t('Tous les bénéficiaires')) ?></option>
                    <?php foreach ($beneficiaires as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"<?= $beneficiaireId === (int) $b['id'] ? ' selected' : '' ?>><?= h($b['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="mois_echeance"><?= h(t("Mois d'échéance")) ?></label>
                <input id="mois_echeance" name="mois_echeance" type="month" class="input" value="<?= h($moisEcheance) ?>">
            </div>

            <div class="filters-actions">
                <button type="button" class="btn btn-ghost" id="toggleAdvanced">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                        <path d="M4 6h16M7 12h10M10 18h4"/>
                    </svg>
                    <?= h(t('Filtres')) ?>
                    <?php if ($activeFilters > 0): ?><span class="filter-count"><?= $activeFilters ?></span><?php endif; ?>
                </button>
            </div>
        </div>

        <div class="filters-advanced" id="advancedFilters"<?= $activeFilters > 4 ? '' : ' hidden' ?>>
            <div class="field">
                <label for="date_from"><?= h(t('Émission — du')) ?></label>
                <input id="date_from" name="date_from" type="date" class="input" value="<?= h($dateFrom) ?>">
            </div>
            <div class="field">
                <label for="date_to"><?= h(t('Émission — au')) ?></label>
                <input id="date_to" name="date_to" type="date" class="input" value="<?= h($dateTo) ?>">
            </div>
            <div class="field">
                <label for="echeance_from"><?= h(t('Échéance — du')) ?></label>
                <input id="echeance_from" name="echeance_from" type="date" class="input" value="<?= h($echeanceFrom) ?>">
            </div>
            <div class="field">
                <label for="echeance_to"><?= h(t('Échéance — au')) ?></label>
                <input id="echeance_to" name="echeance_to" type="date" class="input" value="<?= h($echeanceTo) ?>">
            </div>
            <div class="field">
                <label for="carnet"><?= h(t('Carnet / registre')) ?></label>
                <select id="carnet" name="carnet" class="select" data-quick="carnet">
                    <option value=""><?= h(t('Tous les carnets')) ?></option>
                    <?php foreach ($carnets as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"<?= $carnetId === (int) $c['id'] ? ' selected' : '' ?>><?= h(carnet_label($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="mois_emission"><?= h(t("Mois d'émission")) ?></label>
                <input id="mois_emission" name="mois_emission" type="month" class="input" value="<?= h($moisEmission) ?>">
            </div>
            <div class="field">
                <label for="jours"><?= h(t('Échéance dans')) ?></label>
                <select id="jours" name="jours" class="select">
                    <option value=""><?= h(t('Toute période')) ?></option>
                    <?php foreach ([7, 15, 30, 60, 90] as $j): ?>
                        <option value="<?= $j ?>"<?= $jours === $j ? ' selected' : '' ?>><?= $j ?> <?= h(t('jours')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="montant_min"><?= h(t('Montant min. (DH)')) ?></label>
                <input id="montant_min" name="montant_min" type="number" min="0" step="0.01" class="input" placeholder="0" value="<?= $montantMin !== null ? h((string) $montantMin) : '' ?>">
            </div>
            <div class="field">
                <label for="montant_max"><?= h(t('Montant max. (DH)')) ?></label>
                <input id="montant_max" name="montant_max" type="number" min="0" step="0.01" class="input" placeholder="<?= h(t('Illimité')) ?>" value="<?= $montantMax !== null ? h((string) $montantMax) : '' ?>">
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <a class="btn btn-ghost" href="<?= h(doc_reset_url()) ?>"><?= h(t('Réinitialiser les filtres')) ?></a>
            </div>
        </div>

        <input type="hidden" name="sort" value="<?= h($sort) ?>">
        <input type="hidden" name="dir" value="<?= h($dir) ?>">
        <input type="hidden" name="size" value="<?= (int) $size ?>">

        <div class="filters-footer">
            <div class="totals-strip">
                <span><strong><?= number_format($totalElements, 0, ',', ' ') ?></strong> <?= h(t('document(s)')) ?></span>
                <span class="totals-sep">·</span>
                <span><?= h(t('Montant total')) ?> <strong><?= h(money($totalMontant)) ?></strong></span>
            </div>
            <?php if ($activeFilters > 0): ?>
                <a class="link-btn" href="<?= h(doc_reset_url()) ?>"><?= h(t('Effacer les filtres')) ?></a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th class="sortable"><a href="<?= h(doc_sort_url('carnet')) ?>"><?= h(t('Carnet')) ?> <span class="sort-ind"><?= doc_sort_ind('carnet') ?></span></a></th>
                <th class="sortable"><a href="<?= h(doc_sort_url('numero')) ?>"><?= h(t('N°')) ?> <span class="sort-ind"><?= doc_sort_ind('numero') ?></span></a></th>
                <th><?= h(t('Image')) ?></th>
                <th class="sortable"><a href="<?= h(doc_sort_url('type')) ?>"><?= h(t('Type')) ?> <span class="sort-ind"><?= doc_sort_ind('type') ?></span></a></th>
                <th class="sortable"><a href="<?= h(doc_sort_url('beneficiaire')) ?>"><?= h(t('Bénéficiaire')) ?> <span class="sort-ind"><?= doc_sort_ind('beneficiaire') ?></span></a></th>
                <th class="sortable"><a href="<?= h(doc_sort_url('personne')) ?>"><?= h(t('Personne')) ?> <span class="sort-ind"><?= doc_sort_ind('personne') ?></span></a></th>
                <th class="text-right sortable"><a href="<?= h(doc_sort_url('montant')) ?>"><?= h(t('Montant')) ?> <span class="sort-ind"><?= doc_sort_ind('montant') ?></span></a></th>
                <th class="sortable"><a href="<?= h(doc_sort_url('date_emission')) ?>"><?= h(t('Émission')) ?> <span class="sort-ind"><?= doc_sort_ind('date_emission') ?></span></a></th>
                <th class="sortable"><a href="<?= h(doc_sort_url('date_echeance')) ?>"><?= h(t('Échéance')) ?> <span class="sort-ind"><?= doc_sort_ind('date_echeance') ?></span></a></th>
                <th class="text-center"><?= h(t('Réglé')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="11">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                                    <path d="M3 9h18M7 14h5"/>
                                </svg>
                            </div>
                            <div class="empty-title"><?= h(t('Aucun document trouvé')) ?></div>
                            <div class="empty-text"><?= h(t('Modifiez vos filtres ou ajoutez un nouveau chèque / effet.')) ?></div>
                            <a class="btn btn-primary btn-sm mt-16" href="<?= h(url('documents/form.php')) ?>"><?= h(t('Ajouter un document')) ?></a>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $doc): ?>
                    <?php
                    $isRegle = bit_true($doc['regle']);
                    $isRejete = bit_true($doc['rejete']);
                    $joursRestants = days_until($doc['date_echeance']);
                    ?>
                    <tr>
                        <td class="nowrap">
                            <div class="cell-main"><?= h($doc['carnet_libelle'] ?: '—') ?></div>
                            <?php if ($doc['carnet_code'] !== null): ?>
                                <div class="cell-sub"><?= h(t('Code')) ?> <?= h($doc['carnet_code']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap mono"><?= h($doc['numero']) ?></td>
                        <td class="nowrap">
                            <?php if (!empty($doc['image_path']) || !empty($doc['image_back_path'])): ?>
                                <div style="display:flex;gap:5px">
                                    <?php if (!empty($doc['image_path'])): ?>
                                        <a href="<?= h(url('documents/image.php?id=' . $doc['id'] . '&side=front')) ?>"
                                           data-gos-image
                                           data-title="<?= h(type_label($doc['type']) . ' n° ' . $doc['numero'] . ' — recto') ?>"
                                           title="<?= h(t('Voir l’image')) ?>">
                                            <img src="<?= h(url('documents/image.php?id=' . $doc['id'] . '&side=front')) ?>"
                                                 alt="<?= h(t('Image')) ?>"
                                                 style="width:42px;height:42px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,.14)">
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($doc['image_back_path'])): ?>
                                        <a href="<?= h(url('documents/image.php?id=' . $doc['id'] . '&side=back')) ?>"
                                           data-gos-image
                                           data-title="<?= h(type_label($doc['type']) . ' n° ' . $doc['numero'] . ' — verso') ?>"
                                           title="<?= h(t('Voir l’image')) ?>">
                                            <img src="<?= h(url('documents/image.php?id=' . $doc['id'] . '&side=back')) ?>"
                                                 alt="<?= h(t('Image')) ?>"
                                                 style="width:42px;height:42px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,.14)">
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="cell-sub">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $doc['type'] === 'CHEQUE' ? 'badge-cheque' : 'badge-effet' ?>">
                                <?= h(type_label($doc['type'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="beneficiaire-cell">
                                <div class="cell-main"><?= h($doc['beneficiaire_nom']) ?></div>
                                <span class="badge <?= $doc['beneficiaire_type'] === 'SOCIETE' ? 'badge-societe' : 'badge-personne' ?>">
                                    <?= h(beneficiaire_type_label($doc['beneficiaire_type'])) ?>
                                </span>
                            </div>
                        </td>
                        <td class="personne-cell">
                            <?php if (!empty($doc['personne_nom'])): ?>
                                <div class="cell-main"><?= h($doc['personne_nom']) ?></div>
                                <span class="badge badge-neutral"><?= h(role_label($doc['personne_role'])) ?></span>
                            <?php else: ?>
                                <span class="cell-sub"><?= h(t('Non renseignée')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right mono strong nowrap"><?= h(money($doc['montant'])) ?></td>
                        <td class="nowrap mono"><?= h(date_fr($doc['date_emission'])) ?></td>
                        <td class="nowrap">
                            <div class="mono"><?= h(date_fr($doc['date_echeance'])) ?></div>
                            <?= status_badge($doc) ?>
                        </td>
                        <td class="text-center nowrap">
                            <?php if ($isRejete): ?>
                                <form method="post" action="<?= h(url('documents/toggle.php')) ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                                    <input type="hidden" name="field" value="rejete">
                                    <button type="submit" class="badge badge-late badge-btn" title="<?= h(t('Annuler le rejet')) ?>"><?= h(t('Rejeté')) ?></button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= h(url('documents/toggle.php')) ?>" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                                    <input type="hidden" name="field" value="regle">
                                    <label class="switch" title="<?= h($isRegle ? t('Marquer non réglé') : t('Marquer réglé')) ?>">
                                        <input type="checkbox" <?= $isRegle ? 'checked' : '' ?> onchange="this.form.submit()">
                                        <span class="switch-track"></span>
                                    </label>
                                </form>
                                <?php if ($isRegle && !empty($doc['date_reglement'])): ?>
                                    <div class="cell-sub"><?= h(date_fr($doc['date_reglement'])) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a class="icon-btn" title="<?= h(t('Fiche PDF')) ?>" href="<?= h(url('documents/pdf.php?id=' . (int) $doc['id'])) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M6 9V4h12v5"/><path d="M6 18H4v-7h16v7h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/>
                                    </svg>
                                </a>
                                <?php if (!$isRegle): ?>
                                    <form method="post" action="<?= h(url('documents/toggle.php')) ?>" class="m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                                        <input type="hidden" name="field" value="rejete">
                                        <button type="submit" class="icon-btn danger" title="<?= h($isRejete ? t('Annuler le rejet') : t('Marquer rejeté')) ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M12 3l9 16H3z"/><path d="M12 9v4M12 16.5v.5"/>
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a class="icon-btn" title="<?= h(t('Modifier')) ?>" href="<?= h(url('documents/form.php?id=' . (int) $doc['id'])) ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>
                                    </svg>
                                </a>
                                <form method="post" action="<?= h(url('documents/delete.php')) ?>" class="m-0" onsubmit="return confirm('<?= h(t('Supprimer définitivement ce document ?')) ?>');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
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

    <?= pagination($page, $totalPages, $totalElements, $size) ?>
</div>

<script>
    document.getElementById('toggleAdvanced').addEventListener('click', function () {
        var box = document.getElementById('advancedFilters');
        box.hidden = !box.hidden;
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

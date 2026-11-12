<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Statistiques';
$active = 'statistiques';

$beneficiaires = fetch_all('SELECT id, nom FROM beneficiaire ORDER BY nom');
$personnes = fetch_all('SELECT id, nom FROM personne ORDER BY nom');
$carnets = fetch_all('SELECT id, libelle, code FROM carnet ORDER BY libelle');

$q = trim((string) param('q'));
$typeDate = (string) param('type_date', 'ECHEANCE');
if (!in_array($typeDate, ['ECHEANCE', 'EMISSION', 'REGLEMENT'], true)) {
    $typeDate = 'ECHEANCE';
}
$dateFrom = (string) param('date_from');
$dateTo = (string) param('date_to');
$type = (string) param('type');
$regleFilter = (string) param('regle');
$beneficiaireId = int_param('beneficiaire');
$personneId = int_param('personne');
$carnetId = int_param('carnet');
$banqueFilter = trim((string) param('banque'));
$triMois = (string) param('tri_mois', 'montant');
if (!in_array($triMois, ['montant', 'mois'], true)) {
    $triMois = 'montant';
}
$seuilMoisParam = float_param('seuil_mois');

$dateCol = match ($typeDate) {
    'EMISSION' => 'd.date_emission',
    'REGLEMENT' => 'd.date_reglement',
    default => 'd.date_echeance',
};

$conditions = [];
$params = [];
if ($q !== '') {
    $conditions[] = '(d.numero LIKE ? OR b.nom LIKE ? OR d.banque LIKE ? OR c.libelle LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($type === 'CHEQUE' || $type === 'EFFET') {
    $conditions[] = 'd.type = ?';
    $params[] = $type;
}
if ($regleFilter === 'true') {
    $conditions[] = 'd.regle = 1';
} elseif ($regleFilter === 'false') {
    $conditions[] = 'd.regle = 0 AND d.rejete = 0';
} elseif ($regleFilter === 'rejete') {
    $conditions[] = 'd.rejete = 1';
}
if ($beneficiaireId > 0) {
    $conditions[] = 'd.beneficiaire_id = ?';
    $params[] = $beneficiaireId;
}
if ($personneId > 0) {
    $conditions[] = 'd.personne_id = ?';
    $params[] = $personneId;
}
if ($carnetId > 0) {
    $conditions[] = 'd.carnet_id = ?';
    $params[] = $carnetId;
}
if ($banqueFilter !== '') {
    $conditions[] = 'd.banque LIKE ?';
    $params[] = '%' . $banqueFilter . '%';
}
if (isValidDate($dateFrom)) {
    $conditions[] = $dateCol . ' >= ?';
    $params[] = $dateFrom;
}
if (isValidDate($dateTo)) {
    $conditions[] = $dateCol . ' <= ?';
    $params[] = $dateTo;
}

$joins = ' FROM document_paiement d
           JOIN beneficiaire b ON b.id = d.beneficiaire_id
           LEFT JOIN personne p ON p.id = d.personne_id
           LEFT JOIN carnet c ON c.id = d.carnet_id';
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$stats = fetch_one(
    'SELECT
        COUNT(*) AS nombre_total,
        COALESCE(SUM(d.montant), 0) AS montant_total,
        COALESCE(SUM(d.regle = 1), 0) AS nombre_regle,
        COALESCE(SUM(CASE WHEN d.regle = 1 THEN d.montant ELSE 0 END), 0) AS montant_regle,
        COALESCE(SUM(d.regle = 0 AND d.rejete = 0), 0) AS nombre_non_regle,
        COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS montant_non_regle,
        COALESCE(SUM(d.rejete = 1), 0) AS nombre_rejetes,
        COALESCE(SUM(CASE WHEN d.rejete = 1 THEN d.montant ELSE 0 END), 0) AS montant_rejetes,
        COALESCE(SUM(d.regle = 0 AND d.rejete = 0 AND d.date_echeance < CURDATE()), 0) AS nombre_en_retard,
        COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 AND d.date_echeance < CURDATE() THEN d.montant ELSE 0 END), 0) AS montant_en_retard,
        COALESCE(SUM(d.type = "CHEQUE"), 0) AS nombre_cheques,
        COALESCE(SUM(CASE WHEN d.type = "CHEQUE" THEN d.montant ELSE 0 END), 0) AS montant_cheques,
        COALESCE(SUM(d.type = "CHEQUE" AND d.regle = 1), 0) AS nombre_cheques_regles,
        COALESCE(SUM(CASE WHEN d.type = "CHEQUE" AND d.regle = 1 THEN d.montant ELSE 0 END), 0) AS montant_cheques_regles,
        COALESCE(SUM(d.type = "EFFET"), 0) AS nombre_effets,
        COALESCE(SUM(CASE WHEN d.type = "EFFET" THEN d.montant ELSE 0 END), 0) AS montant_effets,
        COALESCE(SUM(d.type = "EFFET" AND d.regle = 1), 0) AS nombre_effets_regles,
        COALESCE(SUM(CASE WHEN d.type = "EFFET" AND d.regle = 1 THEN d.montant ELSE 0 END), 0) AS montant_effets_regles'
    . $joins . $where,
    $params
) ?: [];

$montantTotal = (float) ($stats['montant_total'] ?? 0);
$nombreTotal = (int) ($stats['nombre_total'] ?? 0);
$pourcentageRegle = $nombreTotal > 0 ? (int) round(((int) $stats['nombre_regle']) * 100 / $nombreTotal) : 0;
$pourcentageRetard = $nombreTotal > 0 ? (int) round(((int) $stats['nombre_en_retard']) * 100 / $nombreTotal) : 0;

$moisRows = fetch_all(
    'SELECT DATE_FORMAT(' . $dateCol . ", '%Y-%m') AS mois,
            COUNT(*) AS nombre,
            COALESCE(SUM(d.montant), 0) AS montant,
            COALESCE(SUM(d.regle = 1), 0) AS nombre_regle,
            COALESCE(SUM(CASE WHEN d.regle = 1 THEN d.montant ELSE 0 END), 0) AS montant_regle,
            COALESCE(SUM(d.regle = 0 AND d.rejete = 0), 0) AS nombre_non_regle,
            COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS montant_non_regle"
    . $joins . $where
    . ($where === '' ? ' WHERE ' : ' AND ') . $dateCol . ' IS NOT NULL'
    . ' GROUP BY mois ORDER BY mois ASC',
    $params
);

$moisChrono = $moisRows;
$nbMois = count($moisRows);
$totalMois = 0.0;
$totalEncoursMois = 0.0;
foreach ($moisRows as $row) {
    $totalMois += (float) $row['montant'];
    $totalEncoursMois += (float) $row['montant_non_regle'];
}
$moyenneMensuelle = $nbMois > 0 ? $totalMois / $nbMois : 0.0;
$moyenneEncours = $nbMois > 0 ? $totalEncoursMois / $nbMois : 0.0;
$seuilAuto = $moyenneEncours * 1.25;
$seuilManuel = $seuilMoisParam !== null && $seuilMoisParam > 0;
$seuilMois = $seuilManuel ? (float) $seuilMoisParam : $seuilAuto;

$moisParMontant = $moisRows;
usort($moisParMontant, static function (array $a, array $b): int {
    $cmp = (float) $b['montant'] <=> (float) $a['montant'];
    return $cmp !== 0 ? $cmp : strcmp((string) $a['mois'], (string) $b['mois']);
});
$rangMois = [];
foreach ($moisParMontant as $index => $row) {
    $rangMois[(string) $row['mois']] = $index + 1;
}
$moisTri = $triMois === 'montant' ? $moisParMontant : $moisChrono;

$moisAlertes = [];
$moisSuggeres = [];
foreach ($moisRows as $row) {
    $encours = (float) $row['montant_non_regle'];
    $row['encours'] = $encours;
    $row['depassement'] = max(0.0, $encours - $seuilMois);
    $row['niveau'] = $seuilMois > 0 && $encours >= $seuilMois * 1.25
        ? 'critique'
        : ($seuilMois > 0 && $encours >= $seuilMois ? 'eleve' : 'normal');
    if ($row['niveau'] !== 'normal') {
        $moisAlertes[] = $row;
    } elseif ($seuilMois > 0) {
        $moisSuggeres[] = $row;
    }
}
usort($moisAlertes, static fn (array $a, array $b): int => (float) $b['encours'] <=> (float) $a['encours']);
usort($moisSuggeres, static fn (array $a, array $b): int => (float) $a['encours'] <=> (float) $b['encours']);
$moisSuggeres = array_slice($moisSuggeres, 0, 3);

$maxMois = 0.0;
foreach ($moisRows as $row) {
    $maxMois = max($maxMois, (float) $row['montant']);
}

$parCarnet = fetch_all(
    'SELECT c.id, c.libelle, c.code, c.type, c.banque, p.nom AS personne_nom,
            COUNT(d.id) AS nombre,
            COALESCE(SUM(d.montant), 0) AS montant,
            COALESCE(SUM(d.regle = 1), 0) AS nombre_regle,
            COALESCE(SUM(CASE WHEN d.regle = 1 THEN d.montant ELSE 0 END), 0) AS montant_regle'
    . $joins . $where
    . ' GROUP BY c.id, c.libelle, c.code, c.type, c.banque, p.nom
        ORDER BY montant DESC',
    $params
);

$parBeneficiaire = fetch_all(
    'SELECT b.id AS beneficiaire_id, b.nom,
            COUNT(*) AS nombre,
            COALESCE(SUM(d.montant), 0) AS montant,
            COALESCE(SUM(d.regle = 1), 0) AS nombre_regle'
    . $joins . $where
    . ' GROUP BY b.id, b.nom ORDER BY montant DESC LIMIT 10',
    $params
);

$parBanque = fetch_all(
    "SELECT COALESCE(NULLIF(d.banque, ''), '—') AS banque,
            COUNT(*) AS nombre,
            COALESCE(SUM(d.montant), 0) AS montant"
    . $joins . $where
    . ' GROUP BY banque ORDER BY montant DESC LIMIT 10',
    $params
);

$typeDateLabel = match ($typeDate) {
    'EMISSION' => t("date d'émission"),
    'REGLEMENT' => t('date de règlement'),
    default => t("date d'échéance"),
};

function pct(mixed $value, float $total): string
{
    if ($total <= 0) {
        return '0 %';
    }
    return (int) round(((float) $value) * 100 / $total) . ' %';
}

function bar_height(float $montant, float $max): string
{
    if ($max <= 0) {
        return '2%';
    }
    $percent = max(($montant / $max) * 100, $montant > 0 ? 4 : 2);
    return round($percent, 1) . '%';
}

function charge_pct(float $encours, float $seuil): ?int
{
    if ($seuil <= 0) {
        return null;
    }
    return (int) round($encours * 100 / $seuil);
}

function mois_court(string $mois): string
{
    $labels = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    if (!preg_match('/^(\d{4})-(\d{2})$/', $mois, $m)) {
        return $mois;
    }
    return $labels[max(0, min(11, (int) $m[2] - 1))] . ' ' . substr($m[1], 2);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Statistiques')) ?></h1>
        <p class="page-sub"><?= h(t('Analyse détaillée de vos chèques et effets : montants, règlements, échéances et répartitions.')) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= h(query_url('rapports/pdf.php', array_merge($_GET, ['rapport' => 'statistiques']))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            <?= h(t('Exporter PDF')) ?>
        </a>
        <a class="btn btn-ghost" href="<?= h(url('calendrier/index.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>
            </svg>
            <?= h(t('Calendrier de distribution')) ?>
        </a>
        <a class="btn btn-ghost" href="<?= h(url('statistiques/index.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>
            </svg>
            <?= h(t('Actualiser')) ?>
        </a>
    </div>
</div>

<div class="card stats-filter-card">
    <div class="filters">
        <form method="get" action="<?= h(url('statistiques/index.php')) ?>" data-auto-submit>
            <div class="stats-filters">
                <div class="field search-box stats-search">
                    <label for="statSearch"><?= h(t('Recherche')) ?></label>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                        <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                    </svg>
                    <input id="statSearch" name="q" type="text" class="input" value="<?= h($q) ?>"
                           placeholder="<?= h(t('Numéro, bénéficiaire, banque, carnet…')) ?>">
                </div>

                <div class="field">
                    <label for="typeDate"><?= h(t('Période basée sur')) ?></label>
                    <select id="typeDate" name="type_date" class="select">
                        <option value="ECHEANCE"<?= $typeDate === 'ECHEANCE' ? ' selected' : '' ?>><?= h(t("Date d'échéance")) ?></option>
                        <option value="EMISSION"<?= $typeDate === 'EMISSION' ? ' selected' : '' ?>><?= h(t("Date d'émission")) ?></option>
                        <option value="REGLEMENT"<?= $typeDate === 'REGLEMENT' ? ' selected' : '' ?>><?= h(t('Date de règlement')) ?></option>
                    </select>
                </div>

                <div class="field">
                    <label for="moisEcheance"><?= h(t("Mois d'échéance")) ?></label>
                    <input id="moisEcheance" type="month" class="input" value="">
                </div>

                <div class="field">
                    <label for="dateFrom"><?= h(t('Du')) ?></label>
                    <input id="dateFrom" name="date_from" type="date" class="input" value="<?= h($dateFrom) ?>">
                </div>

                <div class="field">
                    <label for="dateTo"><?= h(t('Au')) ?></label>
                    <input id="dateTo" name="date_to" type="date" class="input" value="<?= h($dateTo) ?>">
                </div>
            </div>

            <div class="stats-filters stats-filters-secondary">
                <div class="field">
                    <label for="statType"><?= h(t('Type')) ?></label>
                    <select id="statType" name="type" class="select">
                        <option value=""><?= h(t('Tous les types')) ?></option>
                        <option value="CHEQUE"<?= $type === 'CHEQUE' ? ' selected' : '' ?>><?= h(t('Chèques')) ?></option>
                        <option value="EFFET"<?= $type === 'EFFET' ? ' selected' : '' ?>><?= h(t('Effets')) ?></option>
                    </select>
                </div>

                <div class="field">
                    <label for="statRegle"><?= h(t('Règlement')) ?></label>
                    <select id="statRegle" name="regle" class="select">
                        <option value=""><?= h(t('Tous')) ?></option>
                        <option value="true"<?= $regleFilter === 'true' ? ' selected' : '' ?>><?= h(t('Réglés')) ?></option>
                        <option value="false"<?= $regleFilter === 'false' ? ' selected' : '' ?>><?= h(t('Non réglés')) ?></option>
                        <option value="rejete"<?= $regleFilter === 'rejete' ? ' selected' : '' ?>><?= h(t('Rejetés')) ?></option>
                    </select>
                </div>

                <div class="field">
                    <label for="statBeneficiaire"><?= h(t('Bénéficiaire')) ?></label>
                    <select id="statBeneficiaire" name="beneficiaire" class="select" data-quick="client">
                        <option value=""><?= h(t('Tous les bénéficiaires')) ?></option>
                        <?php foreach ($beneficiaires as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"<?= $beneficiaireId === (int) $b['id'] ? ' selected' : '' ?>><?= h($b['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="statPersonne"><?= h(t('Propriétaire')) ?></label>
                    <select id="statPersonne" name="personne" class="select" data-quick="personne">
                        <option value=""><?= h(t('Tous les propriétaires')) ?></option>
                        <?php foreach ($personnes as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"<?= $personneId === (int) $p['id'] ? ' selected' : '' ?>><?= h($p['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="statCarnet"><?= h(t('Carnet / registre')) ?></label>
                    <select id="statCarnet" name="carnet" class="select" data-quick="carnet">
                        <option value=""><?= h(t('Tous les carnets')) ?></option>
                        <?php foreach ($carnets as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= $carnetId === (int) $c['id'] ? ' selected' : '' ?>><?= h(carnet_label($c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="statBanque"><?= h(t('Banque')) ?></label>
                    <input id="statBanque" name="banque" type="text" class="input" value="<?= h($banqueFilter) ?>" placeholder="<?= h(t('Toutes')) ?>">
                </div>

                <div class="field">
                    <label for="seuilMois"><?= h(t("Seuil d'alerte (DH)")) ?></label>
                    <input id="seuilMois" name="seuil_mois" type="number" min="0" step="0.01" class="input"
                           value="<?= $seuilManuel ? h((string) $seuilMoisParam) : '' ?>"
                           placeholder="<?= h(t('Automatique')) ?>">
                </div>

                <div class="field">
                    <label for="triMois"><?= h(t('Trier les mois par')) ?></label>
                    <select id="triMois" name="tri_mois" class="select">
                        <option value="montant"<?= $triMois === 'montant' ? ' selected' : '' ?>><?= h(t('Montant (décroissant)')) ?></option>
                        <option value="mois"<?= $triMois === 'mois' ? ' selected' : '' ?>><?= h(t('Mois (chronologique)')) ?></option>
                    </select>
                </div>

                <div class="stats-actions">
                    <a class="btn btn-ghost" href="<?= h(url('statistiques/index.php')) ?>"><?= h(t('Réinitialiser')) ?></a>
                    <button type="submit" class="btn btn-primary"><?= h(t('Calculer')) ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi kpi-navy">
        <div class="kpi-head">
            <span class="kpi-label"><?= h(t('Total documents')) ?></span>
            <span class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h5M10 16h5"/>
                </svg>
            </span>
        </div>
        <div class="kpi-value"><?= (int) $nombreTotal ?></div>
        <div class="kpi-meta"><strong><?= h(money($montantTotal)) ?></strong> <?= h(t('au total')) ?></div>
    </div>

    <div class="kpi kpi-green">
        <div class="kpi-head">
            <span class="kpi-label"><?= h(t('Réglés')) ?></span>
            <span class="kpi-icon green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.6 2.6L16.5 9"/>
                </svg>
            </span>
        </div>
        <div class="kpi-value"><?= (int) ($stats['nombre_regle'] ?? 0) ?></div>
        <div class="kpi-meta"><strong><?= h(money($stats['montant_regle'] ?? 0)) ?></strong> · <?= $pourcentageRegle ?> % <?= h(t('du total')) ?></div>
    </div>

    <div class="kpi">
        <div class="kpi-head">
            <span class="kpi-label"><?= h(t('Non réglés')) ?></span>
            <span class="kpi-icon gold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/>
                </svg>
            </span>
        </div>
        <div class="kpi-value"><?= (int) ($stats['nombre_non_regle'] ?? 0) ?></div>
        <div class="kpi-meta"><strong><?= h(money($stats['montant_non_regle'] ?? 0)) ?></strong> · <?= h(t('à encaisser')) ?></div>
    </div>

    <div class="kpi kpi-red">
        <div class="kpi-head">
            <span class="kpi-label"><?= h(t('En retard')) ?></span>
            <span class="kpi-icon red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 3l9 16H3z"/><path d="M12 9v4M12 16.5v.5"/>
                </svg>
            </span>
        </div>
        <div class="kpi-value"><?= (int) ($stats['nombre_en_retard'] ?? 0) ?></div>
        <div class="kpi-meta"><strong><?= h(money($stats['montant_en_retard'] ?? 0)) ?></strong> · <?= $pourcentageRetard ?> % <?= h(t('du total')) ?></div>
    </div>

    <div class="kpi kpi-red">
        <div class="kpi-head">
            <span class="kpi-label"><?= h(t('Rejetés')) ?></span>
            <span class="kpi-icon red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9"/><path d="M8 8l8 8M16 8l-8 8"/>
                </svg>
            </span>
        </div>
        <div class="kpi-value"><?= (int) ($stats['nombre_rejetes'] ?? 0) ?></div>
        <div class="kpi-meta"><strong><?= h(money($stats['montant_rejetes'] ?? 0)) ?></strong> · <?= h(t('impayés')) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Chèques')) ?></div>
                <div class="card-sub"><?= h(t('Total et règlements des chèques')) ?></div>
            </div>
            <span class="badge badge-cheque"><?= (int) ($stats['nombre_cheques'] ?? 0) ?> <?= h(t('doc(s)')) ?></span>
        </div>
        <div class="card-body">
            <div class="stat-pairs">
                <div class="stat-pair">
                    <span class="stat-pair-label"><?= h(t('Montant total')) ?></span>
                    <strong><?= h(money($stats['montant_cheques'] ?? 0)) ?></strong>
                </div>
                <div class="stat-pair">
                    <span class="stat-pair-label"><?= h(t('Réglés')) ?></span>
                    <strong><?= (int) ($stats['nombre_cheques_regles'] ?? 0) ?> · <?= h(money($stats['montant_cheques_regles'] ?? 0)) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Effets')) ?></div>
                <div class="card-sub"><?= h(t('Total et règlements des effets')) ?></div>
            </div>
            <span class="badge badge-effet"><?= (int) ($stats['nombre_effets'] ?? 0) ?> <?= h(t('doc(s)')) ?></span>
        </div>
        <div class="card-body">
            <div class="stat-pairs">
                <div class="stat-pair">
                    <span class="stat-pair-label"><?= h(t('Montant total')) ?></span>
                    <strong><?= h(money($stats['montant_effets'] ?? 0)) ?></strong>
                </div>
                <div class="stat-pair">
                    <span class="stat-pair-label"><?= h(t('Réglés')) ?></span>
                    <strong><?= (int) ($stats['nombre_effets_regles'] ?? 0) ?> · <?= h(money($stats['montant_effets_regles'] ?? 0)) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card stats-section month-alert-card">
    <div class="card-header">
        <div>
            <div class="card-title"><?= h(t('Charge mensuelle & alertes')) ?></div>
            <div class="card-sub"><?= h(t('Mois à éviter pour les nouveaux chèques/effets, selon l’encours non réglé.')) ?></div>
        </div>
        <span class="badge <?= $moisAlertes ? 'badge-late' : 'badge-valid' ?>"><?= count($moisAlertes) ?> <?= h(t('mois chargé(s)')) ?></span>
    </div>
    <div class="card-body">
        <div class="month-alert-summary">
            <div class="month-alert-stat">
                <span class="month-alert-label"><?= h(t('Total des mois affichés')) ?></span>
                <strong><?= h(money($totalMois)) ?></strong>
            </div>
            <div class="month-alert-stat">
                <span class="month-alert-label"><?= h(t('Moyenne mensuelle')) ?></span>
                <strong><?= h(money($moyenneMensuelle)) ?></strong>
            </div>
            <div class="month-alert-stat">
                <span class="month-alert-label"><?= h(t("Seuil d'alerte")) ?></span>
                <strong><?= $seuilMois > 0 ? h(money($seuilMois)) : '—' ?></strong>
                <small><?= $seuilManuel ? h(t('Défini manuellement')) : h(t('1,25 × moyenne de l’encours')) ?></small>
            </div>
            <div class="month-alert-stat">
                <span class="month-alert-label"><?= h(t('Mois chargés')) ?></span>
                <strong class="<?= $moisAlertes ? 'is-danger' : '' ?>"><?= count($moisAlertes) ?> / <?= $nbMois ?></strong>
                <small><?= h(t('À éviter pour un nouveau document')) ?></small>
            </div>
        </div>

        <?php if ($moisAlertes): ?>
            <div class="month-alert-list">
                <?php foreach ($moisAlertes as $m): ?>
                    <div class="month-alert-item<?= $m['niveau'] === 'critique' ? ' is-critical' : '' ?>">
                        <div class="month-alert-month"><?= h(month_label($m['mois'])) ?></div>
                        <div class="month-alert-meta">
                            <span><?= h(t('Encours non réglé')) ?> : <strong><?= h(money($m['encours'])) ?></strong></span>
                            <span><?= h(t('Dépassement')) ?> : <strong>+<?= h(money($m['depassement'])) ?></strong></span>
                        </div>
                        <span class="badge <?= $m['niveau'] === 'critique' ? 'badge-late' : 'badge-soon' ?>">
                            <?= h($m['niveau'] === 'critique' ? t('Critique') : t('Éviter / décaler')) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="info-box">
                <?= h(t('Aucun mois ne dépasse le seuil d’alerte.')) ?>
            </div>
        <?php endif; ?>

        <?php if ($moisSuggeres): ?>
            <div class="month-suggest">
                <span class="month-suggest-label"><?= h(t('Mois à privilégier')) ?> :</span>
                <?php foreach ($moisSuggeres as $m): ?>
                    <span class="badge badge-valid">
                        <?= h(month_label($m['mois'])) ?> · <?= h(money($m['encours'])) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="hint month-alert-hint">
            <?= h(t('Le seuil automatique correspond à 1,25 × la moyenne de l’encours des mois affichés. Utilisez un seuil manuel pour adapter l’alerte à votre trésorerie.')) ?>
        </p>
    </div>
</div>

<div class="card stats-section mt-16">
    <div class="card-header">
        <div>
            <div class="card-title"><?= h(t('Totaux mensuels')) ?></div>
            <div class="card-sub"><?= h(t('Totaux par mois, triés par montant ou par date.')) ?> · <?= h($typeDateLabel) ?></div>
        </div>
        <span class="badge badge-neutral"><?= count($moisRows) ?> <?= h(t('mois')) ?></span>
    </div>
    <div class="card-body">
        <?php if (!$moisRows): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
                    </svg>
                </div>
                <div class="empty-title"><?= h(t('Aucune donnée sur cette période')) ?></div>
                <div class="empty-text"><?= h(t('Modifiez les filtres pour afficher des statistiques.')) ?></div>
            </div>
        <?php else: ?>
            <div class="stats-chart">
                <?php foreach ($moisChrono as $m): ?>
                    <?php $chartAlerte = $seuilMois > 0 && (float) $m['montant_non_regle'] >= $seuilMois; ?>
                    <div class="stats-chart-col" title="<?= h(month_label($m['mois']) . ' : ' . money($m['montant']) . ' · ' . t('Encours non réglé') . ' : ' . money($m['montant_non_regle'])) ?>">
                        <span class="stats-chart-value"><?= (int) $m['nombre'] ?></span>
                        <div class="stats-chart-track">
                            <div class="stats-chart-fill<?= $chartAlerte ? ' is-alert' : '' ?>" style="height: <?= h(bar_height((float) $m['montant'], $maxMois)) ?>"></div>
                        </div>
                        <span class="stats-chart-label"><?= h(mois_court($m['mois'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="table-wrap mt-16">
                <table class="data">
                    <thead>
                    <tr>
                        <th><?= h(t('Rang')) ?></th>
                        <th><?= h(t('Mois')) ?></th>
                        <th class="text-right"><?= h(t('Documents')) ?></th>
                        <th class="text-right"><?= h(t('Montant total')) ?></th>
                        <th class="text-right"><?= h(t('Réglés')) ?></th>
                        <th class="text-right"><?= h(t('Montant réglé')) ?></th>
                        <th class="text-right"><?= h(t('Non réglés')) ?></th>
                        <th class="text-right"><?= h(t('Encours non réglé')) ?></th>
                        <th class="text-right"><?= h(t('Charge')) ?></th>
                        <th><?= h(t('Alerte')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($moisTri as $m): ?>
                        <?php
                        $encoursMois = (float) $m['montant_non_regle'];
                        $charge = charge_pct($encoursMois, $seuilMois);
                        $rowAlerte = $seuilMois > 0 && $encoursMois >= $seuilMois;
                        $rowVigilance = $seuilMois > 0 && !$rowAlerte && $charge !== null && $charge >= 75;
                        ?>
                        <tr class="<?= $rowAlerte ? 'row-alert' : '' ?>">
                            <td class="mono">#<?= (int) ($rangMois[(string) $m['mois']] ?? 0) ?></td>
                            <td class="cell-main"><?= h(month_label($m['mois'])) ?></td>
                            <td class="text-right mono"><?= (int) $m['nombre'] ?></td>
                            <td class="text-right mono strong"><?= h(money($m['montant'])) ?></td>
                            <td class="text-right mono"><?= (int) $m['nombre_regle'] ?></td>
                            <td class="text-right mono"><?= h(money($m['montant_regle'])) ?></td>
                            <td class="text-right mono"><?= (int) $m['nombre_non_regle'] ?></td>
                            <td class="text-right mono"><?= h(money($encoursMois)) ?></td>
                            <td class="text-right">
                                <?php if ($charge === null): ?>
                                    <span class="mono">—</span>
                                <?php else: ?>
                                    <div class="charge-cell">
                                        <div class="charge-bar <?= $rowAlerte ? 'is-danger' : ($rowVigilance ? 'is-warn' : '') ?>">
                                            <span style="width: <?= min(100, max(2, $charge)) ?>%"></span>
                                        </div>
                                        <span class="charge-label"><?= $charge ?> %</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($seuilMois <= 0): ?>
                                    <span class="mono">—</span>
                                <?php elseif ($rowAlerte): ?>
                                    <span class="badge badge-late"><?= h(t('Éviter')) ?></span>
                                <?php elseif ($rowVigilance): ?>
                                    <span class="badge badge-soon"><?= h(t('Surveiller')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-valid"><?= h(t('Disponible')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card stats-section">
    <div class="card-header">
        <div>
            <div class="card-title"><?= h(t('Par carnet / registre')) ?></div>
            <div class="card-sub"><?= h(t("Répartition des documents selon leur carnet d'origine")) ?></div>
        </div>
        <span class="badge badge-neutral"><?= count($parCarnet) ?> <?= h(t('carnet(s)')) ?></span>
    </div>
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
                <th class="text-right"><?= h(t('Montant')) ?></th>
                <th class="text-right"><?= h(t('Part')) ?></th>
                <th class="text-right"><?= h(t('Réglés')) ?></th>
                <th class="text-right"><?= h(t('Montant réglé')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$parCarnet): ?>
                <tr><td colspan="10" class="stats-empty-cell"><?= h(t('Aucun carnet pour ces filtres.')) ?></td></tr>
            <?php else: ?>
                <?php foreach ($parCarnet as $c): ?>
                    <tr>
                        <td class="cell-main"><?= h($c['libelle'] ?: '—') ?></td>
                        <td class="mono"><?= $c['code'] !== null ? h($c['code']) : '—' ?></td>
                        <td>
                            <span class="badge <?= $c['type'] === 'EFFET' ? 'badge-effet' : 'badge-cheque' ?>"><?= h(type_label($c['type'] ?? '')) ?></span>
                        </td>
                        <td><?= h($c['banque'] ?: '—') ?></td>
                        <td><?= h($c['personne_nom'] ?: '—') ?></td>
                        <td class="text-right mono"><?= (int) $c['nombre'] ?></td>
                        <td class="text-right mono strong"><?= h(money($c['montant'])) ?></td>
                        <td class="text-right"><?= pct($c['montant'], $montantTotal) ?></td>
                        <td class="text-right mono"><?= (int) $c['nombre_regle'] ?></td>
                        <td class="text-right mono"><?= h(money($c['montant_regle'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Par bénéficiaire')) ?></div>
                <div class="card-sub"><?= h(t('Top des bénéficiaires par montant')) ?></div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th><?= h(t('Bénéficiaire')) ?></th>
                    <th class="text-right"><?= h(t('Docs')) ?></th>
                    <th class="text-right"><?= h(t('Montant')) ?></th>
                    <th class="text-right"><?= h(t('Part')) ?></th>
                    <th class="text-right"><?= h(t('Réglés')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$parBeneficiaire): ?>
                    <tr><td colspan="5" class="stats-empty-cell"><?= h(t('Aucune donnée.')) ?></td></tr>
                <?php else: ?>
                    <?php foreach ($parBeneficiaire as $b): ?>
                        <tr>
                            <td class="cell-main"><?= h($b['nom']) ?></td>
                            <td class="text-right mono"><?= (int) $b['nombre'] ?></td>
                            <td class="text-right mono strong"><?= h(money($b['montant'])) ?></td>
                            <td class="text-right"><?= pct($b['montant'], $montantTotal) ?></td>
                            <td class="text-right"><span class="badge badge-valid"><?= (int) $b['nombre_regle'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Par banque')) ?></div>
                <div class="card-sub"><?= h(t('Répartition des montants par banque')) ?></div>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th><?= h(t('Banque')) ?></th>
                    <th class="text-right"><?= h(t('Docs')) ?></th>
                    <th class="text-right"><?= h(t('Montant')) ?></th>
                    <th class="text-right"><?= h(t('Part')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$parBanque): ?>
                    <tr><td colspan="4" class="stats-empty-cell"><?= h(t('Aucune donnée.')) ?></td></tr>
                <?php else: ?>
                    <?php foreach ($parBanque as $b): ?>
                        <tr>
                            <td class="cell-main"><?= h($b['banque']) ?></td>
                            <td class="text-right mono"><?= (int) $b['nombre'] ?></td>
                            <td class="text-right mono strong"><?= h(money($b['montant'])) ?></td>
                            <td class="text-right"><?= pct($b['montant'], $montantTotal) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        (function ($) {
        $('#moisEcheance').on('change', function () {
            var value = $(this).val();
            if (!value) {
                $('#dateFrom').val('');
                $('#dateTo').val('');
                return;
            }
            var parts = value.split('-');
            var year = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10);
            var last = new Date(year, month, 0).getDate();
            $('#dateFrom').val(value + '-01');
            $('#dateTo').val(value + '-' + String(last).padStart(2, '0'));
            $('#typeDate').val('ECHEANCE');
            $(this).closest('form').trigger('submit');
        });
        })(jQuery);
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

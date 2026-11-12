<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_login();

$title = 'Tableau de bord';
$active = 'dashboard';
$user = current_user();

$stats = fetch_one("
    SELECT
        COUNT(*)                                                                         AS total_documents,
        COALESCE(SUM(montant), 0)                                                        AS montant_total,
        COALESCE(SUM(type = 'CHEQUE'), 0)                                                AS total_cheques,
        COALESCE(SUM(CASE WHEN type = 'CHEQUE' THEN montant ELSE 0 END), 0)              AS montant_cheques,
        COALESCE(SUM(type = 'EFFET'), 0)                                                 AS total_effets,
        COALESCE(SUM(CASE WHEN type = 'EFFET' THEN montant ELSE 0 END), 0)               AS montant_effets,
        COALESCE(SUM(regle = 1), 0)                                                      AS total_regles,
        COALESCE(SUM(CASE WHEN regle = 1 THEN montant ELSE 0 END), 0)                    AS montant_regle,
        COALESCE(SUM(regle = 0 AND rejete = 0), 0)                                       AS total_non_regles,
        COALESCE(SUM(CASE WHEN regle = 0 AND rejete = 0 THEN montant ELSE 0 END), 0)     AS montant_non_regle,
        COALESCE(SUM(rejete = 1), 0)                                                     AS total_rejetes,
        COALESCE(SUM(CASE WHEN rejete = 1 THEN montant ELSE 0 END), 0)                   AS montant_rejete,
        COALESCE(SUM(regle = 0 AND rejete = 0 AND date_echeance < CURDATE()), 0)         AS en_retard,
        COALESCE(SUM(regle = 0 AND rejete = 0 AND date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)), 0)  AS dans7,
        COALESCE(SUM(regle = 0 AND rejete = 0 AND date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)), 0) AS dans30
    FROM document_paiement
");
$totalDocuments = (int) ($stats['total_documents'] ?? 0);
$totalRegles = (int) ($stats['total_regles'] ?? 0);
$pourcentageRegle = $totalDocuments > 0 ? (int) round($totalRegles * 100 / $totalDocuments) : 0;

$prochaines = fetch_all("
    SELECT d.*, b.nom AS beneficiaire_nom, c.libelle AS carnet_libelle
    FROM document_paiement d
    JOIN beneficiaire b ON b.id = d.beneficiaire_id
    LEFT JOIN carnet c ON c.id = d.carnet_id
    WHERE d.regle = 0 AND d.rejete = 0
    ORDER BY d.date_echeance ASC, d.id ASC
    LIMIT 8
");

$startMonth = date('Y-m-01', strtotime('-5 months'));
$repartitionRows = fetch_all("
    SELECT DATE_FORMAT(date_echeance, '%Y-%m') AS mois,
           COUNT(*) AS nombre,
           COALESCE(SUM(montant), 0) AS montant
    FROM document_paiement
    WHERE date_echeance >= ?
    GROUP BY mois
    ORDER BY mois
", [$startMonth]);

$repartition = [];
$maxMois = 0.0;
for ($i = 5; $i >= 0; $i--) {
    $mois = date('Y-m', strtotime("-$i months"));
    $row = null;
    foreach ($repartitionRows as $candidate) {
        if ($candidate['mois'] === $mois) {
            $row = $candidate;
            break;
        }
    }
    $montant = (float) ($row['montant'] ?? 0);
    $maxMois = max($maxMois, $montant);
    $repartition[] = [
        'mois'    => $mois,
        'label'   => month_short($mois),
        'nombre'  => (int) ($row['nombre'] ?? 0),
        'montant' => $montant,
    ];
}

function month_short(string $mois): string
{
    $labels = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $parts = explode('-', $mois);
    if (count($parts) !== 2) {
        return $mois;
    }
    return $labels[max(0, min(11, (int) $parts[1] - 1))] . ' ' . substr($parts[0], 2);
}

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Bonjour')) ?>, <?= h($user['nom'] ?? '') ?></h1>
        <p class="page-sub"><?= h(t('Voici la situation de vos chèques et effets.')) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= h(url('documents/export.php?format=pdf')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            <?= h(t('Exporter PDF')) ?>
        </a>
        <a class="btn btn-primary" href="<?= h(url('documents/form.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            <?= h(t('Nouveau document')) ?>
        </a>
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
        <div class="kpi-value"><?= (int) $totalDocuments ?></div>
        <div class="kpi-meta"><strong><?= h(money($stats['montant_total'])) ?></strong> <?= h(t('au total')) ?></div>
    </div>

    <div class="kpi">
        <div class="kpi-head">
            <span class="kpi-label"><?= h(t('Chèques')) ?></span>
            <span class="kpi-icon gold">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18"/><path d="M7 14h4"/>
                </svg>
            </span>
        </div>
        <div class="kpi-value"><?= (int) $stats['total_cheques'] ?></div>
        <div class="kpi-meta"><strong><?= h(money($stats['montant_cheques'])) ?></strong></div>
    </div>

    <div class="kpi">
        <div class="kpi-head">
            <span class="kpi-label"><?= h(t('Effets')) ?></span>
            <span class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h6M9 15h6M9 18h3"/>
                </svg>
            </span>
        </div>
        <div class="kpi-value"><?= (int) $stats['total_effets'] ?></div>
        <div class="kpi-meta"><strong><?= h(money($stats['montant_effets'])) ?></strong></div>
    </div>

    <div class="kpi kpi-red">
        <div class="kpi-head">
            <span class="kpi-label"><?= h(t('En retard')) ?></span>
            <span class="kpi-icon red">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/>
                </svg>
            </span>
        </div>
        <div class="kpi-value"><?= (int) $stats['en_retard'] ?></div>
        <div class="kpi-meta"><?= h(t('Échéance dépassée et non réglé')) ?></div>
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
        <div class="kpi-value"><?= (int) $stats['total_rejetes'] ?></div>
        <div class="kpi-meta"><strong><?= h(money($stats['montant_rejete'])) ?></strong></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Prochaines échéances')) ?></div>
                <div class="card-sub"><?= h(t('Les 8 documents à surveiller')) ?></div>
            </div>
            <a class="link-btn" href="<?= h(url('documents/index.php')) ?>"><?= h(t('Voir tout')) ?></a>
        </div>
        <div class="card-body tight">
            <?php if (!$prochaines): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="3" y="5" width="18" height="16" rx="2"/>
                            <path d="M3 10h18M8 3v4M16 3v4"/>
                        </svg>
                    </div>
                    <div class="empty-title"><?= h(t('Aucune échéance à venir')) ?></div>
                    <div class="empty-text"><?= h(t('Vos prochains documents apparaîtront ici.')) ?></div>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                        <tr>
                            <th><?= h(t('Échéance')) ?></th>
                            <th><?= h(t('Bénéficiaire / N°')) ?></th>
                            <th class="text-right"><?= h(t('Montant')) ?></th>
                            <th class="text-right"><?= h(t('Délai')) ?></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prochaines as $doc): ?>
                            <?php $jours = days_until($doc['date_echeance']); ?>
                            <tr>
                                <td class="nowrap mono"><?= h(date_fr($doc['date_echeance'])) ?></td>
                                <td>
                                    <div class="cell-main"><?= h($doc['beneficiaire_nom']) ?></div>
                                    <div class="cell-sub">
                                        <?= h(type_label($doc['type'])) ?> n° <?= h($doc['numero']) ?>
                                        <?php if (!empty($doc['carnet_libelle'])): ?> · <?= h($doc['carnet_libelle']) ?><?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-right mono strong"><?= h(money($doc['montant'])) ?></td>
                                <td class="text-right"><?= status_badge($doc) ?></td>
                                <td class="text-right">
                                    <a class="icon-btn" title="<?= h(t('Fiche PDF')) ?>" href="<?= h(url('documents/pdf.php?id=' . (int) $doc['id'])) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M6 9V4h12v5"/><path d="M6 18H4v-7h16v7h-2"/>
                                            <rect x="6" y="14" width="12" height="7" rx="1"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Répartition mensuelle')) ?></div>
                <div class="card-sub"><?= h(t("Montants par mois d'échéance")) ?></div>
            </div>
        </div>
        <div class="card-body">
            <div class="bar-chart">
                <?php foreach ($repartition as $i => $m): ?>
                    <?php
                    $height = $maxMois > 0 ? max(4, (int) round($m['montant'] * 100 / $maxMois)) : 4;
                    $isLast = $i === count($repartition) - 1;
                    ?>
                    <div class="bar-col" title="<?= h(month_label($m['mois']) . ' : ' . money($m['montant'])) ?>">
                        <span class="bar-value"><?= (int) $m['nombre'] ?></span>
                        <div class="bar-track">
                            <div class="bar-fill<?= $isLast ? ' gold' : '' ?>" style="height: <?= $height ?>%"></div>
                        </div>
                        <span class="bar-label"><?= h($m['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <span><i class="dot dot-navy"></i> <?= h(t("Montant des échéances")) ?></span>
                <span class="muted"><?= h(t('Le nombre au-dessus indique le nombre de documents')) ?></span>
            </div>
        </div>
    </div>
</div>

<div class="grid-2 equal">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Règlements')) ?></div>
                <div class="card-sub"><?= (int) $totalRegles ?> <?= h(t('réglé(s) sur')) ?> <?= (int) $totalDocuments ?></div>
            </div>
            <span class="badge badge-valid"><?= (int) $pourcentageRegle ?> %</span>
        </div>
        <div class="card-body">
            <div class="progress">
                <div class="progress-fill" style="width: <?= (int) $pourcentageRegle ?>%"></div>
            </div>
            <div class="reglement-grid">
                <div>
                    <div class="reglement-label"><span class="dot dot-green"></span> <?= h(t('Réglés')) ?></div>
                    <div class="reglement-value"><?= (int) $totalRegles ?></div>
                    <div class="reglement-amount"><?= h(money($stats['montant_regle'])) ?></div>
                </div>
                <div>
                    <div class="reglement-label"><span class="dot dot-red"></span> <?= h(t('Non réglés')) ?></div>
                    <div class="reglement-value"><?= (int) $stats['total_non_regles'] ?></div>
                    <div class="reglement-amount"><?= h(money($stats['montant_non_regle'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('À surveiller')) ?></div>
                <div class="card-sub"><?= h(t('Échéances rapprochées')) ?></div>
            </div>
        </div>
        <div class="card-body">
            <div class="watch-list">
                <div class="watch-item">
                    <span class="watch-dot" style="background: var(--amber)"></span>
                    <div>
                        <div class="watch-title"><?= h(t('Dans les 7 prochains jours')) ?></div>
                        <div class="watch-sub"><?= h(t('Documents non réglés à échéance proche')) ?></div>
                    </div>
                    <span class="watch-count"><?= (int) $stats['dans7'] ?></span>
                </div>
                <div class="watch-item">
                    <span class="watch-dot" style="background: var(--navy-600)"></span>
                    <div>
                        <div class="watch-title"><?= h(t('Dans les 30 prochains jours')) ?></div>
                        <div class="watch-sub"><?= h(t('À anticiper pour la trésorerie')) ?></div>
                    </div>
                    <span class="watch-count"><?= (int) $stats['dans30'] ?></span>
                </div>
                <div class="watch-item">
                    <span class="watch-dot" style="background: var(--red)"></span>
                    <div>
                        <div class="watch-title"><?= h(t('En retard')) ?></div>
                        <div class="watch-sub"><?= h(t('Échéance dépassée, document non réglé')) ?></div>
                    </div>
                    <span class="watch-count danger"><?= (int) $stats['en_retard'] ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

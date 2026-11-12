<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Calendrier de distribution';
$active = 'calendrier';

/* ------------------------------------------------------------------ */
/* Mois affiché                                                       */
/* ------------------------------------------------------------------ */
$mois = (string) param('mois', date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
    $mois = date('Y-m');
}
[$moisDebut, $moisFin] = month_bounds($mois);
if ($moisDebut === null || $moisFin === null) {
    $mois = date('Y-m');
    [$moisDebut, $moisFin] = month_bounds($mois);
}

$seuilMoisParam = float_param('seuil_mois');
$seuilSemaineParam = float_param('seuil_semaine');

$simMontantParam = float_param('sim_montant');
$simDate = (string) param('sim_date');
$simType = (string) param('sim_type', 'CHEQUE');
if (!in_array($simType, ['CHEQUE', 'EFFET'], true)) {
    $simType = 'CHEQUE';
}

$simulationDemandee = $simMontantParam !== null && $simMontantParam > 0 && isValidDate($simDate);
if ($simulationDemandee) {
    $mois = substr($simDate, 0, 7);
    [$moisDebut, $moisFin] = month_bounds($mois);
}

/* ------------------------------------------------------------------ */
/* Fonctions locales                                                   */
/* ------------------------------------------------------------------ */
function cal_level(float $value, float $threshold): string
{
    if ($threshold <= 0) {
        return 'neutral';
    }
    if ($value >= $threshold) {
        return 'danger';
    }
    if ($value >= $threshold * 0.75) {
        return 'warn';
    }
    return 'ok';
}

function cal_level_label(string $level): string
{
    return match ($level) {
        'danger' => t('Chargé'),
        'warn' => t('À surveiller'),
        'ok' => t('Disponible'),
        default => t('Seuil non défini'),
    };
}

function cal_sim_label(string $level): string
{
    return match ($level) {
        'danger' => t('Créneau chargé'),
        'warn' => t('Créneau à surveiller'),
        'ok' => t('Bon créneau'),
        default => t('Seuil non défini'),
    };
}

function cal_badge_class(string $level): string
{
    return match ($level) {
        'danger' => 'badge-late',
        'warn' => 'badge-soon',
        'ok' => 'badge-valid',
        default => 'badge-neutral',
    };
}

function cal_level_class(string $level): string
{
    return 'is-' . $level;
}

function cal_charge_pct(float $value, float $threshold): ?int
{
    if ($threshold <= 0) {
        return null;
    }
    return (int) round($value * 100 / $threshold);
}

function cal_short_date(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d/m', $ts) : (string) $iso;
}

function cal_doc_status_label(array $doc): string
{
    if (!empty($doc['est_regle'])) {
        return t('Réglé');
    }
    if (!empty($doc['est_rejete'])) {
        return t('Rejeté');
    }
    return t('Non réglé');
}

function cal_month_url(string $mois, ?float $seuilMois, ?float $seuilSemaine): string
{
    return query_url('calendrier/index.php', [
        'mois' => $mois,
        'seuil_mois' => $seuilMois !== null && $seuilMois > 0 ? $seuilMois : null,
        'seuil_semaine' => $seuilSemaine !== null && $seuilSemaine > 0 ? $seuilSemaine : null,
    ]);
}

/* ------------------------------------------------------------------ */
/* Documents affichés (mois + jours voisins des semaines complètes)     */
/* ------------------------------------------------------------------ */
$premierJour = new DateTimeImmutable($moisDebut);
$dernierJour = new DateTimeImmutable($moisFin);
$grilleDebut = $premierJour->modify('monday this week')->format('Y-m-d');
$grilleFin = $dernierJour->modify('sunday this week')->format('Y-m-d');

$documents = fetch_all(
    "SELECT d.id, d.numero, d.type, d.montant, d.date_echeance, d.regle, d.rejete, d.banque,
            b.nom AS beneficiaire,
            c.libelle AS carnet_libelle, c.code AS carnet_code
     FROM document_paiement d
     JOIN beneficiaire b ON b.id = d.beneficiaire_id
     LEFT JOIN carnet c ON c.id = d.carnet_id
     WHERE d.date_echeance BETWEEN ? AND ?
     ORDER BY d.date_echeance ASC, d.montant DESC, d.id ASC",
    [$grilleDebut, $grilleFin]
);

$docsParJour = [];
$parJour = [];
$totalMois = 0.0;
$encoursMois = 0.0;
$nbMois = 0;
$nbEncoursMois = 0;

foreach ($documents as $document) {
    $jour = (string) $document['date_echeance'];
    $dansMois = $jour >= $moisDebut && $jour <= $moisFin;
    $montant = (float) $document['montant'];
    $estRegle = bit_true($document['regle']);
    $estRejete = bit_true($document['rejete']);
    $encours = (!$estRegle && !$estRejete) ? $montant : 0.0;

    $document['est_regle'] = $estRegle;
    $document['est_rejete'] = $estRejete;
    $document['encours'] = $encours;
    $document['statut'] = $estRegle ? 'regle' : ($estRejete ? 'rejete' : 'encours');

    $docsParJour[$jour][] = $document;

    if (!isset($parJour[$jour])) {
        $parJour[$jour] = [
            'date' => $jour,
            'nombre' => 0,
            'montant' => 0.0,
            'encours' => 0.0,
            'nombre_encours' => 0,
        ];
    }
    $parJour[$jour]['nombre']++;
    $parJour[$jour]['montant'] += $montant;
    $parJour[$jour]['encours'] += $encours;
    if ($encours > 0) {
        $parJour[$jour]['nombre_encours']++;
    }

    if ($dansMois) {
        $totalMois += $montant;
        $encoursMois += $encours;
        $nbMois++;
        if ($encours > 0) {
            $nbEncoursMois++;
        }
    }
}

/* ------------------------------------------------------------------ */
/* Seuils                                                              */
/* ------------------------------------------------------------------ */
$moisEncoursRows = fetch_all(
    "SELECT DATE_FORMAT(date_echeance, '%Y-%m') AS mois,
            COALESCE(SUM(montant), 0) AS encours
     FROM document_paiement
     WHERE regle = 0 AND rejete = 0 AND date_echeance IS NOT NULL
     GROUP BY mois"
);
$totalEncoursHistorique = 0.0;
foreach ($moisEncoursRows as $row) {
    $totalEncoursHistorique += (float) $row['encours'];
}
$nbMoisHistorique = count($moisEncoursRows);
$moyenneEncoursMensuelle = $nbMoisHistorique > 0 ? $totalEncoursHistorique / $nbMoisHistorique : 0.0;
$seuilMoisAuto = $moyenneEncoursMensuelle * 1.25;

$seuilMoisManuel = $seuilMoisParam !== null && $seuilMoisParam > 0;
$seuilMois = $seuilMoisManuel ? (float) $seuilMoisParam : $seuilMoisAuto;

$seuilSemaineAuto = $seuilMois > 0 ? $seuilMois / 4.33 : 0.0;
$seuilSemaineManuel = $seuilSemaineParam !== null && $seuilSemaineParam > 0;
$seuilSemaine = $seuilSemaineManuel ? (float) $seuilSemaineParam : $seuilSemaineAuto;
$seuilJour = $seuilSemaine > 0 ? $seuilSemaine / 7 : 0.0;

/* ------------------------------------------------------------------ */
/* Navigation                                                          */
/* ------------------------------------------------------------------ */
$moisDate = new DateTimeImmutable($moisDebut);
$moisPrecedent = $moisDate->modify('-1 month')->format('Y-m');
$moisSuivant = $moisDate->modify('+1 month')->format('Y-m');
$moisAujourdhui = date('Y-m');

$lienPrecedent = cal_month_url($moisPrecedent, $seuilMoisParam, $seuilSemaineParam);
$lienSuivant = cal_month_url($moisSuivant, $seuilMoisParam, $seuilSemaineParam);
$lienAujourdhui = cal_month_url($moisAujourdhui, $seuilMoisParam, $seuilSemaineParam);
$lienAuto = cal_month_url($mois, null, null);

/* ------------------------------------------------------------------ */
/* Grille calendrier                                                   */
/* ------------------------------------------------------------------ */
$curseur = new DateTimeImmutable($grilleDebut);
$finGrille = new DateTimeImmutable($grilleFin);

$semaines = [];
$indexSemaine = 0;
while ($curseur <= $finGrille) {
    $indexSemaine++;
    $debutSemaine = $curseur;
    $finSemaine = $curseur->modify('+6 days');
    $jours = [];
    $semaineTotal = 0.0;
    $semaineEncours = 0.0;
    $semaineNombre = 0;

    for ($i = 0; $i < 7; $i++) {
        $jourDate = $debutSemaine->modify('+' . $i . ' days');
        $jourIso = $jourDate->format('Y-m-d');
        $dansMois = $jourIso >= $moisDebut && $jourIso <= $moisFin;
        $jourData = $parJour[$jourIso] ?? null;

        if ($jourData !== null) {
            $semaineTotal += (float) $jourData['montant'];
            $semaineEncours += (float) $jourData['encours'];
            $semaineNombre += (int) $jourData['nombre'];
        }

        $jours[] = [
            'date' => $jourIso,
            'jour' => (int) $jourDate->format('j'),
            'dow' => (int) $jourDate->format('N'),
            'dans_mois' => $dansMois,
            'is_today' => $jourIso === today_iso(),
            'data' => $jourData,
            'docs' => $docsParJour[$jourIso] ?? [],
        ];
    }

    $dateDebutSemaine = $debutSemaine->format('Y-m-d');
    $dateFinSemaine = $finSemaine->format('Y-m-d');
    $semaines[] = [
        'index' => $indexSemaine,
        'date_debut' => $dateDebutSemaine,
        'date_fin' => $dateFinSemaine,
        'range' => cal_short_date($dateDebutSemaine) . ' – ' . cal_short_date($dateFinSemaine),
        'jours' => $jours,
        'total' => $semaineTotal,
        'encours' => $semaineEncours,
        'nombre' => $semaineNombre,
        'niveau' => cal_level($semaineEncours, $seuilSemaine),
    ];

    $curseur = $curseur->modify('+7 days');
}

$moisNiveau = cal_level($encoursMois, $seuilMois);
$moisPct = cal_charge_pct($encoursMois, $seuilMois);

$semainePlusChargee = null;
foreach ($semaines as $semaine) {
    if ($semainePlusChargee === null || (float) $semaine['encours'] > (float) $semainePlusChargee['encours']) {
        $semainePlusChargee = $semaine;
    }
}

/* ------------------------------------------------------------------ */
/* Simulateur                                                          */
/* ------------------------------------------------------------------ */
$simulation = null;
if ($simulationDemandee) {
    $simDateObj = new DateTimeImmutable($simDate);
    $simSemaineDebut = $simDateObj->modify('monday this week')->format('Y-m-d');
    $simSemaineFin = $simDateObj->modify('sunday this week')->format('Y-m-d');
    $simSemaineEncours = (float) scalar(
        'SELECT COALESCE(SUM(montant), 0) FROM document_paiement
         WHERE regle = 0 AND rejete = 0 AND date_echeance BETWEEN ? AND ?',
        [$simSemaineDebut, $simSemaineFin],
        0
    );
    $simJourEncours = (float) scalar(
        'SELECT COALESCE(SUM(montant), 0) FROM document_paiement
         WHERE regle = 0 AND rejete = 0 AND date_echeance = ?',
        [$simDate],
        0
    );

    $simMoisApres = $encoursMois + (float) $simMontantParam;
    $simSemaineApres = $simSemaineEncours + (float) $simMontantParam;
    $simJourApres = $simJourEncours + (float) $simMontantParam;
    $simMoisNiveau = cal_level($simMoisApres, $seuilMois);
    $simSemaineNiveau = cal_level($simSemaineApres, $seuilSemaine);
    $simNiveau = 'ok';
    if ($simMoisNiveau === 'danger' || $simSemaineNiveau === 'danger') {
        $simNiveau = 'danger';
    } elseif ($simMoisNiveau === 'warn' || $simSemaineNiveau === 'warn') {
        $simNiveau = 'warn';
    } elseif ($simMoisNiveau === 'neutral' || $simSemaineNiveau === 'neutral') {
        $simNiveau = 'neutral';
    }

    $simulation = [
        'date' => $simDate,
        'date_label' => date_fr($simDate),
        'semaine_label' => t('Sem.') . ' ' . $simDateObj->format('W') . ' · ' . cal_short_date($simSemaineDebut) . ' – ' . cal_short_date($simSemaineFin),
        'montant' => (float) $simMontantParam,
        'type' => $simType,
        'mois_avant' => $encoursMois,
        'mois_apres' => $simMoisApres,
        'mois_pct' => cal_charge_pct($simMoisApres, $seuilMois),
        'mois_niveau' => $simMoisNiveau,
        'semaine_avant' => $simSemaineEncours,
        'semaine_apres' => $simSemaineApres,
        'semaine_pct' => cal_charge_pct($simSemaineApres, $seuilSemaine),
        'semaine_niveau' => $simSemaineNiveau,
        'jour_avant' => $simJourEncours,
        'jour_apres' => $simJourApres,
        'niveau' => $simNiveau,
        'label' => cal_sim_label($simNiveau),
    ];
}

$simDateValue = $simDate !== '' ? $simDate : (($mois === date('Y-m')) ? today_iso() : $moisDebut);

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Calendrier de distribution')) ?></h1>
        <p class="page-sub"><?= h(t('Planifiez vos nouveaux chèques/effets selon la charge réelle de chaque mois et de chaque semaine.')) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= h(query_url('rapports/pdf.php', array_merge($_GET, ['rapport' => 'calendrier']))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>
            </svg>
            <?= h(t('Exporter PDF')) ?>
        </a>
        <a class="btn btn-ghost" href="<?= h(url('statistiques/index.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
            </svg>
            <?= h(t('Statistiques')) ?>
        </a>
        <a class="btn btn-primary" href="<?= h(url('documents/form.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            <?= h(t('Nouveau document')) ?>
        </a>
    </div>
</div>

<div class="card cal-toolbar">
    <div class="cal-toolbar-top">
        <div class="cal-month-nav">
            <a class="icon-btn" href="<?= h($lienPrecedent) ?>" aria-label="<?= h(t('Mois précédent')) ?>" title="<?= h(t('Mois précédent')) ?>">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </a>
            <form method="get" action="<?= h(url('calendrier/index.php')) ?>" class="cal-month-form" data-auto-submit>
                <?php if ($seuilMoisManuel): ?>
                    <input type="hidden" name="seuil_mois" value="<?= h((string) $seuilMoisParam) ?>">
                <?php endif; ?>
                <?php if ($seuilSemaineManuel): ?>
                    <input type="hidden" name="seuil_semaine" value="<?= h((string) $seuilSemaineParam) ?>">
                <?php endif; ?>
                <label class="cal-month-label" for="calMois"><?= h(t('Mois')) ?></label>
                <input id="calMois" name="mois" type="month" class="input cal-month-input" value="<?= h($mois) ?>">
            </form>
            <a class="icon-btn" href="<?= h($lienSuivant) ?>" aria-label="<?= h(t('Mois suivant')) ?>" title="<?= h(t('Mois suivant')) ?>">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </a>
            <a class="btn btn-ghost btn-sm" href="<?= h($lienAujourdhui) ?>"><?= h(t("Aujourd'hui")) ?></a>
        </div>
        <div class="cal-toolbar-note">
            <?= h(t('Les totaux incluent les documents réglés, non réglés et rejetés. Les alertes utilisent l’encours non réglé.')) ?>
        </div>
    </div>

    <form method="get" action="<?= h(url('calendrier/index.php')) ?>" class="cal-threshold-form">
        <input type="hidden" name="mois" value="<?= h($mois) ?>">
        <div class="field">
            <label for="calSeuilMois"><?= h(t('Seuil mensuel (DH)')) ?></label>
            <input id="calSeuilMois" name="seuil_mois" type="number" min="0" step="0.01" class="input"
                   value="<?= $seuilMoisManuel ? h((string) $seuilMoisParam) : '' ?>"
                   placeholder="<?= h(t('Automatique')) ?>">
        </div>
        <div class="field">
            <label for="calSeuilSemaine"><?= h(t('Seuil hebdomadaire (DH)')) ?></label>
            <input id="calSeuilSemaine" name="seuil_semaine" type="number" min="0" step="0.01" class="input"
                   value="<?= $seuilSemaineManuel ? h((string) $seuilSemaineParam) : '' ?>"
                   placeholder="<?= h(t('Automatique')) ?>">
        </div>
        <div class="cal-threshold-actions">
            <button type="submit" class="btn btn-primary btn-sm"><?= h(t('Appliquer')) ?></button>
            <a class="btn btn-ghost btn-sm" href="<?= h($lienAuto) ?>"><?= h(t('Auto')) ?></a>
        </div>
    </form>
    <p class="hint cal-toolbar-hint">
        <?= h(t('Seuil mensuel automatique = 1,25 × moyenne de l’encours des mois enregistrés. Seuil hebdomadaire automatique = seuil mensuel ÷ 4,33.')) ?>
    </p>
</div>

<div class="cal-summary">
    <div class="cal-summary-card">
        <span class="cal-summary-label"><?= h(t('Total du mois')) ?></span>
        <strong><?= h(money($totalMois)) ?></strong>
        <small><?= (int) $nbMois ?> <?= h(t('document(s)')) ?> · <?= h(month_label($mois)) ?></small>
    </div>
    <div class="cal-summary-card">
        <span class="cal-summary-label"><?= h(t('Encours du mois')) ?></span>
        <strong><?= h(money($encoursMois)) ?></strong>
        <small><?= (int) $nbEncoursMois ?> <?= h(t('document(s) non réglé(s)')) ?></small>
    </div>
    <div class="cal-summary-card">
        <span class="cal-summary-label"><?= h(t('Seuils de charge')) ?></span>
        <strong><?= $seuilMois > 0 ? h(money($seuilMois)) : '—' ?></strong>
        <small><?= h(t('Mensuel')) ?> · <?= $seuilSemaine > 0 ? h(money($seuilSemaine)) : '—' ?> <?= h(t('hebdomadaire')) ?></small>
    </div>
    <div class="cal-summary-card">
        <span class="cal-summary-label"><?= h(t('Semaine la plus chargée')) ?></span>
        <?php if ($semainePlusChargee !== null && (float) $semainePlusChargee['encours'] > 0): ?>
            <strong><?= h(money($semainePlusChargee['encours'])) ?></strong>
            <small><?= h(t('Sem.')) ?> <?= (int) $semainePlusChargee['index'] ?> · <?= h($semainePlusChargee['range']) ?></small>
        <?php else: ?>
            <strong>—</strong>
            <small><?= h(t('Aucune charge sur ce mois')) ?></small>
        <?php endif; ?>
    </div>
</div>

<div class="cal-layout">
    <div class="card cal-card">
        <div class="card-header cal-card-head">
            <div>
                <div class="card-title"><?= h(month_label($mois)) ?></div>
                <div class="card-sub"><?= h(t('Distribution des échéances par semaine et par jour.')) ?></div>
            </div>
            <div class="cal-legend" aria-label="<?= h(t('Légende')) ?>">
                <span class="cal-legend-item"><span class="cal-legend-dot is-ok"></span><?= h(t('Disponible')) ?></span>
                <span class="cal-legend-item"><span class="cal-legend-dot is-warn"></span><?= h(t('À surveiller')) ?></span>
                <span class="cal-legend-item"><span class="cal-legend-dot is-danger"></span><?= h(t('Chargé')) ?></span>
                <span class="cal-legend-sep"></span>
                <span class="cal-legend-item"><span class="cal-doc-dot is-cheque"></span><?= h(t('Chèque')) ?></span>
                <span class="cal-legend-item"><span class="cal-doc-dot is-effet"></span><?= h(t('Effet')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($nbMois === 0): ?>
                <div class="info-box cal-empty-info">
                    <?= h(t('Aucun document sur ce mois. Le calendrier reste disponible pour planifier vos nouvelles échéances.')) ?>
                </div>
            <?php endif; ?>

            <div class="cal-scroll">
                <div class="cal-board">
                    <div class="cal-week cal-week-header">
                        <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $jourLabel): ?>
                            <div class="cal-dow"><?= h(t($jourLabel)) ?></div>
                        <?php endforeach; ?>
                        <div class="cal-dow cal-dow-week"><?= h(t('Semaine')) ?></div>
                    </div>

                    <?php foreach ($semaines as $semaine): ?>
                        <div class="cal-week">
                            <?php foreach ($semaine['jours'] as $jour): ?>
                                <?php
                                $dayClasses = ['cal-day'];
                                if (!$jour['dans_mois']) {
                                    $dayClasses[] = 'is-out';
                                }
                                if ($jour['is_today']) {
                                    $dayClasses[] = 'is-today';
                                }
                                if ($jour['dow'] >= 6) {
                                    $dayClasses[] = 'is-weekend';
                                }
                                if ($jour['data'] !== null) {
                                    $dayClasses[] = 'has-docs';
                                }
                                ?>
                                <div class="<?= h(implode(' ', $dayClasses)) ?>" data-date="<?= h($jour['date']) ?>">
                                    <div class="cal-day-head">
                                        <a class="cal-day-num" href="<?= h(query_url('documents/index.php', ['echeance_from' => $jour['date'], 'echeance_to' => $jour['date']])) ?>">
                                            <?= (int) $jour['jour'] ?>
                                        </a>
                                        <?php if ($jour['data'] !== null): ?>
                                            <span class="cal-day-count"><?= (int) $jour['data']['nombre'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($jour['data'] !== null): ?>
                                        <div class="cal-day-total"><?= h(money($jour['data']['montant'])) ?></div>
                                        <div class="cal-day-sub"><?= h(money($jour['data']['encours'])) ?> <?= h(t('encours')) ?></div>
                                        <div class="cal-docs">
                                            <?php foreach (array_slice($jour['docs'], 0, 3) as $document): ?>
                                                <?php
                                                $docTitle = $document['numero'] . ' · ' . $document['beneficiaire']
                                                    . ' · ' . money($document['montant'])
                                                    . ' · ' . cal_doc_status_label($document);
                                                ?>
                                                <div class="cal-doc is-<?= h(strtolower((string) $document['type'])) ?> is-<?= h((string) $document['statut']) ?>"
                                                     title="<?= h($docTitle) ?>">
                                                    <span class="cal-doc-dot is-<?= h(strtolower((string) $document['type'])) ?>"></span>
                                                    <span class="cal-doc-num"><?= h($document['numero']) ?></span>
                                                    <span class="cal-doc-amt"><?= h(money($document['montant'])) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($jour['docs']) > 3): ?>
                                                <div class="cal-more">+<?= count($jour['docs']) - 3 ?> <?= h(t('autre(s)')) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="cal-week-summary <?= h(cal_level_class($semaine['niveau'])) ?>">
                                <div class="cal-week-top">
                                    <span class="cal-week-name"><?= h(t('Sem.')) ?> <?= (int) $semaine['index'] ?></span>
                                    <span class="badge <?= h(cal_badge_class($semaine['niveau'])) ?>"><?= h(cal_level_label($semaine['niveau'])) ?></span>
                                </div>
                                <div class="cal-week-range"><?= h($semaine['range']) ?></div>
                                <div class="cal-week-total"><?= h(money($semaine['total'])) ?></div>
                                <div class="cal-week-sub"><?= h(money($semaine['encours'])) ?> <?= h(t('encours')) ?> · <?= (int) $semaine['nombre'] ?> <?= h(t('docs')) ?></div>
                                <a class="cal-week-link" href="<?= h(query_url('documents/index.php', ['echeance_from' => $semaine['date_debut'], 'echeance_to' => $semaine['date_fin']])) ?>">
                                    <?= h(t('Voir')) ?> →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="cal-month-total <?= h(cal_level_class($moisNiveau)) ?>">
                <div class="cal-month-total-main">
                    <div class="cal-month-total-label"><?= h(t('Total du mois')) ?></div>
                    <div class="cal-month-total-sub">
                        <?= (int) $nbMois ?> <?= h(t('document(s)')) ?> ·
                        <?= h(money($encoursMois)) ?> <?= h(t('encours')) ?>
                        <?php if ($moisPct !== null): ?> · <?= (int) $moisPct ?> % <?= h(t('du seuil mensuel')) ?><?php endif; ?>
                    </div>
                </div>
                <div class="cal-month-total-value"><?= h(money($totalMois)) ?></div>
                <span class="badge <?= h(cal_badge_class($moisNiveau)) ?>"><?= h(cal_level_label($moisNiveau)) ?></span>
                <a class="cal-week-link" href="<?= h(query_url('documents/index.php', ['echeance_from' => $moisDebut, 'echeance_to' => $moisFin])) ?>">
                    <?= h(t('Voir')) ?> →
                </a>
            </div>
        </div>
    </div>

    <aside class="cal-side">
        <div class="card cal-sim-card" id="simulateur">
            <div class="card-header">
                <div>
                    <div class="card-title"><?= h(t('Simuler un nouveau document')) ?></div>
                    <div class="card-sub"><?= h(t('Testez une date et un montant avant de créer le document.')) ?></div>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="<?= h(url('calendrier/index.php')) ?>#simulateur" class="cal-sim-form">
                    <input type="hidden" name="mois" value="<?= h($mois) ?>">
                    <?php if ($seuilMoisManuel): ?>
                        <input type="hidden" name="seuil_mois" value="<?= h((string) $seuilMoisParam) ?>">
                    <?php endif; ?>
                    <?php if ($seuilSemaineManuel): ?>
                        <input type="hidden" name="seuil_semaine" value="<?= h((string) $seuilSemaineParam) ?>">
                    <?php endif; ?>

                    <div class="field">
                        <label for="simDate"><?= h(t("Date d'échéance")) ?></label>
                        <input id="simDate" name="sim_date" type="date" class="input" required value="<?= h($simDateValue) ?>">
                    </div>
                    <div class="field">
                        <label for="simMontant"><?= h(t('Montant (DH)')) ?></label>
                        <input id="simMontant" name="sim_montant" type="number" min="0.01" step="0.01" class="input" required
                               value="<?= $simMontantParam !== null ? h((string) $simMontantParam) : '' ?>"
                               placeholder="0,00">
                    </div>
                    <div class="field">
                        <label for="simType"><?= h(t('Type de document')) ?></label>
                        <select id="simType" name="sim_type" class="select">
                            <option value="CHEQUE"<?= $simType === 'CHEQUE' ? ' selected' : '' ?>><?= h(t('Chèque')) ?></option>
                            <option value="EFFET"<?= $simType === 'EFFET' ? ' selected' : '' ?>><?= h(t('Effet')) ?></option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-full"><?= h(t('Simuler')) ?></button>
                </form>

                <?php if ($simulation !== null): ?>
                    <div class="cal-sim-result <?= h(cal_level_class($simulation['niveau'])) ?>">
                        <div class="cal-sim-result-head">
                            <div>
                                <div class="cal-sim-result-title"><?= h($simulation['label']) ?></div>
                                <div class="cal-sim-result-sub"><?= h($simulation['date_label']) ?> · <?= h($simulation['semaine_label']) ?></div>
                            </div>
                            <span class="badge <?= h(cal_badge_class($simulation['niveau'])) ?>"><?= h($simulation['label']) ?></span>
                        </div>

                        <div class="cal-sim-lines">
                            <div class="cal-sim-line">
                                <span><?= h(t('Mois')) ?></span>
                                <strong><?= h(money($simulation['mois_apres'])) ?></strong>
                                <small>
                                    <?= h(money($simulation['mois_avant'])) ?> + <?= h(money($simulation['montant'])) ?>
                                    <?php if ($simulation['mois_pct'] !== null): ?> · <?= (int) $simulation['mois_pct'] ?> % <?= h(t('du seuil')) ?><?php endif; ?>
                                </small>
                            </div>
                            <div class="cal-sim-line">
                                <span><?= h(t('Semaine')) ?></span>
                                <strong><?= h(money($simulation['semaine_apres'])) ?></strong>
                                <small>
                                    <?= h(money($simulation['semaine_avant'])) ?> + <?= h(money($simulation['montant'])) ?>
                                    <?php if ($simulation['semaine_pct'] !== null): ?> · <?= (int) $simulation['semaine_pct'] ?> % <?= h(t('du seuil')) ?><?php endif; ?>
                                </small>
                            </div>
                            <div class="cal-sim-line">
                                <span><?= h(t('Jour')) ?></span>
                                <strong><?= h(money($simulation['jour_apres'])) ?></strong>
                                <small><?= h(money($simulation['jour_avant'])) ?> + <?= h(money($simulation['montant'])) ?></small>
                            </div>
                        </div>

                        <div class="cal-sim-actions">
                            <a class="btn btn-primary btn-sm w-full"
                               href="<?= h(query_url('documents/form.php', [
                                   'date_echeance' => $simulation['date'],
                                   'montant' => $simulation['montant'],
                                   'type' => $simulation['type'],
                               ])) ?>">
                                <?= h(t('Créer le document')) ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="hint cal-sim-hint">
                    <?= h(t('La simulation utilise l’encours non réglé. Un document réglé ou rejeté ne compte pas dans la charge.')) ?>
                </p>
            </div>
        </div>

        <div class="card cal-tip-card">
            <div class="card-header">
                <div>
                    <div class="card-title"><?= h(t('Comment lire le calendrier ?')) ?></div>
                </div>
            </div>
            <div class="card-body">
                <ul class="mini-list cal-tips">
                    <li><?= h(t('Chaque ligne représente une semaine ; le total de la semaine est à droite.')) ?></li>
                    <li><?= h(t('Les couleurs indiquent la charge par rapport aux seuils : vert disponible, orange à surveiller, rouge chargé.')) ?></li>
                    <li><?= h(t('Cliquez sur une date pour voir les documents du jour.')) ?></li>
                    <li><?= h(t('Les semaines complètes incluent les jours des mois voisins ; le total mensuel reste limité au mois affiché.')) ?></li>
                </ul>
            </div>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

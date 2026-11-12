<?php
declare(strict_types=1);

/**
 * Construction des rapports PDF.
 *
 * Toutes les fonctions de ce fichier renvoient des données déjà formatées
 * pour l'affichage PDF (français). Les montants sont exprimés en dirhams.
 */

function report_catalog(): array
{
    return [
        'synthese' => [
            'label'       => 'Synthèse globale',
            'description' => 'Tous les indicateurs clés : montants, règlements, encours, retards et répartitions.',
            'icon'        => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        ],
        'documents' => [
            'label'       => 'Chèques & effets',
            'description' => 'Liste détaillée des documents avec totaux, filtres et statuts.',
            'icon'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18"/><path d="M7 14h4"/><path d="M7 16.5h7"/>',
        ],
        'beneficiaires' => [
            'label'       => 'Bénéficiaires',
            'description' => 'Coordonnées, nombre de documents, montants et encours par bénéficiaire.',
            'icon'        => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20c0-3.4 2.8-5.6 6.2-5.6s6.2 2.2 6.2 5.6"/><path d="M16.5 4.5a3 3 0 0 1 0 6"/><path d="M18 14.6c2 .6 3.3 2.3 3.3 4.4"/>',
        ],
        'personnes' => [
            'label'       => 'Personnes responsables',
            'description' => 'Carnets rattachés, documents suivis et montants par personne responsable.',
            'icon'        => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20c0-3.4 2.8-5.6 6.2-5.6s6.2 2.2 6.2 5.6"/><path d="M17 8h5M19.5 5.5v5"/>',
        ],
        'carnets' => [
            'label'       => 'Carnets & registres',
            'description' => 'Détail par carnet : propriétaire, banque, documents, montants et encours.',
            'icon'        => '<path d="M5 4h11a2 2 0 0 1 2 2v14H7a2 2 0 0 1-2-2z"/><path d="M5 4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h1"/><path d="M9 8h5M9 12h5"/>',
        ],
        'statistiques' => [
            'label'       => 'Rapport statistique',
            'description' => 'Analyse complète : répartition par type, totaux mensuels, seuils et classements.',
            'icon'        => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        ],
        'calendrier' => [
            'label'       => 'Calendrier de distribution',
            'description' => 'Planning mensuel par semaine et par jour, avec seuils de charge et détail.',
            'icon'        => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h2M14 14h2M8 17h2"/>',
        ],
    ];
}

function report_type_from_input(array $input): string
{
    $type = strtolower(trim((string) ($input['rapport'] ?? 'synthese')));
    return array_key_exists($type, report_catalog()) ? $type : 'synthese';
}

function report_input_float(array $input, string $key): ?float
{
    $value = $input[$key] ?? null;
    if ($value === null || $value === '') {
        return null;
    }
    $value = str_replace([' ', ','], ['', '.'], trim((string) $value));
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float) $value;
    return $number >= 0 ? $number : null;
}

function report_input_month(array $input, string $key, string $default = ''): string
{
    $value = trim((string) ($input[$key] ?? ''));
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? $value : $default;
}

function report_normalize_filters(array $input): array
{
    $type = strtoupper(trim((string) ($input['type'] ?? '')));
    $typeBeneficiaire = strtoupper(trim((string) ($input['type_beneficiaire'] ?? '')));
    if (!in_array($typeBeneficiaire, ['SOCIETE', 'PERSONNE'], true)
        && in_array($type, ['SOCIETE', 'PERSONNE'], true)) {
        $typeBeneficiaire = $type;
    }

    $role = strtoupper(trim((string) ($input['role'] ?? '')));
    if (!in_array($role, ['PROPRIETAIRE_SOCIETE', 'GERANT', 'EMPLOYE'], true)) {
        $role = '';
    }

    $statut = (string) ($input['statut'] ?? $input['regle'] ?? '');
    if (!in_array($statut, ['true', 'false', 'rejete'], true)) {
        $statut = '';
    }

    $dateBasis = strtoupper((string) ($input['date_basis'] ?? $input['type_date'] ?? 'ECHEANCE'));
    if (!in_array($dateBasis, ['ECHEANCE', 'EMISSION', 'REGLEMENT'], true)) {
        $dateBasis = 'ECHEANCE';
    }

    $dateFrom = (string) ($input['date_from'] ?? '');
    $dateTo = (string) ($input['date_to'] ?? '');
    $echeanceFrom = (string) ($input['echeance_from'] ?? '');
    $echeanceTo = (string) ($input['echeance_to'] ?? '');

    $jours = (int) ($input['jours'] ?? 0);
    if (!in_array($jours, [7, 15, 30, 60, 90], true)) {
        $jours = 0;
    }

    $moisEcheance = report_input_month($input, 'mois_echeance');
    $moisEmission = report_input_month($input, 'mois_emission');
    $mois = report_input_month($input, 'mois', date('Y-m'));

    $seuilMois = report_input_float($input, 'seuil_mois');
    $seuilSemaine = report_input_float($input, 'seuil_semaine');

    return [
        'q'                  => mb_substr(trim((string) ($input['q'] ?? '')), 0, 120),
        'type'               => in_array($type, ['CHEQUE', 'EFFET'], true) ? $type : '',
        'type_beneficiaire'  => $typeBeneficiaire,
        'role'               => $role,
        'statut'             => $statut,
        'beneficiaire'       => max(0, (int) ($input['beneficiaire'] ?? 0)),
        'personne'           => max(0, (int) ($input['personne'] ?? 0)),
        'carnet'             => max(0, (int) ($input['carnet'] ?? 0)),
        'banque'             => mb_substr(trim((string) ($input['banque'] ?? '')), 0, 100),
        'date_from'          => isValidDate($dateFrom) ? $dateFrom : '',
        'date_to'            => isValidDate($dateTo) ? $dateTo : '',
        'echeance_from'      => isValidDate($echeanceFrom) ? $echeanceFrom : '',
        'echeance_to'        => isValidDate($echeanceTo) ? $echeanceTo : '',
        'date_basis'         => $dateBasis,
        'mois_echeance'      => $moisEcheance,
        'mois_emission'      => $moisEmission,
        'jours'              => $jours,
        'montant_min'        => report_input_float($input, 'montant_min'),
        'montant_max'        => report_input_float($input, 'montant_max'),
        'mois'               => $mois,
        'seuil_mois'         => $seuilMois !== null && $seuilMois > 0 ? $seuilMois : null,
        'seuil_semaine'      => $seuilSemaine !== null && $seuilSemaine > 0 ? $seuilSemaine : null,
    ];
}

function report_date_col(string $alias, string $basis): string
{
    $column = match ($basis) {
        'EMISSION' => 'date_emission',
        'REGLEMENT' => 'date_reglement',
        default => 'date_echeance',
    };
    return $alias . '.' . $column;
}

function report_date_basis_label(string $basis): string
{
    return match ($basis) {
        'EMISSION' => "date d'émission",
        'REGLEMENT' => 'date de règlement',
        default => "date d'échéance",
    };
}

/**
 * Construit la clause WHERE des documents.
 *
 * @return array{0:string,1:array<int,mixed>}
 */
function report_document_conditions(
    array $f,
    string $alias = 'd',
    bool $withSearch = true,
    bool $withBeneficiaire = true,
    ?string $dateBasis = null
): array {
    $conditions = [];
    $params = [];
    $a = $alias;

    if ($withSearch && $f['q'] !== '') {
        $conditions[] = '(' . $a . '.numero LIKE ? OR b.nom LIKE ? OR p.nom LIKE ? OR '
            . $a . '.banque LIKE ? OR c.libelle LIKE ? OR ' . $a . '.remarque LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    if ($f['type'] !== '') {
        $conditions[] = $a . '.type = ?';
        $params[] = $f['type'];
    }
    if ($f['statut'] === 'true') {
        $conditions[] = $a . '.regle = 1';
    } elseif ($f['statut'] === 'false') {
        $conditions[] = $a . '.regle = 0 AND ' . $a . '.rejete = 0';
    } elseif ($f['statut'] === 'rejete') {
        $conditions[] = $a . '.rejete = 1';
    }
    if ($withBeneficiaire && $f['beneficiaire'] > 0) {
        $conditions[] = $a . '.beneficiaire_id = ?';
        $params[] = $f['beneficiaire'];
    }
    if ($f['personne'] > 0) {
        $conditions[] = $a . '.personne_id = ?';
        $params[] = $f['personne'];
    }
    if ($f['carnet'] > 0) {
        $conditions[] = $a . '.carnet_id = ?';
        $params[] = $f['carnet'];
    }
    if ($f['banque'] !== '') {
        $conditions[] = $a . '.banque LIKE ?';
        $params[] = '%' . $f['banque'] . '%';
    }
    if ($f['mois_echeance'] !== '') {
        $conditions[] = "DATE_FORMAT(" . $a . ".date_echeance, '%Y-%m') = ?";
        $params[] = $f['mois_echeance'];
    }
    if ($f['mois_emission'] !== '') {
        $conditions[] = "DATE_FORMAT(" . $a . ".date_emission, '%Y-%m') = ?";
        $params[] = $f['mois_emission'];
    }

    $basis = $dateBasis ?? $f['date_basis'];
    if ($f['date_from'] !== '') {
        $conditions[] = report_date_col($a, $basis) . ' >= ?';
        $params[] = $f['date_from'];
    }
    if ($f['date_to'] !== '') {
        $conditions[] = report_date_col($a, $basis) . ' <= ?';
        $params[] = $f['date_to'];
    }
    if ($f['echeance_from'] !== '') {
        $conditions[] = $a . '.date_echeance >= ?';
        $params[] = $f['echeance_from'];
    }
    if ($f['echeance_to'] !== '') {
        $conditions[] = $a . '.date_echeance <= ?';
        $params[] = $f['echeance_to'];
    }
    if ($f['jours'] > 0) {
        $conditions[] = $a . '.date_echeance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL '
            . (int) $f['jours'] . ' DAY)';
    }
    if ($f['montant_min'] !== null) {
        $conditions[] = $a . '.montant >= ?';
        $params[] = $f['montant_min'];
    }
    if ($f['montant_max'] !== null) {
        $conditions[] = $a . '.montant <= ?';
        $params[] = $f['montant_max'];
    }

    return [$conditions ? ' WHERE ' . implode(' AND ', $conditions) : '', $params];
}

function report_join_condition(string $where): string
{
    if ($where === '') {
        return '';
    }
    $condition = preg_replace('/^\s*WHERE\s+/i', '', $where) ?? '';
    return $condition !== '' ? ' AND ' . $condition : '';
}

function report_filters_summary(string $type, array $f): string
{
    $parts = [];

    if ($f['q'] !== '') {
        $parts[] = 'Recherche : ' . $f['q'];
    }
    if ($f['type'] !== '') {
        $parts[] = 'Type : ' . ($f['type'] === 'EFFET' ? 'Effets' : 'Chèques');
    }
    if ($f['type_beneficiaire'] !== '') {
        $parts[] = 'Type de bénéficiaire : ' . ($f['type_beneficiaire'] === 'SOCIETE' ? 'Société' : 'Personne');
    }
    if ($f['role'] !== '') {
        $parts[] = 'Rôle : ' . report_role_label($f['role']);
    }
    if ($f['statut'] === 'true') {
        $parts[] = 'Réglés';
    } elseif ($f['statut'] === 'false') {
        $parts[] = 'Non réglés';
    } elseif ($f['statut'] === 'rejete') {
        $parts[] = 'Rejetés';
    }
    if ($f['beneficiaire'] > 0) {
        $beneficiaire = fetch_one('SELECT nom FROM beneficiaire WHERE id = ?', [$f['beneficiaire']]);
        if ($beneficiaire) {
            $parts[] = 'Bénéficiaire : ' . $beneficiaire['nom'];
        }
    }
    if ($f['personne'] > 0) {
        $personne = fetch_one('SELECT nom FROM personne WHERE id = ?', [$f['personne']]);
        if ($personne) {
            $parts[] = 'Personne : ' . $personne['nom'];
        }
    }
    if ($f['carnet'] > 0) {
        $carnet = fetch_one('SELECT libelle, code FROM carnet WHERE id = ?', [$f['carnet']]);
        if ($carnet) {
            $parts[] = 'Carnet : ' . carnet_label($carnet);
        }
    }
    if ($f['banque'] !== '') {
        $parts[] = 'Banque : ' . $f['banque'];
    }
    if ($f['mois_echeance'] !== '') {
        $parts[] = 'Échéance : ' . month_label($f['mois_echeance']);
    }
    if ($f['mois_emission'] !== '') {
        $parts[] = 'Émission : ' . month_label($f['mois_emission']);
    }

    if ($f['date_from'] !== '' || $f['date_to'] !== '') {
        $basis = $type === 'documents' || in_array($type, ['beneficiaires', 'personnes', 'carnets'], true)
            ? 'EMISSION'
            : $f['date_basis'];
        $parts[] = ucfirst(report_date_basis_label($basis)) . ' du '
            . ($f['date_from'] !== '' ? date_fr($f['date_from']) : '…')
            . ' au ' . ($f['date_to'] !== '' ? date_fr($f['date_to']) : '…');
    }
    if ($f['echeance_from'] !== '' || $f['echeance_to'] !== '') {
        $parts[] = 'Échéance du '
            . ($f['echeance_from'] !== '' ? date_fr($f['echeance_from']) : '…')
            . ' au ' . ($f['echeance_to'] !== '' ? date_fr($f['echeance_to']) : '…');
    }
    if ($f['jours'] > 0) {
        $parts[] = 'Échéance dans ' . $f['jours'] . ' jours';
    }
    if ($f['montant_min'] !== null || $f['montant_max'] !== null) {
        $parts[] = 'Montant : '
            . ($f['montant_min'] !== null ? money($f['montant_min']) : '0 DH')
            . ' – ' . ($f['montant_max'] !== null ? money($f['montant_max']) : '∞');
    }

    if ($type === 'calendrier') {
        $parts[] = 'Mois : ' . month_label($f['mois']);
        if ($f['seuil_mois'] !== null) {
            $parts[] = 'Seuil mensuel : ' . money($f['seuil_mois']);
        }
        if ($f['seuil_semaine'] !== null) {
            $parts[] = 'Seuil hebdomadaire : ' . money($f['seuil_semaine']);
        }
    }

    if ($parts) {
        return implode(' · ', $parts);
    }

    return match ($type) {
        'documents' => 'Tous les documents',
        'beneficiaires' => 'Tous les bénéficiaires',
        'personnes' => 'Toutes les personnes responsables',
        'carnets' => 'Tous les carnets et registres',
        'calendrier' => 'Calendrier de ' . month_label($f['mois']),
        default => 'Toutes les données',
    };
}

function report_kpi(string $label, string $value, string $meta = '', string $tone = 'navy'): array
{
    return ['label' => $label, 'value' => $value, 'meta' => $meta, 'tone' => $tone];
}

function report_role_label(?string $role): string
{
    return match ($role) {
        'PROPRIETAIRE_SOCIETE' => 'Propriétaire',
        'GERANT' => 'Gérant',
        'EMPLOYE' => 'Employé',
        default => (string) $role,
    };
}

function report_beneficiaire_type_label(?string $type): string
{
    return $type === 'SOCIETE' ? 'Société' : 'Personne';
}

function report_document_type_label(?string $type): string
{
    return $type === 'EFFET' ? 'Effet' : 'Chèque';
}

function report_document_type_plural(?string $type): string
{
    return $type === 'EFFET' ? 'Effets' : 'Chèques';
}

function report_status_label(array $doc): string
{
    return match (document_status($doc)) {
        'regle' => 'Réglé',
        'rejete' => 'Rejeté',
        'retard' => 'En retard',
        'bientot' => 'À échéance',
        default => 'Non réglé',
    };
}

function report_number(float|int|string|null $value): string
{
    return number_format((float) $value, 0, ',', ' ');
}

function report_amount(float|int|string|null $value): string
{
    return number_format((float) $value, 2, ',', ' ');
}

function report_pct(float|int|string|null $value, float $total): string
{
    if ($total <= 0) {
        return '0 %';
    }
    return (int) round(((float) $value) * 100 / $total) . ' %';
}

/**
 * Statistiques calculées à partir des lignes de documents.
 */
function report_stats_from_rows(array $rows): array
{
    $stats = [
        'n' => 0,
        'montant' => 0.0,
        'n_regle' => 0,
        'montant_regle' => 0.0,
        'n_non_regle' => 0,
        'montant_non_regle' => 0.0,
        'n_rejete' => 0,
        'montant_rejete' => 0.0,
        'n_retard' => 0,
        'montant_retard' => 0.0,
        'n_cheque' => 0,
        'montant_cheque' => 0.0,
        'n_effet' => 0,
        'montant_effet' => 0.0,
    ];

    foreach ($rows as $row) {
        $montant = (float) ($row['montant'] ?? 0);
        $regle = bit_true($row['regle'] ?? false);
        $rejete = bit_true($row['rejete'] ?? false);
        $retard = !$regle && !$rejete && (string) ($row['date_echeance'] ?? '') < today_iso();
        $type = (string) ($row['type'] ?? '');

        $stats['n']++;
        $stats['montant'] += $montant;
        if ($regle) {
            $stats['n_regle']++;
            $stats['montant_regle'] += $montant;
        } elseif ($rejete) {
            $stats['n_rejete']++;
            $stats['montant_rejete'] += $montant;
        } else {
            $stats['n_non_regle']++;
            $stats['montant_non_regle'] += $montant;
            if ($retard) {
                $stats['n_retard']++;
                $stats['montant_retard'] += $montant;
            }
        }
        if ($type === 'CHEQUE') {
            $stats['n_cheque']++;
            $stats['montant_cheque'] += $montant;
        } elseif ($type === 'EFFET') {
            $stats['n_effet']++;
            $stats['montant_effet'] += $montant;
        }
    }

    return $stats;
}

function report_document_stats_sql(string $joins, string $where, array $params): array
{
    $row = fetch_one(
        'SELECT
            COUNT(*) AS n,
            COALESCE(SUM(d.montant), 0) AS montant,
            COALESCE(SUM(d.regle = 1), 0) AS n_regle,
            COALESCE(SUM(CASE WHEN d.regle = 1 THEN d.montant ELSE 0 END), 0) AS montant_regle,
            COALESCE(SUM(d.regle = 0 AND d.rejete = 0), 0) AS n_non_regle,
            COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS montant_non_regle,
            COALESCE(SUM(d.rejete = 1), 0) AS n_rejete,
            COALESCE(SUM(CASE WHEN d.rejete = 1 THEN d.montant ELSE 0 END), 0) AS montant_rejete,
            COALESCE(SUM(d.regle = 0 AND d.rejete = 0 AND d.date_echeance < CURDATE()), 0) AS n_retard,
            COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 AND d.date_echeance < CURDATE()
                THEN d.montant ELSE 0 END), 0) AS montant_retard,
            COALESCE(SUM(d.type = \'CHEQUE\'), 0) AS n_cheque,
            COALESCE(SUM(CASE WHEN d.type = \'CHEQUE\' THEN d.montant ELSE 0 END), 0) AS montant_cheque,
            COALESCE(SUM(d.type = \'EFFET\'), 0) AS n_effet,
            COALESCE(SUM(CASE WHEN d.type = \'EFFET\' THEN d.montant ELSE 0 END), 0) AS montant_effet
        ' . $joins . $where,
        $params
    );

    return $row ?: [
        'n' => 0,
        'montant' => 0,
        'n_regle' => 0,
        'montant_regle' => 0,
        'n_non_regle' => 0,
        'montant_non_regle' => 0,
        'n_rejete' => 0,
        'montant_rejete' => 0,
        'n_retard' => 0,
        'montant_retard' => 0,
        'n_cheque' => 0,
        'montant_cheque' => 0,
        'n_effet' => 0,
        'montant_effet' => 0,
    ];
}

function report_load_level(float $encours, float $seuil): string
{
    if ($seuil <= 0) {
        return 'neutral';
    }
    if ($encours >= $seuil * 1.25) {
        return 'critique';
    }
    if ($encours >= $seuil) {
        return 'eleve';
    }
    if ($encours >= $seuil * 0.75) {
        return 'vigilance';
    }
    return 'normal';
}

function report_load_label(string $level): string
{
    return match ($level) {
        'critique', 'eleve' => 'Éviter',
        'vigilance' => 'Surveiller',
        'normal' => 'Disponible',
        default => '—',
    };
}

function report_charge_value(float $encours, float $seuil): string
{
    if ($seuil <= 0) {
        return '—';
    }
    return (int) round($encours * 100 / $seuil) . ' %';
}

function report_build_documents(array $f): array
{
    [$where, $params] = report_document_conditions($f, 'd', true, true, 'EMISSION');
    $joins = ' FROM document_paiement d
               JOIN beneficiaire b ON b.id = d.beneficiaire_id
               LEFT JOIN personne p ON p.id = d.personne_id
               LEFT JOIN carnet c ON c.id = d.carnet_id';
    $rows = fetch_all(
        'SELECT d.*, b.nom AS beneficiaire_nom, b.type AS beneficiaire_type,
                p.nom AS personne_nom, p.role AS personne_role,
                c.libelle AS carnet_libelle, c.code AS carnet_code'
        . $joins . $where . ' ORDER BY d.date_echeance ASC, d.id ASC',
        $params
    );
    $stats = report_stats_from_rows($rows);
    $total = (float) $stats['montant'];

    $tableRows = [];
    foreach ($rows as $row) {
        $carnet = trim((string) ($row['carnet_libelle'] ?? ''));
        if (($row['carnet_code'] ?? null) !== null && $row['carnet_code'] !== '') {
            $carnet .= ($carnet !== '' ? ' · ' : '') . $row['carnet_code'];
        }
        $tableRows[] = [
            $carnet !== '' ? $carnet : '—',
            (string) $row['numero'],
            $row['type'] === 'EFFET' ? 'Effet' : 'Chèque',
            (string) $row['beneficiaire_nom'],
            (string) ($row['personne_nom'] ?: '—'),
            report_amount($row['montant']),
            date_fr($row['date_emission']),
            date_fr($row['date_echeance']),
            report_status_label($row),
        ];
    }

    return [
        'orientation' => 'L',
        'title' => 'Liste des chèques & effets',
        'subtitle' => 'Détail complet des documents de paiement, avec totaux et statuts.',
        'filters' => report_filters_summary('documents', $f),
        'kpis' => [
            report_kpi('Documents', report_number($stats['n']), 'dans la sélection', 'navy'),
            report_kpi('Montant total', money($total), 'toutes lignes', 'gold'),
            report_kpi('Réglés', report_number($stats['n_regle']), money($stats['montant_regle']), 'green'),
            report_kpi('Non réglés', report_number($stats['n_non_regle']), money($stats['montant_non_regle']), 'navy'),
            report_kpi('En retard', report_number($stats['n_retard']), money($stats['montant_retard']), 'red'),
            report_kpi('Rejetés', report_number($stats['n_rejete']), money($stats['montant_rejete']), 'red'),
        ],
        'sections' => [[
            'title' => 'Détail des documents',
            'note' => 'Montants exprimés en dirhams. Le statut est calculé à la date d’édition.',
            'table' => [
                'headers' => ['Carnet', 'N°', 'Type', 'Bénéficiaire', 'Personne', 'Montant', 'Émission', 'Échéance', 'Statut'],
                'widths' => [120, 55, 45, 145, 120, 85, 65, 65, 85],
                'align' => ['L', 'L', 'L', 'L', 'L', 'R', 'L', 'L', 'L'],
                'rows' => $tableRows,
                'footer' => ['TOTAL', report_number($stats['n']), '', '', '', report_amount($total), '', '', ''],
                'empty' => 'Aucun document ne correspond à ces filtres.',
            ],
        ]],
        'totals' => [
            'label' => 'TOTAL GÉNÉRAL',
            'value' => money($total),
            'meta' => report_number($stats['n']) . ' document(s) · ' . MontantEnLettres::convertir($total),
        ],
        'notes' => [
            'Les documents réglés et rejetés sont exclus de l’encours. L’encours correspond aux documents ni réglés ni rejetés.',
        ],
    ];
}

function report_build_beneficiaires(array $f): array
{
    $conditions = [];
    $params = [];
    if ($f['q'] !== '') {
        $conditions[] = '(b.nom LIKE ? OR b.ice LIKE ? OR b.telephone LIKE ? OR b.email LIKE ? OR b.adresse LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($f['type_beneficiaire'] !== '') {
        $conditions[] = 'b.type = ?';
        $params[] = $f['type_beneficiaire'];
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

    [$docWhere, $docParams] = report_document_conditions($f, 'd', false, false, 'EMISSION');
    $rows = fetch_all(
        'SELECT b.*,
                COUNT(d.id) AS nb_documents,
                COALESCE(SUM(d.montant), 0) AS montant_total,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS encours,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 AND d.date_echeance < CURDATE()
                    THEN d.montant ELSE 0 END), 0) AS retard
         FROM beneficiaire b
         LEFT JOIN document_paiement d ON d.beneficiaire_id = b.id' . report_join_condition($docWhere)
        . $where . '
         GROUP BY b.id, b.nom, b.type, b.ice, b.telephone, b.email, b.adresse, b.created_at
         ORDER BY b.nom ASC',
        array_merge($docParams, $params)
    );

    $totalDocs = 0;
    $totalMontant = 0.0;
    $totalEncours = 0.0;
    $totalRetard = 0.0;
    $tableRows = [];
    foreach ($rows as $row) {
        $docs = (int) $row['nb_documents'];
        $montant = (float) $row['montant_total'];
        $encours = (float) $row['encours'];
        $retard = (float) $row['retard'];
        $totalDocs += $docs;
        $totalMontant += $montant;
        $totalEncours += $encours;
        $totalRetard += $retard;
        $tableRows[] = [
            (string) $row['nom'],
            report_beneficiaire_type_label($row['type']),
            (string) ($row['ice'] ?: '—'),
            (string) ($row['telephone'] ?: '—'),
            report_number($docs),
            report_amount($montant),
            report_amount($encours),
            report_amount($retard),
        ];
    }

    return [
        'orientation' => 'L',
        'title' => 'Rapport des bénéficiaires',
        'subtitle' => 'Coordonnées, volume de documents et montants par bénéficiaire.',
        'filters' => report_filters_summary('beneficiaires', $f),
        'kpis' => [
            report_kpi('Bénéficiaires', report_number(count($rows)), 'dans la sélection', 'navy'),
            report_kpi('Documents', report_number($totalDocs), 'toutes périodes filtrées', 'gold'),
            report_kpi('Montant total', money($totalMontant), 'tous documents', 'navy'),
            report_kpi('Encours', money($totalEncours), 'ni réglé ni rejeté', 'green'),
            report_kpi('En retard', money($totalRetard), 'échéance dépassée', 'red'),
        ],
        'sections' => [[
            'title' => 'Détail par bénéficiaire',
            'note' => 'Les montants portent sur les documents de la période sélectionnée.',
            'table' => [
                'headers' => ['Bénéficiaire', 'Type', 'ICE', 'Téléphone', 'Docs', 'Montant', 'Encours', 'Retard'],
                'widths' => [190, 55, 85, 105, 55, 90, 90, 80],
                'align' => ['L', 'L', 'L', 'L', 'R', 'R', 'R', 'R'],
                'rows' => $tableRows,
                'footer' => ['TOTAL', '', '', '', report_number($totalDocs), report_amount($totalMontant), report_amount($totalEncours), report_amount($totalRetard)],
                'empty' => 'Aucun bénéficiaire ne correspond à ces filtres.',
            ],
        ]],
        'totals' => [
            'label' => 'TOTAL GÉNÉRAL',
            'value' => money($totalMontant),
            'meta' => report_number(count($rows)) . ' bénéficiaire(s) · ' . report_number($totalDocs) . ' document(s)',
        ],
        'notes' => [
            'L’encours regroupe les documents non réglés et non rejetés. Le retard correspond à un encours dont l’échéance est dépassée.',
        ],
    ];
}

function report_build_personnes(array $f): array
{
    $conditions = [];
    $params = [];
    if ($f['q'] !== '') {
        $conditions[] = '(p.nom LIKE ? OR p.telephone LIKE ? OR p.email LIKE ? OR p.adresse LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($f['role'] !== '') {
        $conditions[] = 'p.role = ?';
        $params[] = $f['role'];
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

    [$docWhere, $docParams] = report_document_conditions($f, 'd', false, false, 'EMISSION');
    $rows = fetch_all(
        'SELECT p.*,
                (SELECT COUNT(*) FROM carnet c WHERE c.personne_id = p.id) AS nb_carnets,
                COUNT(d.id) AS nb_documents,
                COALESCE(SUM(d.montant), 0) AS montant_total,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS encours,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 AND d.date_echeance < CURDATE()
                    THEN d.montant ELSE 0 END), 0) AS retard
         FROM personne p
         LEFT JOIN document_paiement d ON d.personne_id = p.id' . report_join_condition($docWhere)
        . $where . '
         GROUP BY p.id, p.nom, p.role, p.telephone, p.email, p.adresse, p.created_at
         ORDER BY p.nom ASC',
        array_merge($docParams, $params)
    );

    $totalDocs = 0;
    $totalCarnets = 0;
    $totalMontant = 0.0;
    $totalEncours = 0.0;
    $tableRows = [];
    foreach ($rows as $row) {
        $docs = (int) $row['nb_documents'];
        $carnets = (int) $row['nb_carnets'];
        $montant = (float) $row['montant_total'];
        $encours = (float) $row['encours'];
        $totalDocs += $docs;
        $totalCarnets += $carnets;
        $totalMontant += $montant;
        $totalEncours += $encours;
        $tableRows[] = [
            (string) $row['nom'],
            report_role_label($row['role']),
            (string) ($row['telephone'] ?: '—'),
            (string) ($row['email'] ?: '—'),
            report_number($carnets),
            report_number($docs),
            report_amount($montant),
            report_amount($encours),
        ];
    }

    return [
        'orientation' => 'L',
        'title' => 'Rapport des personnes responsables',
        'subtitle' => 'Vision par propriétaire, gérant ou employé : carnets, documents et montants.',
        'filters' => report_filters_summary('personnes', $f),
        'kpis' => [
            report_kpi('Personnes', report_number(count($rows)), 'dans la sélection', 'navy'),
            report_kpi('Carnets', report_number($totalCarnets), 'rattachés', 'gold'),
            report_kpi('Documents', report_number($totalDocs), 'suivis', 'navy'),
            report_kpi('Montant total', money($totalMontant), 'tous documents', 'green'),
            report_kpi('Encours', money($totalEncours), 'ni réglé ni rejeté', 'red'),
        ],
        'sections' => [[
            'title' => 'Détail par personne responsable',
            'note' => 'Les carnets sont comptés sur l’ensemble du référentiel ; les documents suivent la période filtrée.',
            'table' => [
                'headers' => ['Personne', 'Rôle', 'Téléphone', 'Email', 'Carnets', 'Docs', 'Montant', 'Encours'],
                'widths' => [160, 80, 95, 150, 50, 50, 80, 80],
                'align' => ['L', 'L', 'L', 'L', 'R', 'R', 'R', 'R'],
                'rows' => $tableRows,
                'footer' => ['TOTAL', '', '', '', report_number($totalCarnets), report_number($totalDocs), report_amount($totalMontant), report_amount($totalEncours)],
                'empty' => 'Aucune personne ne correspond à ces filtres.',
            ],
        ]],
        'totals' => [
            'label' => 'TOTAL GÉNÉRAL',
            'value' => money($totalMontant),
            'meta' => report_number(count($rows)) . ' personne(s) · ' . report_number($totalDocs) . ' document(s)',
        ],
        'notes' => [
            'Un document sans personne responsable n’est pas rattaché à une ligne de ce rapport.',
        ],
    ];
}

function report_build_carnets(array $f): array
{
    $conditions = [];
    $params = [];
    if ($f['q'] !== '') {
        $conditions[] = '(c.libelle LIKE ? OR c.banque LIKE ? OR c.code LIKE ? OR p.nom LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($f['type'] !== '') {
        $conditions[] = 'c.type = ?';
        $params[] = $f['type'];
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

    [$docWhere, $docParams] = report_document_conditions($f, 'd', false, true, 'EMISSION');
    $rows = fetch_all(
        'SELECT c.*, p.nom AS personne_nom, p.role AS personne_role,
                COUNT(d.id) AS nb_documents,
                COALESCE(SUM(d.montant), 0) AS montant_total,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS encours,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 AND d.date_echeance < CURDATE()
                    THEN d.montant ELSE 0 END), 0) AS retard
         FROM carnet c
         LEFT JOIN personne p ON p.id = c.personne_id
         LEFT JOIN document_paiement d ON d.carnet_id = c.id' . report_join_condition($docWhere)
        . $where . '
         GROUP BY c.id, c.libelle, c.type, c.banque, c.created_at, c.updated_at, c.code, c.personne_id, p.nom, p.role
         ORDER BY c.libelle ASC',
        array_merge($docParams, $params)
    );

    $totalDocs = 0;
    $totalMontant = 0.0;
    $totalEncours = 0.0;
    $totalRetard = 0.0;
    $tableRows = [];
    foreach ($rows as $row) {
        $docs = (int) $row['nb_documents'];
        $montant = (float) $row['montant_total'];
        $encours = (float) $row['encours'];
        $retard = (float) $row['retard'];
        $totalDocs += $docs;
        $totalMontant += $montant;
        $totalEncours += $encours;
        $totalRetard += $retard;
        $tableRows[] = [
            (string) $row['libelle'],
            $row['code'] !== null ? (string) $row['code'] : '—',
            report_document_type_label($row['type']),
            (string) ($row['banque'] ?: '—'),
            (string) ($row['personne_nom'] ?: '—'),
            report_number($docs),
            report_amount($montant),
            report_amount($encours),
        ];
    }

    return [
        'orientation' => 'L',
        'title' => 'Rapport des carnets & registres',
        'subtitle' => 'Détail par carnet : propriétaire, banque, volume et montants.',
        'filters' => report_filters_summary('carnets', $f),
        'kpis' => [
            report_kpi('Carnets', report_number(count($rows)), 'dans la sélection', 'navy'),
            report_kpi('Documents', report_number($totalDocs), 'rattachés', 'gold'),
            report_kpi('Montant total', money($totalMontant), 'tous documents', 'navy'),
            report_kpi('Encours', money($totalEncours), 'ni réglé ni rejeté', 'green'),
            report_kpi('En retard', money($totalRetard), 'échéance dépassée', 'red'),
        ],
        'sections' => [[
            'title' => 'Détail par carnet',
            'note' => 'Les montants portent sur les documents de la période sélectionnée.',
            'table' => [
                'headers' => ['Carnet', 'Code', 'Type', 'Banque', 'Propriétaire', 'Docs', 'Montant', 'Encours'],
                'widths' => [150, 55, 55, 120, 140, 50, 90, 90],
                'align' => ['L', 'L', 'L', 'L', 'L', 'R', 'R', 'R'],
                'rows' => $tableRows,
                'footer' => ['TOTAL', '', '', '', '', report_number($totalDocs), report_amount($totalMontant), report_amount($totalEncours)],
                'empty' => 'Aucun carnet ne correspond à ces filtres.',
            ],
        ]],
        'totals' => [
            'label' => 'TOTAL GÉNÉRAL',
            'value' => money($totalMontant),
            'meta' => report_number(count($rows)) . ' carnet(s) · ' . report_number($totalDocs) . ' document(s)',
        ],
        'notes' => [
            'Les carnets sans document restent visibles avec des montants à zéro.',
        ],
    ];
}

function report_build_analysis(array $f, string $type): array
{
    $isSynthese = $type === 'synthese';
    $dateBasis = $f['date_basis'];
    $dateCol = report_date_col('d', $dateBasis);
    [$where, $params] = report_document_conditions($f, 'd', true, true, $dateBasis);
    $joins = ' FROM document_paiement d
               JOIN beneficiaire b ON b.id = d.beneficiaire_id
               LEFT JOIN personne p ON p.id = d.personne_id
               LEFT JOIN carnet c ON c.id = d.carnet_id';

    $stats = report_document_stats_sql($joins, $where, $params);
    $montantTotal = (float) $stats['montant'];

    $moisWhere = $where;
    $moisWhere .= ($moisWhere === '' ? ' WHERE ' : ' AND ') . $dateCol . ' IS NOT NULL';
    $moisRows = fetch_all(
        'SELECT DATE_FORMAT(' . $dateCol . ", '%Y-%m') AS mois,
                COUNT(*) AS n,
                COALESCE(SUM(d.montant), 0) AS montant,
                COALESCE(SUM(d.regle = 1), 0) AS n_regle,
                COALESCE(SUM(CASE WHEN d.regle = 1 THEN d.montant ELSE 0 END), 0) AS montant_regle,
                COALESCE(SUM(d.regle = 0 AND d.rejete = 0), 0) AS n_non_regle,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS montant_non_regle"
        . $joins . $moisWhere . ' GROUP BY mois ORDER BY mois ASC',
        $params
    );

    $nbMois = count($moisRows);
    $totalEncoursMois = 0.0;
    foreach ($moisRows as $row) {
        $totalEncoursMois += (float) $row['montant_non_regle'];
    }
    $moyenneEncours = $nbMois > 0 ? $totalEncoursMois / $nbMois : 0.0;
    $seuilAuto = $moyenneEncours * 1.25;
    $seuilMois = $f['seuil_mois'] !== null ? (float) $f['seuil_mois'] : $seuilAuto;

    $monthRows = [];
    foreach ($moisRows as $row) {
        $encours = (float) $row['montant_non_regle'];
        $niveau = report_load_level($encours, $seuilMois);
        $monthRows[] = [
            month_label((string) $row['mois']),
            report_number($row['n']),
            report_amount($row['montant']),
            report_amount($row['montant_regle']),
            report_amount($encours),
            report_charge_value($encours, $seuilMois),
            report_load_label($niveau),
        ];
    }

    $typeRowsRaw = fetch_all(
        'SELECT d.type,
                COUNT(*) AS n,
                COALESCE(SUM(d.montant), 0) AS montant,
                COALESCE(SUM(d.regle = 1), 0) AS n_regle,
                COALESCE(SUM(CASE WHEN d.regle = 1 THEN d.montant ELSE 0 END), 0) AS montant_regle,
                COALESCE(SUM(d.regle = 0 AND d.rejete = 0), 0) AS n_non_regle,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS montant_non_regle,
                COALESCE(SUM(d.rejete = 1), 0) AS n_rejete'
        . $joins . $where . ' GROUP BY d.type ORDER BY d.type',
        $params
    );
    $typeMap = [];
    foreach ($typeRowsRaw as $row) {
        $typeMap[(string) $row['type']] = $row;
    }
    $typeRows = [];
    foreach (['CHEQUE', 'EFFET'] as $documentType) {
        $row = $typeMap[$documentType] ?? [
            'n' => 0,
            'montant' => 0,
            'n_regle' => 0,
            'montant_regle' => 0,
            'n_non_regle' => 0,
            'montant_non_regle' => 0,
            'n_rejete' => 0,
        ];
        $typeRows[] = [
            $documentType === 'EFFET' ? 'Effets' : 'Chèques',
            report_number($row['n']),
            report_amount($row['montant']),
            report_number($row['n_regle']),
            report_amount($row['montant_non_regle']),
            report_number($row['n_rejete']),
        ];
    }

    $benefRowsRaw = fetch_all(
        'SELECT b.id, b.nom,
                COUNT(*) AS n,
                COALESCE(SUM(d.montant), 0) AS montant,
                COALESCE(SUM(d.regle = 1), 0) AS n_regle,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS encours'
        . $joins . $where . ' GROUP BY b.id, b.nom ORDER BY montant DESC LIMIT 15',
        $params
    );
    $benefRows = [];
    foreach ($benefRowsRaw as $row) {
        $benefRows[] = [
            (string) $row['nom'],
            report_number($row['n']),
            report_amount($row['montant']),
            report_pct($row['montant'], $montantTotal),
            report_number($row['n_regle']),
            report_amount($row['encours']),
        ];
    }

    $banqueRowsRaw = fetch_all(
        "SELECT COALESCE(NULLIF(d.banque, ''), '—') AS banque,
                COUNT(*) AS n,
                COALESCE(SUM(d.montant), 0) AS montant,
                COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS encours"
        . $joins . $where . ' GROUP BY banque ORDER BY montant DESC LIMIT 15',
        $params
    );
    $banqueRows = [];
    foreach ($banqueRowsRaw as $row) {
        $banqueRows[] = [
            (string) $row['banque'],
            report_number($row['n']),
            report_amount($row['montant']),
            report_pct($row['montant'], $montantTotal),
            report_amount($row['encours']),
        ];
    }

    $carnetRows = [];
    if (!$isSynthese) {
        $carnetRowsRaw = fetch_all(
            'SELECT c.libelle, c.code, c.type, c.banque, p.nom AS personne_nom,
                    COUNT(*) AS n,
                    COALESCE(SUM(d.montant), 0) AS montant,
                    COALESCE(SUM(CASE WHEN d.regle = 0 AND d.rejete = 0 THEN d.montant ELSE 0 END), 0) AS encours'
            . $joins . $where . ' GROUP BY c.id, c.libelle, c.code, c.type, c.banque, p.nom
            ORDER BY montant DESC LIMIT 20',
            $params
        );
        foreach ($carnetRowsRaw as $row) {
            $carnetRows[] = [
                (string) $row['libelle'],
                $row['code'] !== null ? (string) $row['code'] : '—',
                report_document_type_label($row['type']),
                (string) ($row['banque'] ?: '—'),
                (string) ($row['personne_nom'] ?: '—'),
                report_number($row['n']),
                report_amount($row['montant']),
                report_amount($row['encours']),
            ];
        }
    }

    $sections = [
        [
            'title' => 'Répartition par type',
            'note' => 'Chèques et effets regroupés sur la période sélectionnée.',
            'table' => [
                'headers' => ['Type', 'Docs', 'Montant', 'Réglés', 'Encours', 'Rejetés'],
                'widths' => [120, 70, 120, 90, 120, 90],
                'align' => ['L', 'R', 'R', 'R', 'R', 'R'],
                'rows' => $typeRows,
                'empty' => 'Aucun document sur cette période.',
            ],
        ],
        [
            'title' => 'Totaux mensuels & charge',
            'note' => 'Seuil d’alerte ' . ($seuilMois > 0 ? money($seuilMois) : 'non défini')
                . ($f['seuil_mois'] !== null ? ' (manuel)' : ' (1,25 × moyenne de l’encours)') . '.',
            'table' => [
                'headers' => ['Mois', 'Docs', 'Montant', 'Réglé', 'Encours', 'Charge', 'Alerte'],
                'widths' => [120, 55, 100, 100, 100, 80, 100],
                'align' => ['L', 'R', 'R', 'R', 'R', 'R', 'L'],
                'rows' => $monthRows,
                'empty' => 'Aucun mois sur cette période.',
            ],
        ],
        [
            'title' => 'Top 15 des bénéficiaires',
            'note' => 'Classement par montant total sur la période.',
            'table' => [
                'headers' => ['Bénéficiaire', 'Docs', 'Montant', 'Part', 'Réglés', 'Encours'],
                'widths' => [240, 60, 110, 70, 70, 110],
                'align' => ['L', 'R', 'R', 'R', 'R', 'R'],
                'rows' => $benefRows,
                'empty' => 'Aucun bénéficiaire sur cette période.',
            ],
        ],
        [
            'title' => 'Top 15 des banques',
            'note' => 'Répartition des montants par banque.',
            'table' => [
                'headers' => ['Banque', 'Docs', 'Montant', 'Part', 'Encours'],
                'widths' => [280, 70, 120, 80, 120],
                'align' => ['L', 'R', 'R', 'R', 'R'],
                'rows' => $banqueRows,
                'empty' => 'Aucune banque sur cette période.',
            ],
        ],
    ];

    if (!$isSynthese) {
        $sections[] = [
            'title' => 'Top 20 des carnets',
            'note' => 'Classement par montant total sur la période.',
            'table' => [
                'headers' => ['Carnet', 'Code', 'Type', 'Banque', 'Propriétaire', 'Docs', 'Montant', 'Encours'],
                'widths' => [150, 50, 50, 110, 130, 50, 90, 90],
                'align' => ['L', 'L', 'L', 'L', 'L', 'R', 'R', 'R'],
                'rows' => $carnetRows,
                'empty' => 'Aucun carnet sur cette période.',
            ],
        ];
    }

    return [
        'orientation' => 'L',
        'title' => $isSynthese ? 'Synthèse globale' : 'Rapport statistique',
        'subtitle' => $isSynthese
            ? 'Vue d’ensemble des montants, règlements, encours et alertes de charge.'
            : 'Analyse détaillée des documents, des mensualités, des bénéficiaires et des banques.',
        'filters' => report_filters_summary($type, $f),
        'kpis' => [
            report_kpi('Documents', report_number($stats['n']), 'dans la sélection', 'navy'),
            report_kpi('Montant total', money($montantTotal), 'tous documents', 'gold'),
            report_kpi('Réglés', report_number($stats['n_regle']), money($stats['montant_regle']), 'green'),
            report_kpi('Encours', report_number($stats['n_non_regle']), money($stats['montant_non_regle']), 'navy'),
            report_kpi('En retard', report_number($stats['n_retard']), money($stats['montant_retard']), 'red'),
            report_kpi('Rejetés', report_number($stats['n_rejete']), money($stats['montant_rejete']), 'red'),
            report_kpi('Seuil d’alerte', $seuilMois > 0 ? money($seuilMois) : '—',
                $f['seuil_mois'] !== null ? 'Défini manuellement' : '1,25 × moyenne de l’encours', 'gold'),
        ],
        'sections' => $sections,
        'totals' => [
            'label' => 'TOTAL GÉNÉRAL',
            'value' => money($montantTotal),
            'meta' => report_number($stats['n']) . ' document(s) · ' . MontantEnLettres::convertir($montantTotal),
        ],
        'notes' => [
            'Période analysée selon la ' . report_date_basis_label($dateBasis) . '.',
            'L’encours correspond aux documents ni réglés ni rejetés. Le seuil automatique est égal à 1,25 × la moyenne de l’encours des mois affichés.',
        ],
    ];
}

function report_build_calendar(array $f): array
{
    $mois = $f['mois'];
    [$moisDebut, $moisFin] = month_bounds($mois);
    if ($moisDebut === null || $moisFin === null) {
        $mois = date('Y-m');
        [$moisDebut, $moisFin] = month_bounds($mois);
    }

    $documents = fetch_all(
        'SELECT d.*, b.nom AS beneficiaire_nom, b.type AS beneficiaire_type,
                c.libelle AS carnet_libelle, c.code AS carnet_code
         FROM document_paiement d
         JOIN beneficiaire b ON b.id = d.beneficiaire_id
         LEFT JOIN carnet c ON c.id = d.carnet_id
         WHERE d.date_echeance BETWEEN ? AND ?
         ORDER BY d.date_echeance ASC, d.montant DESC, d.id ASC',
        [$moisDebut, $moisFin]
    );

    $stats = report_stats_from_rows($documents);
    $totalMois = (float) $stats['montant'];
    $encoursMois = (float) $stats['montant_non_regle'];

    $historique = fetch_all(
        "SELECT DATE_FORMAT(date_echeance, '%Y-%m') AS mois,
                COALESCE(SUM(montant), 0) AS encours
         FROM document_paiement
         WHERE regle = 0 AND rejete = 0 AND date_echeance IS NOT NULL
         GROUP BY mois"
    );
    $totalEncoursHistorique = 0.0;
    foreach ($historique as $row) {
        $totalEncoursHistorique += (float) $row['encours'];
    }
    $nbMoisHistorique = count($historique);
    $moyenneEncours = $nbMoisHistorique > 0 ? $totalEncoursHistorique / $nbMoisHistorique : 0.0;
    $seuilMois = $f['seuil_mois'] !== null ? (float) $f['seuil_mois'] : $moyenneEncours * 1.25;
    $seuilSemaine = $f['seuil_semaine'] !== null
        ? (float) $f['seuil_semaine']
        : ($seuilMois > 0 ? $seuilMois / 4.33 : 0.0);

    $debutGrille = (new DateTimeImmutable($moisDebut))->modify('monday this week');
    $finGrille = (new DateTimeImmutable($moisFin))->modify('sunday this week');
    $curseur = $debutGrille;
    $semaines = [];
    $indexSemaine = 0;
    while ($curseur <= $finGrille) {
        $indexSemaine++;
        $finSemaine = $curseur->modify('+6 days');
        $from = max($moisDebut, $curseur->format('Y-m-d'));
        $to = min($moisFin, $finSemaine->format('Y-m-d'));
        $semaineNombre = 0;
        $semaineTotal = 0.0;
        $semaineEncours = 0.0;
        foreach ($documents as $document) {
            $jour = (string) $document['date_echeance'];
            if ($jour < $from || $jour > $to) {
                continue;
            }
            $montant = (float) $document['montant'];
            $semaineNombre++;
            $semaineTotal += $montant;
            if (!bit_true($document['regle']) && !bit_true($document['rejete'])) {
                $semaineEncours += $montant;
            }
        }
        $niveau = report_load_level($semaineEncours, $seuilSemaine);
        $semaines[] = [
            'index' => $indexSemaine,
            'range' => date_fr($curseur->format('Y-m-d')) . ' – ' . date_fr($finSemaine->format('Y-m-d')),
            'nombre' => $semaineNombre,
            'total' => $semaineTotal,
            'encours' => $semaineEncours,
            'niveau' => $niveau,
        ];
        $curseur = $curseur->modify('+7 days');
    }

    $semainePlusChargee = null;
    foreach ($semaines as $semaine) {
        if ($semainePlusChargee === null || $semaine['encours'] > $semainePlusChargee['encours']) {
            $semainePlusChargee = $semaine;
        }
    }

    $jours = [];
    foreach ($documents as $document) {
        $jour = (string) $document['date_echeance'];
        if (!isset($jours[$jour])) {
            $jours[$jour] = ['nombre' => 0, 'total' => 0.0, 'encours' => 0.0];
        }
        $montant = (float) $document['montant'];
        $jours[$jour]['nombre']++;
        $jours[$jour]['total'] += $montant;
        if (!bit_true($document['regle']) && !bit_true($document['rejete'])) {
            $jours[$jour]['encours'] += $montant;
        }
    }
    ksort($jours);

    $semaineRows = [];
    foreach ($semaines as $semaine) {
        $semaineRows[] = [
            'Sem. ' . $semaine['index'],
            $semaine['range'],
            report_number($semaine['nombre']),
            report_amount($semaine['total']),
            report_amount($semaine['encours']),
            $seuilSemaine > 0 ? report_amount($seuilSemaine) : '—',
            report_load_label($semaine['niveau']),
        ];
    }

    $jourRows = [];
    foreach ($jours as $jour => $data) {
        $jourRows[] = [
            date_fr((string) $jour),
            report_number($data['nombre']),
            report_amount($data['total']),
            report_amount($data['encours']),
        ];
    }

    $detailRows = [];
    foreach ($documents as $document) {
        $carnet = trim((string) ($document['carnet_libelle'] ?? ''));
        if (($document['carnet_code'] ?? null) !== null && $document['carnet_code'] !== '') {
            $carnet .= ($carnet !== '' ? ' · ' : '') . $document['carnet_code'];
        }
        $detailRows[] = [
            $carnet !== '' ? $carnet : '—',
            (string) $document['numero'],
            $document['type'] === 'EFFET' ? 'Effet' : 'Chèque',
            (string) $document['beneficiaire_nom'],
            report_amount($document['montant']),
            date_fr($document['date_echeance']),
            report_status_label($document),
        ];
    }

    return [
        'orientation' => 'P',
        'title' => 'Calendrier de distribution — ' . month_label($mois),
        'subtitle' => 'Planning des échéances par semaine et par jour, avec seuils de charge.',
        'filters' => report_filters_summary('calendrier', $f),
        'kpis' => [
            report_kpi('Total du mois', money($totalMois), report_number($stats['n']) . ' document(s)', 'navy'),
            report_kpi('Encours du mois', money($encoursMois), report_number($stats['n_non_regle']) . ' non réglé(s)', 'gold'),
            report_kpi('Documents', report_number($stats['n']), 'sur le mois', 'navy'),
            report_kpi('Seuil mensuel', $seuilMois > 0 ? money($seuilMois) : '—', 'charge mensuelle', 'green'),
            report_kpi('Seuil hebdomadaire', $seuilSemaine > 0 ? money($seuilSemaine) : '—', 'charge hebdomadaire', 'green'),
            report_kpi('Semaine la plus chargée',
                $semainePlusChargee && $semainePlusChargee['encours'] > 0 ? money($semainePlusChargee['encours']) : '—',
                $semainePlusChargee ? 'Sem. ' . $semainePlusChargee['index'] : 'aucune charge', 'red'),
        ],
        'sections' => [
            [
                'title' => 'Synthèse hebdomadaire',
                'note' => 'Les semaines sont limitées aux jours du mois sélectionné.',
                'table' => [
                    'headers' => ['Semaine', 'Période', 'Docs', 'Total', 'Encours', 'Seuil', 'Niveau'],
                    'widths' => [45, 105, 40, 80, 80, 70, 70],
                    'align' => ['L', 'L', 'R', 'R', 'R', 'R', 'L'],
                    'rows' => $semaineRows,
                    'empty' => 'Aucune semaine sur ce mois.',
                ],
            ],
            [
                'title' => 'Détail par jour',
                'note' => 'Seuls les jours avec au moins un document sont affichés.',
                'table' => [
                    'headers' => ['Date', 'Docs', 'Total', 'Encours'],
                    'widths' => [120, 55, 130, 130],
                    'align' => ['L', 'R', 'R', 'R'],
                    'rows' => $jourRows,
                    'empty' => 'Aucun document sur ce mois.',
                ],
            ],
            [
                'title' => 'Détail des documents du mois',
                'note' => 'Montants exprimés en dirhams.',
                'table' => [
                    'headers' => ['Carnet', 'N°', 'Type', 'Bénéficiaire', 'Montant', 'Échéance', 'Statut'],
                    'widths' => [100, 45, 40, 110, 65, 60, 75],
                    'align' => ['L', 'L', 'L', 'L', 'R', 'L', 'L'],
                    'rows' => $detailRows,
                    'footer' => ['TOTAL', report_number($stats['n']), '', '', report_amount($totalMois), '', ''],
                    'empty' => 'Aucun document sur ce mois.',
                ],
            ],
        ],
        'totals' => [
            'label' => 'TOTAL DU MOIS',
            'value' => money($totalMois),
            'meta' => report_number($stats['n']) . ' document(s) · ' . MontantEnLettres::convertir($totalMois),
        ],
        'notes' => [
            'Les totaux incluent les documents réglés, non réglés et rejetés. L’encours ne retient que les documents ni réglés ni rejetés.',
            'Seuil mensuel automatique = 1,25 × moyenne de l’encours des mois enregistrés. Seuil hebdomadaire = seuil mensuel ÷ 4,33.',
        ],
    ];
}

function report_build(string $type, array $f): ?array
{
    return match ($type) {
        'documents' => report_build_documents($f),
        'beneficiaires' => report_build_beneficiaires($f),
        'personnes' => report_build_personnes($f),
        'carnets' => report_build_carnets($f),
        'statistiques' => report_build_analysis($f, 'statistiques'),
        'calendrier' => report_build_calendar($f),
        'synthese' => report_build_analysis($f, 'synthese'),
        default => null,
    };
}

function report_preview(string $type, array $f): array
{
    $report = report_build($type, $f);
    if ($report === null) {
        return [
            'title' => 'Rapport PDF',
            'subtitle' => '',
            'filters' => '',
            'kpis' => [],
            'sections' => 0,
            'notes' => [],
        ];
    }

    return [
        'title' => $report['title'],
        'subtitle' => $report['subtitle'],
        'filters' => $report['filters'],
        'kpis' => $report['kpis'],
        'sections' => count($report['sections']),
        'notes' => $report['notes'],
    ];
}

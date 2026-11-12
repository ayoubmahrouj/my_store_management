<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$input = $_GET;
$format = strtolower((string) ($input['format'] ?? 'pdf'));
if (!in_array($format, ['pdf', 'csv', 'xlsx'], true)) {
    $format = 'pdf';
}

/* ------------------------------------------------------------------ */
/* Filtres (identiques à la liste)                                     */
/* ------------------------------------------------------------------ */
$conditions = [];
$params = [];

$q = trim((string) ($input['q'] ?? ''));
$type = (string) ($input['type'] ?? '');
$statut = (string) ($input['statut'] ?? '');
$beneficiaireId = (int) ($input['beneficiaire'] ?? 0);
$moisEcheance = (string) ($input['mois_echeance'] ?? '');
$moisEmission = (string) ($input['mois_emission'] ?? '');
$carnetId = (int) ($input['carnet'] ?? 0);
$jours = (int) ($input['jours'] ?? 0);
$montantMin = isset($input['montant_min']) && $input['montant_min'] !== '' ? (float) $input['montant_min'] : null;
$montantMax = isset($input['montant_max']) && $input['montant_max'] !== '' ? (float) $input['montant_max'] : null;

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
foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $op) {
    if (!empty($input[$key]) && isValidDate((string) $input[$key])) {
        $conditions[] = 'd.date_emission ' . $op . ' ?';
        $params[] = (string) $input[$key];
    }
}
foreach (['echeance_from' => '>=', 'echeance_to' => '<='] as $key => $op) {
    if (!empty($input[$key]) && isValidDate((string) $input[$key])) {
        $conditions[] = 'd.date_echeance ' . $op . ' ?';
        $params[] = (string) $input[$key];
    }
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
$sql = "SELECT d.*, b.nom AS beneficiaire_nom, b.type AS beneficiaire_type,
               p.nom AS personne_nom, p.role AS personne_role,
               c.libelle AS carnet_libelle, c.code AS carnet_code
        FROM document_paiement d
        JOIN beneficiaire b ON b.id = d.beneficiaire_id
        LEFT JOIN personne p ON p.id = d.personne_id
        LEFT JOIN carnet c ON c.id = d.carnet_id"
    . $where . ' ORDER BY c.libelle ASC, d.numero ASC, d.date_echeance ASC';

$rows = fetch_all($sql, $params);
$total = 0.0;
foreach ($rows as $row) {
    $total += (float) $row['montant'];
}
$count = count($rows);
$stamp = date('Ymd_His');
$societe = societe();
$summary = filters_summary($input);

audit('EXPORT', 'DOCUMENT', null, strtoupper($format) . ' · ' . $count . ' document(s)');

/* ------------------------------------------------------------------ */
/* CSV                                                                */
/* ------------------------------------------------------------------ */
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="documents_' . $stamp . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Carnet', 'Code', 'Numéro', 'Type', 'Bénéficiaire', 'Type bénéficiaire', 'Personne responsable',
        'Rôle', 'Montant (DH)', 'Émission', 'Échéance', 'Statut', 'Réglé', 'Date règlement', 'Banque', 'Remarque',
    ], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['carnet_libelle'],
            $row['carnet_code'],
            $row['numero'],
            $row['type'] === 'EFFET' ? 'Effet' : 'Chèque',
            $row['beneficiaire_nom'],
            $row['beneficiaire_type'] === 'SOCIETE' ? 'Société' : 'Personne',
            $row['personne_nom'],
            role_label($row['personne_role'] ?? ''),
            number_format((float) $row['montant'], 2, ',', ''),
            date_fr($row['date_emission']),
            date_fr($row['date_echeance']),
            status_label($row),
            bit_true($row['regle']) ? 'Oui' : 'Non',
            $row['date_reglement'] ? date_fr($row['date_reglement']) : '',
            $row['banque'],
            $row['remarque'],
        ], ';');
    }
    fputcsv($out, ['TOTAL', '', '', '', '', '', '', '', number_format($total, 2, ',', ''), '', '', '', '', '', '', ''], ';');
    fclose($out);
    exit;
}

/* ------------------------------------------------------------------ */
/* XLSX                                                               */
/* ------------------------------------------------------------------ */
if ($format === 'xlsx') {
    require_once __DIR__ . '/../lib/XlsxWriter.php';
    $xl = new XlsxWriter('Documents', 5);
    $xl->setColWidths([24, 12, 14, 26, 22, 16, 12, 12, 12, 10]);
    $xl->addRow([['v' => 'Gestion Chèques & Effets', 's' => 4]]);
    $xl->addRow([['v' => 'Export du ' . datetime_fr(date('Y-m-d H:i:s')) . ' — ' . $count . ' document(s)', 's' => 0]]);
    $xl->addRow([['v' => 'Filtres : ' . $summary, 's' => 0]]);
    $xl->addRow([]);
    $xl->addRow([
        ['v' => 'Carnet', 's' => 1],
        ['v' => 'N°', 's' => 1],
        ['v' => 'Type', 's' => 1],
        ['v' => 'Bénéficiaire', 's' => 1],
        ['v' => 'Personne responsable', 's' => 1],
        ['v' => 'Montant (DH)', 's' => 1],
        ['v' => 'Émission', 's' => 1],
        ['v' => 'Échéance', 's' => 1],
        ['v' => 'Statut', 's' => 1],
        ['v' => 'Réglé', 's' => 1],
    ]);
    foreach ($rows as $row) {
        $xl->addRow([
            ['v' => (string) $row['carnet_libelle'], 's' => 2],
            ['v' => (string) $row['numero'], 's' => 2],
            ['v' => type_label($row['type']), 's' => 2],
            ['v' => (string) $row['beneficiaire_nom'], 's' => 2],
            ['v' => (string) ($row['personne_nom'] ?? ''), 's' => 2],
            ['v' => (float) $row['montant'], 't' => 'n', 's' => 3],
            ['v' => date_fr($row['date_emission']), 's' => 2],
            ['v' => date_fr($row['date_echeance']), 's' => 2],
            ['v' => status_label($row), 's' => 2],
            ['v' => bit_true($row['regle']) ? 'Oui' : 'Non', 's' => 2],
        ]);
    }
    $xl->addRow([
        ['v' => 'TOTAL', 's' => 5],
        ['v' => $count . ' document(s)', 's' => 5],
        ['v' => '', 's' => 5],
        ['v' => '', 's' => 5],
        ['v' => '', 's' => 5],
        ['v' => $total, 't' => 'n', 's' => 6],
        ['v' => '', 's' => 5],
        ['v' => '', 's' => 5],
        ['v' => '', 's' => 5],
        ['v' => '', 's' => 5],
    ]);
    $xl->send('documents_' . $stamp . '.xlsx');
}

/* ------------------------------------------------------------------ */
/* PDF                                                                */
/* ------------------------------------------------------------------ */
require_once __DIR__ . '/../lib/SimplePdf.php';

$navy = [13, 38, 76];
$muted = [110, 122, 140];
$gold = [197, 160, 89];
$border = [226, 232, 240];
$white = [255, 255, 255];

$pdf = new SimplePdf('L');
$W = $pdf->width;

$fit = static function (SimplePdf $p, string $text, float $max, float $size, bool $bold = false): string {
    if ($p->textWidth($text, $size, $bold) <= $max) {
        return $text;
    }
    while ($text !== '' && $p->textWidth($text . '…', $size, $bold) > $max) {
        $text = mb_substr($text, 0, -1);
    }
    return $text . '…';
};

$drawChrome = static function (SimplePdf $p, array $soc, bool $first, string $summary, int $count, float $total, int $pageNo) use ($navy, $muted, $gold, $white): float {
    $p->setFillColor(...$navy);
    $p->rect(0, 0, $p->width, 64, 'F');
    $p->setFillColor(...$gold);
    $p->rect(28, 15, 34, 34, 'F');
    $p->text(45 - $p->textWidth('GC', 12, true) / 2, 37, 'GC', 12, true, $navy);
    $p->text(72, 31, (string) ($soc['nom'] ?? APP_NAME), 12, true, $white);
    $contact = trim(implode(' · ', array_filter([$soc['adresse'] ?? null, $soc['telephone'] ?? null, $soc['email'] ?? null])));
    $p->text(72, 46, $contact, 7.5, false, [205, 216, 232]);
    $p->textRight($p->width - 28, 31, 'LISTE DES CHÈQUES & EFFETS', 11, true, $white);
    $p->textRight($p->width - 28, 46, 'Édité le ' . datetime_fr(date('Y-m-d H:i:s')), 8, false, [205, 216, 232]);

    $y = 84.0;
    if ($first) {
        $p->text(28, $y, $summary, 8.5, false, $muted);
        $p->textRight($p->width - 28, $y, $count . ' document(s) · ' . money($total), 8.5, true, $navy);
        $y += 14;
    }

    $p->setFillColor(238, 243, 249);
    $p->rect(28, $y, $p->width - 56, 20, 'F');
    $headers = [
        [28, 'Carnet'],
        [132, 'N°'],
        [184, 'Type'],
        [222, 'Bénéficiaire'],
        [356, 'Personne responsable'],
        [566, 'Émission'],
        [630, 'Échéance'],
        [694, 'Statut'],
    ];
    foreach ($headers as [$x, $label]) {
        $p->text($x, $y + 13, $label, 7.5, true, $navy);
    }
    $p->textRight(556, $y + 13, 'Montant', 7.5, true, $navy);
    $p->text(28, $p->height - 16, 'Page ' . $pageNo, 7.5, false, $muted);
    $p->textRight($p->width - 28, $p->height - 16, APP_NAME, 7.5, false, $muted);

    return $y + 20;
};

$y = $drawChrome($pdf, $societe, true, $summary, $count, $total, 1);
$pageNo = 1;
$rowHeight = 18.0;
$bottom = $pdf->height - 32;
$i = 0;

foreach ($rows as $row) {
    if ($y + $rowHeight > $bottom) {
        $pdf->addPage();
        $pageNo++;
        $y = $drawChrome($pdf, $societe, false, $summary, $count, $total, $pageNo);
    }
    if ($i % 2 === 1) {
        $pdf->setFillColor(248, 250, 252);
        $pdf->rect(28, $y, $pdf->width - 56, $rowHeight, 'F');
    }
    $pdf->text(28, $y + 12, $fit($pdf, (string) ($row['carnet_libelle'] ?: '—'), 100, 7.5), 7.5, false, $navy);
    $pdf->text(132, $y + 12, $fit($pdf, (string) $row['numero'], 48, 7.5), 7.5, false, $navy);
    $pdf->text(184, $y + 12, $row['type'] === 'EFFET' ? 'Effet' : 'Chèque', 7.5, false, $muted);
    $pdf->text(222, $y + 12, $fit($pdf, (string) $row['beneficiaire_nom'], 130, 7.5), 7.5, true, $navy);
    $pdf->text(356, $y + 12, $fit($pdf, (string) ($row['personne_nom'] ?: '—'), 108, 7.5), 7.5, false, $muted);
    $pdf->textRight(556, $y + 12, number_format((float) $row['montant'], 2, ',', ' '), 7.5, true, $navy);
    $pdf->text(566, $y + 12, date_fr($row['date_emission']), 7.5, false, $muted);
    $pdf->text(630, $y + 12, date_fr($row['date_echeance']), 7.5, false, $muted);
    $status = document_status($row);
    $statusColor = in_array($status, ['rejete', 'retard'], true) ? [185, 28, 28] : ($status === 'regle' ? [15, 123, 71] : ($status === 'bientot' ? [146, 94, 12] : [71, 85, 105]));
    $pdf->text(694, $y + 12, $fit($pdf, status_label($row), 100, 7.5, true), 7.5, true, $statusColor);
    $y += $rowHeight;
    $i++;
}

/* Totaux */
if ($y + 56 > $bottom) {
    $pdf->addPage();
    $pageNo++;
    $y = $drawChrome($pdf, $societe, false, $summary, $count, $total, $pageNo);
}
$y += 8;
$pdf->setFillColor(253, 249, 240);
$pdf->setDrawColor(...$border);
$pdf->rect(28, $y, $pdf->width - 56, 40, 'B');
$pdf->text(44, $y + 17, 'TOTAL GÉNÉRAL', 10, true, $navy);
$pdf->text(44, $y + 31, $count . ' document(s) · ' . MontantEnLettres::convertir($total), 8, false, [122, 84, 18]);
$pdf->textRight($pdf->width - 44, $y + 17, money($total), 14, true, $navy);

$pdf->send('documents_' . $stamp . '.pdf');

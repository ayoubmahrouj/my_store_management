<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();
require_once __DIR__ . '/../lib/SimplePdf.php';

$id = int_param('id');
$doc = $id > 0 ? fetch_one(
    'SELECT d.*, b.nom AS beneficiaire_nom, b.type AS beneficiaire_type, b.ice, b.telephone AS beneficiaire_tel,
            b.email AS beneficiaire_email, b.adresse AS beneficiaire_adresse,
            p.nom AS personne_nom, p.role AS personne_role, p.telephone AS personne_tel, p.email AS personne_email,
            c.libelle AS carnet_libelle, c.code AS carnet_code
     FROM document_paiement d
     JOIN beneficiaire b ON b.id = d.beneficiaire_id
     LEFT JOIN personne p ON p.id = d.personne_id
     LEFT JOIN carnet c ON c.id = d.carnet_id
     WHERE d.id = ?',
    [$id]
) : null;

if (!$doc) {
    http_response_code(404);
    exit('Document introuvable.');
}

$soc = societe();
$pdf = new SimplePdf('P');
$pdf->addPage();

$navy = [13, 38, 76];
$muted = [110, 122, 140];
$gold = [197, 160, 89];
$border = [226, 232, 240];
$soft = [248, 250, 252];

/* En-tête */
$pdf->setFillColor(...$navy);
$pdf->rect(0, 0, 595.28, 96, 'F');
$pdf->setFillColor(...$gold);
$pdf->rect(40, 26, 44, 44, 'F');
$pdf->text(62 - $pdf->textWidth('GC', 16, true) / 2, 55, 'GC', 16, true, $navy);
$pdf->text(98, 44, (string) $soc['nom'], 15, true, [255, 255, 255]);
$pdf->text(98, 61, trim((string) ($soc['adresse'] ?? '')), 8, false, [205, 216, 232]);
$contact = trim(implode(' · ', array_filter([
    $soc['telephone'] ?? null,
    $soc['email'] ?? null,
])));
$pdf->text(98, 73, $contact, 8, false, [205, 216, 232]);
$pdf->textRight(555, 46, 'FICHE DOCUMENT', 12, true, [255, 255, 255]);
$pdf->textRight(555, 63, type_label($doc['type']) . ' n° ' . $doc['numero'], 9, false, [205, 216, 232]);

/* Titre + statut */
$pdf->text(40, 140, type_label($doc['type']) . ' n° ' . $doc['numero'], 22, true, $navy);
$pdf->text(40, 160, 'Carnet : ' . ($doc['carnet_libelle'] ?: '—') . ($doc['carnet_code'] !== null ? ' · Code ' . $doc['carnet_code'] : ''), 10, false, $muted);

$status = document_status($doc);
$statusMap = [
    'regle'     => ['Réglé', [232, 246, 239], [15, 123, 71]],
    'rejete'    => ['Rejeté', [253, 235, 235], [185, 28, 28]],
    'retard'    => ['En retard', [253, 235, 235], [185, 28, 28]],
    'bientot'   => ['À échéance', [255, 246, 224], [146, 94, 12]],
    'non_regle' => ['Non réglé', [241, 245, 249], [71, 85, 105]],
];
[$statusLabel, $statusBg, $statusColor] = $statusMap[$status];
$badgeW = $pdf->textWidth($statusLabel, 9, true) + 22;
$pdf->setFillColor(...$statusBg);
$pdf->rect(555 - $badgeW, 124, $badgeW, 22, 'F');
$pdf->textCenter(555 - $badgeW / 2, 139, $statusLabel, 9, true, $statusColor);

/* Carte bénéficiaire */
$pdf->setFillColor(...$soft);
$pdf->setDrawColor(...$border);
$pdf->rect(40, 184, 515, 130, 'B');
$pdf->text(56, 205, 'BÉNÉFICIAIRE / TIERS', 8, true, $gold);
$pdf->text(56, 223, (string) $doc['beneficiaire_nom'], 12, true, $navy);
$pdf->text(56, 241, 'Type : ' . beneficiaire_type_label($doc['beneficiaire_type']) . ($doc['ice'] ? ' · ICE ' . $doc['ice'] : ''), 9, false, $muted);
$pdf->text(56, 259, 'Téléphone : ' . ($doc['beneficiaire_tel'] ?: '—'), 9, false, $muted);
$pdf->text(56, 277, 'Email : ' . ($doc['beneficiaire_email'] ?: '—'), 9, false, $muted);
$pdf->text(56, 295, 'Adresse : ' . ($doc['beneficiaire_adresse'] ?: '—'), 9, false, $muted);

$pdf->text(330, 205, 'PERSONNE RESPONSABLE', 8, true, $gold);
$pdf->text(330, 223, (string) ($doc['personne_nom'] ?: 'Non renseignée'), 12, true, $navy);
$pdf->text(330, 241, role_label($doc['personne_role']), 9, false, $muted);
$pdf->text(330, 259, 'Téléphone : ' . ($doc['personne_tel'] ?: '—'), 9, false, $muted);
$pdf->text(330, 277, 'Email : ' . ($doc['personne_email'] ?: '—'), 9, false, $muted);
$pdf->text(330, 295, 'Banque : ' . ($doc['banque'] ?: '—'), 9, false, $muted);

/* Montant */
$pdf->setFillColor(253, 249, 240);
$pdf->setDrawColor(238, 224, 194);
$pdf->rect(40, 330, 515, 100, 'B');
$pdf->text(56, 352, 'MONTANT', 8, true, $gold);
$pdf->text(56, 382, money($doc['montant']), 25, true, $navy);
$lettres = MontantEnLettres::convertir((float) $doc['montant']);
$lines = $pdf->wrap('Montant en lettres : ' . $lettres, 480, 9.5, false);
$y = 402;
foreach (array_slice($lines, 0, 2) as $line) {
    $pdf->text(56, $y, $line, 9.5, false, [122, 84, 18]);
    $y += 13;
}

/* Dates */
$boxes = [
    ['ÉMISSION', date_fr($doc['date_emission'])],
    ['ÉCHÉANCE', date_fr($doc['date_echeance'])],
    ['RÈGLEMENT', !empty($doc['date_reglement']) ? date_fr($doc['date_reglement']) : 'Non réglé'],
];
$bx = 40;
foreach ($boxes as $box) {
    $pdf->setFillColor(255, 255, 255);
    $pdf->setDrawColor(...$border);
    $pdf->rect($bx, 448, 165, 62, 'B');
    $pdf->text($bx + 14, 468, $box[0], 8, true, $muted);
    $pdf->text($bx + 14, 490, $box[1], 12, true, $navy);
    $bx += 175;
}

/* Statut & remarque */
$pdf->setFillColor(255, 255, 255);
$pdf->setDrawColor(...$border);
$pdf->rect(40, 526, 515, 74, 'B');
$pdf->text(56, 546, 'STATUT', 8, true, $muted);
$pdf->text(56, 566, $statusLabel . ($status === 'retard' ? ' · échéance dépassée' : ''), 11, true, $statusColor);
$pdf->text(56, 585, 'Créé le ' . datetime_fr($doc['created_at']) . ' · Dernière modification : ' . datetime_fr($doc['updated_at']), 8, false, $muted);

$pdf->setFillColor(255, 255, 255);
$pdf->setDrawColor(...$border);
$pdf->rect(40, 612, 515, 76, 'B');
$pdf->text(56, 632, 'REMARQUE', 8, true, $muted);
$remarque = trim((string) ($doc['remarque'] ?? ''));
if ($remarque !== '') {
    $ry = 650;
    foreach (array_slice($pdf->wrap($remarque, 480, 9.5, false), 0, 3) as $line) {
        $pdf->text(56, $ry, $line, 9.5, false, [71, 85, 105]);
        $ry += 13;
    }
} else {
    $pdf->text(56, 652, '—', 9.5, false, $muted);
}

/* Pied de page */
$pdf->setDrawColor(...$border);
$pdf->line(40, 792, 555, 792);
$pdf->text(40, 808, 'Document généré le ' . datetime_fr(date('Y-m-d H:i:s')) . ' · ' . APP_NAME, 8, false, $muted);
$pdf->textRight(555, 808, 'Page 1 / 1', 8, false, $muted);

$pdf->send('fiche_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $doc['numero']) . '.pdf');

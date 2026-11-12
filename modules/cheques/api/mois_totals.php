<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_api_login();

$mois = (string) param('mois');
$excludeId = int_param('exclude_id');

if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
    json_response(['ok' => false, 'error' => t('Mois invalide.')], 422);
}

[$start, $end] = month_bounds($mois);
if ($start === null || $end === null) {
    json_response(['ok' => false, 'error' => t('Mois invalide.')], 422);
}

$total = (float) scalar(
    'SELECT COALESCE(SUM(montant), 0) FROM document_paiement
     WHERE regle = 0 AND rejete = 0 AND date_echeance BETWEEN ? AND ? AND id <> ?',
    [$start, $end, $excludeId],
    0
);
$nombre = (int) scalar(
    'SELECT COUNT(*) FROM document_paiement
     WHERE regle = 0 AND rejete = 0 AND date_echeance BETWEEN ? AND ? AND id <> ?',
    [$start, $end, $excludeId],
    0
);

$sixMonthsAgo = date('Y-m-01', strtotime('-6 months'));
$rows = fetch_all(
    "SELECT DATE_FORMAT(date_echeance, '%Y-%m') AS mois, COALESCE(SUM(montant), 0) AS montant
     FROM document_paiement
     WHERE regle = 0 AND rejete = 0 AND date_echeance >= ? AND date_echeance <= ?
     GROUP BY mois",
    [$sixMonthsAgo, $end]
);
$sum = 0.0;
foreach ($rows as $row) {
    $sum += (float) $row['montant'];
}
$moyenne = count($rows) > 0 ? $sum / count($rows) : 0.0;

json_response([
    'ok'      => true,
    'mois'    => $mois,
    'total'   => round($total, 2),
    'nombre'  => $nombre,
    'moyenne' => round($moyenne, 2),
    'seuil'   => round($moyenne * 1.25, 2),
]);

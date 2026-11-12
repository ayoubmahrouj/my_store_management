<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

require_once __DIR__ . '/../lib/SimplePdf.php';
require_once __DIR__ . '/../lib/Reports.php';
require_once __DIR__ . '/../lib/ReportPdf.php';

$type = report_type_from_input($_GET);
$filters = report_normalize_filters($_GET);
$report = report_build($type, $filters);

if ($report === null) {
    http_response_code(404);
    exit('Rapport introuvable.');
}

audit(
    'EXPORT',
    'RAPPORT',
    null,
    'PDF ' . $type . ' · ' . $report['filters']
);

$pdf = new ReportPdf(
    (string) ($report['orientation'] ?? 'P'),
    societe(),
    (string) $report['title'],
    (string) $report['subtitle'],
    (string) $report['filters']
);
$pdf->start();
$pdf->kpis($report['kpis']);

foreach ($report['sections'] as $section) {
    $pdf->section((string) ($section['title'] ?? ''), (string) ($section['note'] ?? ''));
    if (isset($section['table']) && is_array($section['table'])) {
        $table = $section['table'];
        $pdf->table(
            $table['headers'] ?? [],
            $table['rows'] ?? [],
            $table['widths'] ?? [],
            $table['align'] ?? [],
            $table['footer'] ?? null,
            (string) ($table['empty'] ?? 'Aucune donnée')
        );
    } elseif (isset($section['text'])) {
        $pdf->note((string) $section['text']);
    }
}

$totals = $report['totals'] ?? null;
if (is_array($totals)) {
    $pdf->totals(
        (string) ($totals['label'] ?? 'TOTAL'),
        (string) ($totals['value'] ?? ''),
        (string) ($totals['meta'] ?? '')
    );
}

$pdf->notes($report['notes'] ?? []);

$filename = 'rapport_' . $type . '_' . date('Ymd_His') . '.pdf';
$pdf->send($filename);

<?php
declare(strict_types=1);

namespace App\Infrastructure\Export;

/**
 * Rendu CSV compatible avec les habitudes françaises (séparateur point-virgule,
 * décimales à la virgule). Il partage exactement les mêmes colonnes et lignes
 * que les rendus Excel et PDF.
 */
final class CsvExporter
{
    public function render(ExportDocument $document): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        $headers = array_map(static fn (array $column): string => (string) ($column['label'] ?? ''), $document->columns);
        fputcsv($handle, $headers, ';', '"', '');

        foreach ($document->rows as $row) {
            $cells = [];
            foreach ($document->columns as $index => $column) {
                $cells[] = $this->format($row[$index] ?? null, (string) ($column['type'] ?? 'text'));
            }
            fputcsv($handle, $cells, ';', '"', '');
        }

        if ($document->totals !== []) {
            $cells = [];
            foreach ($document->columns as $index => $column) {
                $value = $document->totals[$index] ?? null;
                if ($index === 0 && ($value === null || $value === '')) {
                    $value = 'TOTAL';
                }
                $cells[] = $this->format($value, (string) ($column['type'] ?? 'text'));
            }
            fputcsv($handle, $cells, ';', '"', '');
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function format(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => number_format((float) $value, 2, ',', ' '),
            'qty' => $this->qty((float) $value),
            'number' => number_format((float) $value, 2, ',', ' '),
            default => (string) $value,
        };
    }

    private function qty(float $value): string
    {
        $formatted = number_format($value, 3, ',', ' ');
        $formatted = rtrim(rtrim($formatted, '0'), ',');
        return $formatted === '' ? '0' : $formatted;
    }
}

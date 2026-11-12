<?php
declare(strict_types=1);

namespace App\Infrastructure\Export;

use InvalidArgumentException;

/**
 * Générateur PDF autonome (PDF 1.4, police Helvetica).
 *
 * Aucun binaire externe ni bibliothèque Composer n'est requis. Le rendu gère
 * les tableaux multi-pages, les accents français, les filtres, les synthèses et
 * la pagination. Il est volontairement sobre et lisible à l'impression.
 */
final class PdfExporter
{
    private const PORTRAIT_WIDTH = 595.28;
    private const PORTRAIT_HEIGHT = 841.89;
    private const MARGIN = 28.0;
    private const BOTTOM_SAFE = 42.0;

    private ExportDocument $document;
    private float $pageWidth;
    private float $pageHeight;
    private float $y = 0.0;

    /** @var array<int, string> */
    private array $pageContents = [];

    private int $currentPage = -1;

    /** @var array{0: float, 1: float, 2: float} */
    private array $navy = [0.07, 0.15, 0.25];

    /** @var array{0: float, 1: float, 2: float} */
    private array $accent = [0.10, 0.35, 0.54];

    /** @var array{0: float, 1: float, 2: float} */
    private array $muted = [0.42, 0.48, 0.56];

    /** @var array{0: float, 1: float, 2: float} */
    private array $border = [0.86, 0.89, 0.93];

    /** @var array{0: float, 1: float, 2: float} */
    private array $soft = [0.96, 0.98, 1.0];

    /** @var array{0: float, 1: float, 2: float} */
    private array $totalFill = [0.91, 0.95, 0.98];

    public function render(ExportDocument $document): string
    {
        if ($document->columns === []) {
            throw new InvalidArgumentException('Aucune colonne à exporter.');
        }

        $this->document = $document;
        $this->pageWidth = $document->landscape ? self::PORTRAIT_HEIGHT : self::PORTRAIT_WIDTH;
        $this->pageHeight = $document->landscape ? self::PORTRAIT_WIDTH : self::PORTRAIT_HEIGHT;

        $this->newPage();
        $this->renderCover();
        $this->renderTable();
        $this->addFooters();

        return $this->assemble();
    }

    private function newPage(): void
    {
        $this->pageContents[] = '';
        $this->currentPage = count($this->pageContents) - 1;

        $top = $this->pageHeight - self::MARGIN;
        $this->out($this->textCommand(self::MARGIN, $top, 7.4, true, $this->accent, 'SUIVI STOCK'));
        $this->out($this->textCommand(
            $this->pageWidth - self::MARGIN - $this->textWidth('Document confidentiel', 6.5, false),
            $top,
            6.5,
            false,
            $this->muted,
            'Document confidentiel'
        ));
        $this->out($this->lineCommand(self::MARGIN, $top - 7, $this->pageWidth - self::MARGIN, $top - 7, $this->border));

        if ($this->currentPage > 0) {
            $this->out($this->textCommand(self::MARGIN, $top - 22, 8.5, true, $this->navy, $this->fitText($this->document->title, $this->pageWidth - 2 * self::MARGIN, 8.5, true)));
            $this->y = $top - 36;
            return;
        }

        $this->y = $top - 30;
    }

    private function renderCover(): void
    {
        $available = $this->pageWidth - 2 * self::MARGIN;

        $titleSize = 19.0;
        while ($titleSize > 12.0 && $this->textWidth($this->document->title, $titleSize, true) > $available) {
            $titleSize -= 0.5;
        }
        $this->out($this->textCommand(self::MARGIN, $this->y, $titleSize, true, $this->navy, $this->document->title));
        $this->y -= 18;

        if ($this->document->subtitle !== '') {
            foreach ($this->wrapText($this->document->subtitle, $available, 8.5, false) as $line) {
                $this->out($this->textCommand(self::MARGIN, $this->y, 8.5, false, $this->muted, $line));
                $this->y -= 10.5;
            }
        }

        $meta = $this->document->metaLine();
        $meta[] = 'Généré le ' . $this->document->generatedAtLabel();
        $metaText = implode('  ·  ', $meta);
        foreach ($this->wrapText($metaText, $available, 7.2, false) as $line) {
            $this->out($this->textCommand(self::MARGIN, $this->y, 7.2, false, $this->muted, $line));
            $this->y -= 9.2;
        }

        if ($this->document->summary !== []) {
            $this->y -= 8;
            $this->renderSummary();
        }

        $this->y -= 7;
    }

    private function renderSummary(): void
    {
        $items = array_values($this->document->summary);
        $count = count($items);
        if ($count === 0) {
            return;
        }

        $available = $this->pageWidth - 2 * self::MARGIN;
        $gap = 6.0;
        $boxWidth = ($available - ($count - 1) * $gap) / $count;
        $boxHeight = 40.0;
        $top = $this->y;

        foreach ($items as $index => $item) {
            $x = self::MARGIN + $index * ($boxWidth + $gap);
            $this->out($this->rectCommand($x, $top - $boxHeight, $boxWidth, $boxHeight, $this->soft));
            $this->out($this->rectCommand($x, $top - $boxHeight, 2.1, $boxHeight, $this->accent));
            $this->out($this->textCommand($x + 8, $top - 13, 6.1, true, $this->muted, mb_strtoupper($this->fitText($item['label'], $boxWidth - 16, 6.1, true))));
            $valueSize = 10.5;
            $value = $this->fitText($item['value'], $boxWidth - 16, $valueSize, true);
            $this->out($this->textCommand($x + 8, $top - 29, $valueSize, true, $this->navy, $value));
        }

        $this->y = $top - $boxHeight - 10;
    }

    private function renderTable(): void
    {
        $columns = $this->document->columns;
        $widths = $this->columnWidths($columns);
        $tableWidth = array_sum($widths);

        $this->drawTableHeader($columns, $widths);

        if ($this->document->rows === []) {
            $height = 28.0;
            if ($this->y - $height < self::BOTTOM_SAFE) {
                $this->newPage();
                $this->drawTableHeader($columns, $widths);
            }
            $this->out($this->rectCommand(self::MARGIN, $this->y - $height, $tableWidth, $height, $this->soft));
            $this->out($this->textCommand(self::MARGIN + 8, $this->y - 17, 7.8, false, $this->muted, 'Aucune donnée ne correspond aux filtres sélectionnés.'));
            $this->y -= $height;
        } else {
            $alternate = false;
            foreach ($this->document->rows as $row) {
                $prepared = $this->prepareRow($row, $columns, $widths);
                $rowHeight = max(15.0, $prepared['height'] + 7);

                if ($this->y - $rowHeight < self::BOTTOM_SAFE) {
                    $this->newPage();
                    $this->drawTableHeader($columns, $widths);
                }

                $this->drawDataRow($prepared['cells'], $widths, $rowHeight, $alternate);
                $alternate = !$alternate;
            }
        }

        if ($this->document->totals !== []) {
            $height = 20.0;
            if ($this->y - $height < self::BOTTOM_SAFE) {
                $this->newPage();
                $this->drawTableHeader($columns, $widths);
            }
            $this->drawTotalsRow($columns, $widths, $height);
        }
    }

    /** @param array<int, array{label: string, width?: float, align?: string, type?: string}> $columns */
    private function drawTableHeader(array $columns, array $widths): void
    {
        $linesByColumn = [];
        $maxLines = 1;
        foreach ($columns as $index => $column) {
            $lines = $this->wrapText((string) ($column['label'] ?? ''), max(12, $widths[$index] - 8), 7.0, true);
            $linesByColumn[$index] = $lines;
            $maxLines = max($maxLines, count($lines));
        }

        $height = max(20.0, $maxLines * 8.2 + 8);
        if ($this->y - $height < self::BOTTOM_SAFE) {
            $this->newPage();
        }

        $this->out($this->rectCommand(self::MARGIN, $this->y - $height, array_sum($widths), $height, $this->navy));
        $x = self::MARGIN;
        foreach ($columns as $index => $column) {
            $align = (string) ($column['align'] ?? 'left');
            $lines = $linesByColumn[$index];
            $lineHeight = 8.2;
            $blockHeight = count($lines) * $lineHeight;
            $startBaseline = $this->y - (($height - $blockHeight) / 2) - 5.5;
            foreach ($lines as $lineIndex => $line) {
                $textWidth = $this->textWidth($line, 7.0, true);
                $textX = $this->alignedX($x, $widths[$index], 4, $textWidth, $align);
                $this->out($this->textCommand($textX, $startBaseline - $lineIndex * $lineHeight, 7.0, true, [1, 1, 1], $line));
            }
            $x += $widths[$index];
        }

        $this->y -= $height;
    }

    /**
     * @param array<int, array{lines: array<int, string>, align: string}> $cells
     * @param array<int, float> $widths
     */
    private function drawDataRow(array $cells, array $widths, float $height, bool $alternate): void
    {
        $tableWidth = array_sum($widths);
        $fill = $alternate ? $this->soft : [1, 1, 1];
        $this->out($this->rectCommand(self::MARGIN, $this->y - $height, $tableWidth, $height, $fill));
        $this->out($this->lineCommand(self::MARGIN, $this->y - $height, self::MARGIN + $tableWidth, $this->y - $height, $this->border));

        $x = self::MARGIN;
        foreach ($cells as $index => $cell) {
            $lineHeight = 8.4;
            foreach ($cell['lines'] as $lineIndex => $line) {
                $textWidth = $this->textWidth($line, 7.1, false);
                $textX = $this->alignedX($x, $widths[$index], 4, $textWidth, $cell['align']);
                $this->out($this->textCommand($textX, $this->y - 10.5 - $lineIndex * $lineHeight, 7.1, false, $this->navy, $line));
            }
            $x += $widths[$index];
        }

        $this->y -= $height;
    }

    /** @param array<int, array{label: string, width?: float, align?: string, type?: string}> $columns
     *  @param array<int, float> $widths
     */
    private function drawTotalsRow(array $columns, array $widths, float $height): void
    {
        $tableWidth = array_sum($widths);
        $this->out($this->rectCommand(self::MARGIN, $this->y - $height, $tableWidth, $height, $this->totalFill));
        $this->out($this->lineCommand(self::MARGIN, $this->y, self::MARGIN + $tableWidth, $this->y, $this->accent));

        $x = self::MARGIN;
        foreach ($columns as $index => $column) {
            $value = $this->document->totals[$index] ?? null;
            if ($index === 0 && ($value === null || $value === '')) {
                $value = 'TOTAL';
            }
            $text = $this->formatCell($value, (string) ($column['type'] ?? 'text'));
            $align = (string) ($column['align'] ?? 'left');
            $textWidth = $this->textWidth($text, 7.2, true);
            $textX = $this->alignedX($x, $widths[$index], 4, $textWidth, $align);
            if ($text !== '') {
                $this->out($this->textCommand($textX, $this->y - 13, 7.2, true, $this->navy, $text));
            }
            $x += $widths[$index];
        }

        $this->y -= $height;
    }

    /** @param array<int, mixed> $row
     *  @param array<int, array{label: string, width?: float, align?: string, type?: string}> $columns
     *  @param array<int, float> $widths
     *  @return array{cells: array<int, array{lines: array<int, string>, align: string}>, height: float}
     */
    private function prepareRow(array $row, array $columns, array $widths): array
    {
        $cells = [];
        $maxLines = 1;
        foreach ($columns as $index => $column) {
            $text = $this->formatCell($row[$index] ?? null, (string) ($column['type'] ?? 'text'));
            $lines = $this->wrapText($text, max(12, $widths[$index] - 8), 7.1, false);
            $maxLines = max($maxLines, count($lines));
            $cells[$index] = [
                'lines' => $lines,
                'align' => (string) ($column['align'] ?? 'left'),
            ];
        }

        return ['cells' => $cells, 'height' => $maxLines * 8.4];
    }

    /** @param array<int, array{label: string, width?: float, align?: string, type?: string}> $columns
     *  @return array<int, float>
     */
    private function columnWidths(array $columns): array
    {
        $available = $this->pageWidth - 2 * self::MARGIN;
        $weights = [];
        foreach ($columns as $column) {
            $weights[] = max(0.35, (float) ($column['width'] ?? 1.0));
        }
        $sum = array_sum($weights);
        $widths = [];
        foreach ($weights as $weight) {
            $widths[] = ($weight / $sum) * $available;
        }

        return $widths;
    }

    private function formatCell(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'money' => number_format((float) $value, 2, ',', ' '),
            'qty' => $this->formatQty((float) $value),
            'number' => number_format((float) $value, 2, ',', ' '),
            default => (string) $value,
        };
    }

    private function formatQty(float $value): string
    {
        $formatted = number_format($value, 3, ',', ' ');
        $formatted = rtrim(rtrim($formatted, '0'), ',');
        return $formatted === '' ? '0' : $formatted;
    }

    private function alignedX(float $x, float $width, float $padding, float $textWidth, string $align): float
    {
        return match ($align) {
            'right' => $x + $width - $padding - $textWidth,
            'center' => $x + ($width - $textWidth) / 2,
            default => $x + $padding,
        };
    }

    /** @return array<int, string> */
    private function wrapText(string $text, float $maxWidth, float $size, bool $bold): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return [''];
        }

        $lines = [];
        $current = '';
        foreach (preg_split('/\s+/u', $text) ?: [$text] as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($this->textWidth($candidate, $size, $bold) <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            if ($this->textWidth($word, $size, $bold) <= $maxWidth) {
                $current = $word;
                continue;
            }

            $chunk = '';
            foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
                $candidate = $chunk . $char;
                if ($chunk !== '' && $this->textWidth($candidate, $size, $bold) > $maxWidth) {
                    $lines[] = $chunk;
                    $chunk = $char;
                } else {
                    $chunk = $candidate;
                }
            }
            $current = $chunk;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    private function fitText(string $text, float $maxWidth, float $size, bool $bold): string
    {
        if ($this->textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        $length = mb_strlen($text, 'UTF-8');
        while ($length > 3) {
            $length--;
            $candidate = mb_substr($text, 0, $length, 'UTF-8') . '...';
            if ($this->textWidth($candidate, $size, $bold) <= $maxWidth) {
                return $candidate;
            }
        }

        return mb_substr($text, 0, 3, 'UTF-8');
    }

    private function textWidth(string $text, float $size, bool $bold): float
    {
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        return $length * $size * ($bold ? 0.54 : 0.51);
    }

    private function addFooters(): void
    {
        $total = count($this->pageContents);
        if ($total === 0) {
            return;
        }

        $this->currentPage = 0;
        $footerY = 21.0;
        foreach ($this->pageContents as $index => $unused) {
            $this->currentPage = $index;
            $left = 'Suivi Stock · ' . $this->document->title . ' · ' . $this->document->generatedAtLabel();
            $right = 'Page ' . ($index + 1) . ' / ' . $total;

            $this->out($this->lineCommand(self::MARGIN, 31, $this->pageWidth - self::MARGIN, 31, $this->border));
            $this->out($this->textCommand(self::MARGIN, $footerY, 6.3, false, $this->muted, $this->fitText($left, $this->pageWidth - 2 * self::MARGIN - 70, 6.3, false)));
            $this->out($this->textCommand(
                $this->pageWidth - self::MARGIN - $this->textWidth($right, 6.3, false),
                $footerY,
                6.3,
                false,
                $this->muted,
                $right
            ));
        }
    }

    private function out(string $command): void
    {
        if ($this->currentPage < 0) {
            return;
        }
        $this->pageContents[$this->currentPage] .= $command . "\n";
    }

    /** @param array{0: float, 1: float, 2: float} $color */
    private function textCommand(float $x, float $y, float $size, bool $bold, array $color, string $text): string
    {
        $font = $bold ? 'F2' : 'F1';
        return 'BT /' . $font . ' ' . $this->n($size) . ' Tf '
            . $this->n($color[0]) . ' ' . $this->n($color[1]) . ' ' . $this->n($color[2]) . ' rg '
            . $this->n($x) . ' ' . $this->n($y) . ' Td (' . $this->pdfString($text) . ') Tj ET';
    }

    /** @param array{0: float, 1: float, 2: float} $color */
    private function rectCommand(float $x, float $y, float $width, float $height, array $color): string
    {
        return $this->n($color[0]) . ' ' . $this->n($color[1]) . ' ' . $this->n($color[2]) . ' rg '
            . $this->n($x) . ' ' . $this->n($y) . ' ' . $this->n($width) . ' ' . $this->n($height) . ' re f';
    }

    /** @param array{0: float, 1: float, 2: float} $color */
    private function lineCommand(float $x1, float $y1, float $x2, float $y2, array $color): string
    {
        return $this->n($color[0]) . ' ' . $this->n($color[1]) . ' ' . $this->n($color[2]) . ' RG '
            . $this->n($x1) . ' ' . $this->n($y1) . ' m ' . $this->n($x2) . ' ' . $this->n($y2) . ' l S';
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function pdfString(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{202F}", "\r", "\n", "\t"], [' ', ' ', ' ', ' ', ' '], $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        } elseif (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function assemble(): string
    {
        $pageCount = count($this->pageContents);
        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Title (' . $this->pdfString($this->document->title) . ') /Author (Suivi Stock) /Producer (Suivi Stock) /CreationDate (' . date('YmdHis') . ') >>';

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObject = 6 + $i;
            $contentObject = 6 + $pageCount + $i;
            $content = $this->pageContents[$i];
            $kids[] = $pageObject . ' 0 R';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->n($this->pageWidth) . ' ' . $this->n($this->pageHeight) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
            $objects[$contentObject] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $lastObject = max(array_keys($objects));
        for ($id = 1; $id <= $lastObject; $id++) {
            if (!isset($objects[$id])) {
                continue;
            }
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $objects[$id] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . ($lastObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $lastObject; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= 'trailer' . "\n<< /Size " . ($lastObject + 1) . ' /Root 1 0 R /Info 5 0 R >>' . "\n";
        $pdf .= 'startxref' . "\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }
}

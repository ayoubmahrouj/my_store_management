<?php
declare(strict_types=1);

require_once __DIR__ . '/SimplePdf.php';

/**
 * Mise en page commune des rapports PDF.
 *
 * Le rendu reste volontairement en français : SimplePdf utilise les polices
 * Helvetica/WinAnsi et ne peut pas afficher correctement l'arabe.
 */
final class ReportPdf
{
    private const NAVY = [13, 38, 76];
    private const NAVY_700 = [20, 58, 107];
    private const GOLD = [197, 160, 89];
    private const GOLD_SOFT = [253, 246, 230];
    private const MUTED = [110, 122, 140];
    private const BORDER = [226, 232, 240];
    private const SOFT = [248, 250, 252];
    private const BLUE_SOFT = [234, 241, 251];
    private const GREEN = [22, 128, 91];
    private const RED = [190, 55, 55];

    private SimplePdf $pdf;
    private string $title;
    private string $subtitle;
    private string $filters;
    private array $soc;

    private float $left = 28.0;
    private float $right;
    private float $contentWidth;
    private float $bottom;
    private float $y = 0.0;
    private int $pageNo = 0;

    public function __construct(
        string $orientation,
        array $soc,
        string $title,
        string $subtitle,
        string $filters
    ) {
        $this->pdf = new SimplePdf($orientation);
        $this->soc = $soc;
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->filters = $filters;
        $this->right = $this->pdf->width - $this->left;
        $this->contentWidth = $this->right - $this->left;
        $this->bottom = $this->pdf->height - 36;
    }

    public function start(): void
    {
        $this->newPage(true);
    }

    public function kpis(array $items): void
    {
        if (!$items) {
            return;
        }

        $count = count($items);
        $columns = $count >= 4 ? 4 : max(1, $count);
        $gap = 9.0;
        $height = 58.0;
        $width = ($this->contentWidth - $gap * ($columns - 1)) / $columns;

        foreach (array_chunk($items, $columns) as $rowIndex => $row) {
            $this->ensure($height + 10);
            $x = $this->left;
            foreach ($row as $item) {
                $tone = $this->toneColor((string) ($item['tone'] ?? 'navy'));
                $this->pdf->setFillColor(...self::SOFT);
                $this->pdf->rect($x, $this->y, $width, $height, 'F');
                $this->pdf->setDrawColor(...self::BORDER);
                $this->pdf->setLineWidth(0.6);
                $this->pdf->rect($x, $this->y, $width, $height, 'S');
                $this->pdf->setFillColor(...$tone);
                $this->pdf->rect($x, $this->y, $width, 3.0, 'F');

                $this->pdf->text(
                    $x + 9,
                    $this->y + 17,
                    $this->fit((string) ($item['label'] ?? ''), $width - 18, 7.4, true),
                    7.4,
                    true,
                    self::MUTED
                );
                $this->pdf->text(
                    $x + 9,
                    $this->y + 37,
                    $this->fit((string) ($item['value'] ?? ''), $width - 18, 13, true),
                    13,
                    true,
                    $tone
                );
                $meta = (string) ($item['meta'] ?? '');
                if ($meta !== '') {
                    $this->pdf->text(
                        $x + 9,
                        $this->y + 50,
                        $this->fit($meta, $width - 18, 7.0),
                        7.0,
                        false,
                        self::MUTED
                    );
                }

                $x += $width + $gap;
            }
            $this->y += $height + ($rowIndex === count(array_chunk($items, $columns)) - 1 ? 0 : 10);
        }
        $this->y += 8;
    }

    public function section(string $title, string $note = ''): void
    {
        $noteLines = $note !== '' ? $this->pdf->wrap($note, $this->contentWidth, 8) : [];
        $this->ensure(21 + count($noteLines) * 10 + 8);

        $this->pdf->setFillColor(...self::GOLD);
        $this->pdf->rect($this->left, $this->y, 6, 6, 'F');
        $this->pdf->text(
            $this->left + 12,
            $this->y + 7,
            $this->fit($title, $this->contentWidth - 12, 11, true),
            11,
            true,
            self::NAVY
        );
        $this->pdf->setDrawColor(...self::BORDER);
        $this->pdf->setLineWidth(0.7);
        $this->pdf->line($this->left, $this->y + 14, $this->right, $this->y + 14);
        $this->y += 21;

        foreach ($noteLines as $line) {
            $this->pdf->text($this->left, $this->y + 8, $line, 8, false, self::MUTED);
            $this->y += 10;
        }
        $this->y += 6;
    }

    public function table(array $headers, array $rows, array $widths, array $align = [], ?array $footer = null, string $empty = 'Aucune donnée'): void
    {
        if (!$rows) {
            $this->emptyState($empty);
            return;
        }

        $widths = $this->normalizeWidths($widths, count($headers));
        $headerHeight = 20.0;
        $rowHeight = 18.0;

        $this->ensure($headerHeight + $rowHeight);
        $this->drawTableHeader($headers, $widths, $align);

        foreach ($rows as $index => $row) {
            if ($this->y + $rowHeight > $this->bottom) {
                $this->newPage(false);
                $this->drawTableHeader($headers, $widths, $align);
            }
            $this->pdf->setFillColor(...($index % 2 === 0 ? [255, 255, 255] : self::SOFT));
            $this->pdf->rect($this->left, $this->y, $this->contentWidth, $rowHeight, 'F');
            $this->pdf->setDrawColor(...self::BORDER);
            $this->pdf->setLineWidth(0.35);
            $this->pdf->line($this->left, $this->y + $rowHeight, $this->right, $this->y + $rowHeight);
            $this->drawCells($row, $widths, $align, $this->y, $rowHeight, false);
            $this->y += $rowHeight;
        }

        if ($footer !== null) {
            if ($this->y + $rowHeight > $this->bottom) {
                $this->newPage(false);
                $this->drawTableHeader($headers, $widths, $align);
            }
            $this->pdf->setFillColor(...self::GOLD_SOFT);
            $this->pdf->rect($this->left, $this->y, $this->contentWidth, $rowHeight, 'F');
            $this->pdf->setDrawColor(...self::GOLD);
            $this->pdf->setLineWidth(0.5);
            $this->pdf->line($this->left, $this->y, $this->right, $this->y);
            $this->drawCells($footer, $widths, $align, $this->y, $rowHeight, true);
            $this->y += $rowHeight;
        }

        $this->y += 12;
    }

    public function totals(string $label, string $value, string $meta = ''): void
    {
        $height = 58.0;
        $this->ensure($height + 12);
        $this->pdf->setFillColor(...self::GOLD_SOFT);
        $this->pdf->rect($this->left, $this->y, $this->contentWidth, $height, 'F');
        $this->pdf->setDrawColor(...self::GOLD);
        $this->pdf->setLineWidth(0.7);
        $this->pdf->rect($this->left, $this->y, $this->contentWidth, $height, 'S');
        $this->pdf->setFillColor(...self::GOLD);
        $this->pdf->rect($this->left, $this->y, 5, $height, 'F');

        $this->pdf->text($this->left + 16, $this->y + 18, $label, 9, true, self::NAVY);
        if ($meta !== '') {
            $lines = $this->pdf->wrap($meta, $this->contentWidth - 250, 7.5);
            $lineY = $this->y + 31;
            foreach (array_slice($lines, 0, 2) as $line) {
                $this->pdf->text($this->left + 16, $lineY, $line, 7.5, false, self::MUTED);
                $lineY += 10;
            }
        }
        $this->pdf->textRight(
            $this->right - 14,
            $this->y + 31,
            $this->fit($value, 225, 15, true),
            15,
            true,
            self::GOLD
        );
        $this->y += $height + 12;
    }

    public function notes(array $notes): void
    {
        foreach ($notes as $note) {
            $this->note((string) $note);
        }
    }

    public function note(string $text): void
    {
        $lines = $this->pdf->wrap($text, $this->contentWidth - 26, 8);
        $height = count($lines) * 10 + 12;
        $this->ensure($height + 6);
        $this->pdf->setFillColor(...self::SOFT);
        $this->pdf->rect($this->left, $this->y, $this->contentWidth, $height, 'F');
        $this->pdf->setDrawColor(...self::BORDER);
        $this->pdf->setLineWidth(0.4);
        $this->pdf->rect($this->left, $this->y, $this->contentWidth, $height, 'S');
        $this->pdf->text($this->left + 10, $this->y + 13, 'i', 8, true, self::GOLD);
        $lineY = $this->y + 13;
        foreach ($lines as $line) {
            $this->pdf->text($this->left + 21, $lineY, $line, 8, false, self::MUTED);
            $lineY += 10;
        }
        $this->y += $height + 6;
    }

    public function output(): string
    {
        return $this->pdf->output();
    }

    public function send(string $filename): never
    {
        $this->pdf->send($filename);
    }

    private function newPage(bool $first): void
    {
        $this->pdf->addPage();
        $this->pageNo++;
        $this->drawHeader();
        $this->y = 84.0;
        if ($first) {
            $this->drawTitleBlock();
        }
    }

    private function drawHeader(): void
    {
        $this->pdf->setFillColor(...self::NAVY);
        $this->pdf->rect(0, 0, $this->pdf->width, 64, 'F');
        $this->pdf->setFillColor(...self::GOLD);
        $this->pdf->rect(0, 64, $this->pdf->width, 2.5, 'F');

        $this->pdf->setFillColor(...self::GOLD);
        $this->pdf->rect($this->left, 18, 28, 28, 'F');
        $this->pdf->text($this->left + 8, 37, 'GC', 11, true, self::NAVY);

        $nom = (string) ($this->soc['nom'] ?? APP_NAME);
        $this->pdf->text($this->left + 38, 27, $this->fit($nom, $this->contentWidth - 240, 13, true), 13, true, [255, 255, 255]);
        $contact = array_filter([
            (string) ($this->soc['adresse'] ?? ''),
            (string) ($this->soc['telephone'] ?? ''),
            (string) ($this->soc['email'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '');
        $contactText = implode(' · ', $contact);
        if ($contactText !== '') {
            $this->pdf->text(
                $this->left + 38,
                42,
                $this->fit($contactText, $this->contentWidth - 240, 7.5),
                7.5,
                false,
                [217, 226, 239]
            );
        }

        $this->pdf->textRight($this->right, 24, 'RAPPORT PDF', 9, true, self::GOLD);
        $this->pdf->textRight($this->right, 40, 'Édité le ' . date('d/m/Y à H:i'), 7.5, false, [217, 226, 239]);

        $this->pdf->setDrawColor(50, 75, 112);
        $this->pdf->setLineWidth(0.5);
        $this->pdf->line($this->left, $this->pdf->height - 26, $this->right, $this->pdf->height - 26);
        $this->pdf->text($this->left, $this->pdf->height - 15, APP_NAME, 7, false, self::MUTED);
        $this->pdf->textRight($this->right, $this->pdf->height - 15, 'Page ' . $this->pageNo, 7, false, self::MUTED);
    }

    private function drawTitleBlock(): void
    {
        $this->pdf->text(
            $this->left,
            $this->y + 18,
            $this->fit($this->title, $this->contentWidth, 17, true),
            17,
            true,
            self::NAVY
        );
        $this->y += 25;

        if ($this->subtitle !== '') {
            $lines = $this->pdf->wrap($this->subtitle, $this->contentWidth, 8.5);
            foreach (array_slice($lines, 0, 2) as $line) {
                $this->pdf->text($this->left, $this->y + 8, $line, 8.5, false, self::MUTED);
                $this->y += 11;
            }
        }

        if ($this->filters !== '') {
            $this->y += 3;
            $lines = $this->pdf->wrap('Filtres : ' . $this->filters, $this->contentWidth, 7.6);
            foreach (array_slice($lines, 0, 4) as $line) {
                $this->pdf->text($this->left, $this->y + 8, $line, 7.6, false, self::MUTED);
                $this->y += 9.5;
            }
        }

        $this->y += 4;
        $this->pdf->setDrawColor(...self::GOLD);
        $this->pdf->setLineWidth(0.8);
        $this->pdf->line($this->left, $this->y, $this->right, $this->y);
        $this->y += 14;
    }

    private function drawTableHeader(array $headers, array $widths, array $align): void
    {
        $this->pdf->setFillColor(...self::BLUE_SOFT);
        $this->pdf->rect($this->left, $this->y, $this->contentWidth, 20, 'F');
        $this->pdf->setDrawColor(...self::BORDER);
        $this->pdf->setLineWidth(0.5);
        $this->pdf->line($this->left, $this->y + 20, $this->right, $this->y + 20);

        $x = $this->left;
        foreach ($headers as $index => $header) {
            $width = $widths[$index] ?? 0;
            $alignment = $align[$index] ?? 'L';
            $text = $this->fit((string) $header, $width - 12, 7.5, true);
            $this->drawTextAligned($x, $this->y + 13, $width, $text, 7.5, true, self::NAVY_700, $alignment);
            $x += $width;
        }
        $this->y += 20;
    }

    private function drawCells(array $row, array $widths, array $align, float $y, float $height, bool $bold): void
    {
        $x = $this->left;
        foreach ($widths as $index => $width) {
            $value = $row[$index] ?? '';
            $cell = is_array($value) ? $value : ['text' => (string) $value];
            $text = (string) ($cell['text'] ?? '');
            $cellBold = array_key_exists('bold', $cell) ? (bool) $cell['bold'] : $bold;
            $color = $cell['color'] ?? ($bold ? self::NAVY : [18, 35, 61]);
            $alignment = (string) ($cell['align'] ?? ($align[$index] ?? 'L'));
            $this->drawTextAligned(
                $x,
                $y + 12.5,
                $width,
                $this->fit($text, $width - 12, 7.4, $cellBold),
                7.4,
                $cellBold,
                $color,
                $alignment
            );
            $x += $width;
        }
    }

    private function drawTextAligned(float $x, float $y, float $width, string $text, float $size, bool $bold, array $color, string $align): void
    {
        if ($align === 'R') {
            $this->pdf->textRight($x + $width - 6, $y, $text, $size, $bold, $color);
        } elseif ($align === 'C') {
            $this->pdf->textCenter($x + $width / 2, $y, $text, $size, $bold, $color);
        } else {
            $this->pdf->text($x + 6, $y, $text, $size, $bold, $color);
        }
    }

    private function emptyState(string $message): void
    {
        $this->ensure(40);
        $this->pdf->setFillColor(...self::SOFT);
        $this->pdf->rect($this->left, $this->y, $this->contentWidth, 34, 'F');
        $this->pdf->setDrawColor(...self::BORDER);
        $this->pdf->setLineWidth(0.5);
        $this->pdf->rect($this->left, $this->y, $this->contentWidth, 34, 'S');
        $this->pdf->text($this->left + 10, $this->y + 20, $this->fit($message, $this->contentWidth - 20, 8.5), 8.5, false, self::MUTED);
        $this->y += 46;
    }

    private function ensure(float $height): void
    {
        if ($this->y + $height > $this->bottom) {
            $this->newPage(false);
        }
    }

    private function normalizeWidths(array $widths, int $columns): array
    {
        $widths = array_values($widths);
        if (count($widths) < $columns) {
            $widths = array_pad($widths, $columns, 1);
        }
        $widths = array_slice($widths, 0, $columns);
        $sum = array_sum(array_map(static fn ($value): float => max(0.0, (float) $value), $widths));
        if ($sum <= 0) {
            $widths = array_fill(0, $columns, $this->contentWidth / max(1, $columns));
        } else {
            $scale = $this->contentWidth / $sum;
            $widths = array_map(static fn ($value): float => max(0.0, (float) $value) * $scale, $widths);
        }
        return $widths;
    }

    private function fit(string $text, float $maxWidth, float $size, bool $bold = false): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '' || $this->pdf->textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }
        $ellipsis = '…';
        while ($text !== '' && $this->pdf->textWidth($text . $ellipsis, $size, $bold) > $maxWidth) {
            $text = mb_substr($text, 0, mb_strlen($text) - 1);
        }
        return $text . $ellipsis;
    }

    private function toneColor(string $tone): array
    {
        return match ($tone) {
            'green' => self::GREEN,
            'red' => self::RED,
            'gold' => [166, 124, 42],
            default => self::NAVY,
        };
    }
}

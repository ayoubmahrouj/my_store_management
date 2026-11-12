<?php
declare(strict_types=1);

/**
 * Générateur PDF minimaliste, sans dépendance externe.
 *
 * Gère le texte (Helvetica / Helvetica-Bold), les rectangles, les lignes
 * et la pagination. Les accents français sont convertis en Windows-1252.
 */
final class SimplePdf
{
    public float $width;
    public float $height;

    /** @var string[] */
    private array $pages = [];
    private string $buffer = '';

    private float $fillR = 0.0;
    private float $fillG = 0.0;
    private float $fillB = 0.0;
    private float $strokeR = 0.0;
    private float $strokeG = 0.0;
    private float $strokeB = 0.0;
    private float $lineWidth = 0.6;

    public function __construct(string $orientation = 'P')
    {
        if (strtoupper($orientation) === 'L') {
            $this->width = 841.89;
            $this->height = 595.28;
        } else {
            $this->width = 595.28;
            $this->height = 841.89;
        }
    }

    public function addPage(): void
    {
        if ($this->buffer !== '') {
            $this->pages[] = $this->buffer;
        }
        $this->buffer = '';
        $this->fillR = $this->fillG = $this->fillB = 0.0;
        $this->strokeR = $this->strokeG = $this->strokeB = 0.0;
        $this->lineWidth = 0.6;
    }

    public function setFillColor(float $r, float $g, float $b): void
    {
        $this->fillR = $r / 255;
        $this->fillG = $g / 255;
        $this->fillB = $b / 255;
    }

    public function setDrawColor(float $r, float $g, float $b): void
    {
        $this->strokeR = $r / 255;
        $this->strokeG = $g / 255;
        $this->strokeB = $b / 255;
    }

    public function setLineWidth(float $width): void
    {
        $this->lineWidth = $width;
    }

    public function rect(float $x, float $y, float $w, float $h, string $style = 'S', float $radius = 0): void
    {
        $yp = $this->height - $y - $h;
        $op = '';
        if ($style === 'F' || $style === 'B') {
            $op .= sprintf("%.3F %.3F %.3F rg\n", $this->fillR, $this->fillG, $this->fillB);
        }
        if ($style === 'S' || $style === 'B') {
            $op .= sprintf("%.3F %.3F %.3F RG\n%.2F w\n", $this->strokeR, $this->strokeG, $this->strokeB, $this->lineWidth);
        }
        $op .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $yp, $w, $h, $style === 'F' ? 'f' : ($style === 'B' ? 'B' : 'S'));
        $this->buffer .= $op;
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->buffer .= sprintf(
            "%.3F %.3F %.3F RG\n%.2F w\n%.2F %.2F m %.2F %.2F l S\n",
            $this->strokeR,
            $this->strokeG,
            $this->strokeB,
            $this->lineWidth,
            $x1,
            $this->height - $y1,
            $x2,
            $this->height - $y2
        );
    }

    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, array $color = [13, 38, 76]): void
    {
        $text = $this->encode($text);
        if ($text === '') {
            return;
        }
        $font = $bold ? 'F2' : 'F1';
        $this->buffer .= sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $font,
            $size,
            $color[0] / 255,
            $color[1] / 255,
            $color[2] / 255,
            $x,
            $this->height - $y,
            $this->escape($text)
        );
    }

    public function textRight(float $xRight, float $y, string $text, float $size = 10, bool $bold = false, array $color = [13, 38, 76]): void
    {
        $this->text($xRight - $this->textWidth($text, $size, $bold), $y, $text, $size, $bold, $color);
    }

    public function textCenter(float $xCenter, float $y, string $text, float $size = 10, bool $bold = false, array $color = [13, 38, 76]): void
    {
        $this->text($xCenter - $this->textWidth($text, $size, $bold) / 2, $y, $text, $size, $bold, $color);
    }

    public function textWidth(string $text, float $size, bool $bold = false): float
    {
        $encoded = $this->encode($text);
        $factor = $bold ? 0.56 : 0.51;
        return strlen($encoded) * $size * $factor;
    }

    /** Découpe un texte en lignes selon une largeur maximale. */
    public function wrap(string $text, float $maxWidth, float $size, bool $bold = false): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($this->textWidth($candidate, $size, $bold) <= $maxWidth || $current === '') {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines;
    }

    public function finish(): void
    {
        if ($this->buffer !== '') {
            $this->pages[] = $this->buffer;
            $this->buffer = '';
        }
        if (!$this->pages) {
            $this->pages[] = '';
        }
    }

    public function output(): string
    {
        $this->finish();

        $objects = [];
        $pageCount = count($this->pages);
        $firstPageObj = 5;
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($firstPageObj + $i * 2) . ' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Count ' . $pageCount . ' /Kids [' . implode(' ', $kids) . '] >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        for ($i = 0; $i < $pageCount; $i++) {
            $pageObj = $firstPageObj + $i * 2;
            $contentObj = $pageObj + 1;
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . sprintf('%.2F %.2F', $this->width, $this->height)
                . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObj . ' 0 R >>';
            $stream = $this->pages[$i];
            $objects[$contentObj] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $maxObj = $firstPageObj + $pageCount * 2 - 1;
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        for ($n = 1; $n <= $maxObj; $n++) {
            $offsets[$n] = strlen($pdf);
            $pdf .= $n . " 0 obj\n" . ($objects[$n] ?? '<< >>') . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= 'xref' . "\n" . '0 ' . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($n = 1; $n <= $maxObj; $n++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$n]) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }

    public function send(string $filename): never
    {
        $content = $this->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $content;
        exit;
    }

    private function encode(string $text): string
    {
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }
        return $converted;
    }

    private function escape(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        return $text;
    }
}

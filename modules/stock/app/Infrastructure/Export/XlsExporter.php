<?php
declare(strict_types=1);

namespace App\Infrastructure\Export;

use InvalidArgumentException;

/**
 * Générateur Excel 2003 SpreadsheetML (.xls), sans dépendance externe.
 *
 * Le fichier est un vrai classeur XML lisible par Excel, LibreOffice et Google
 * Sheets. Les colonnes numériques restent numériques : les totaux peuvent donc
 * être recalculés et filtrés directement dans le tableur.
 */
final class XlsExporter
{
    public function render(ExportDocument $document): string
    {
        $columns = $document->columns;
        if ($columns === []) {
            throw new InvalidArgumentException('Aucune colonne à exporter.');
        }

        $columnCount = count($columns);
        $sheetName = $this->sheetName($document->title);
        $titleRow = 1;
        $subtitleRow = $document->subtitle !== '' ? 2 : 0;
        $metaRow = $document->metaLine() !== [] ? 3 : 0;
        $summaryStart = 0;
        $headerRow = 1;

        $fixedRows = 1;
        if ($document->subtitle !== '') {
            $fixedRows++;
        }
        if ($document->metaLine() !== []) {
            $fixedRows++;
        }
        $fixedRows += 1; // ligne vide de respiration
        if ($document->summary !== []) {
            $fixedRows += 2; // libellés + valeurs
        }
        $headerRow = $fixedRows + 1;

        $rows = [];
        $rows[] = $this->row(
            [$this->mergedCell($document->title, 'Title', $columnCount)],
            30
        );

        if ($document->subtitle !== '') {
            $rows[] = $this->row([
                $this->mergedCell($document->subtitle, 'Subtitle', $columnCount),
            ], 18);
        }
        if ($document->metaLine() !== []) {
            $rows[] = $this->row([
                $this->mergedCell(implode('  ·  ', $document->metaLine()) . '  ·  Généré le ' . $document->generatedAtLabel(), 'Meta', $columnCount),
            ], 16);
        }

        $rows[] = '<Row ss:Height="8"/>';

        if ($document->summary !== []) {
            $summaryLabels = [];
            $summaryValues = [];
            foreach ($document->summary as $item) {
                $summaryLabels[] = $this->cell((string) $item['label'], 'SummaryLabel');
                $summaryValues[] = $this->cell((string) $item['value'], 'SummaryValue');
            }
            $rows[] = $this->row($summaryLabels, 17);
            $rows[] = $this->row($summaryValues, 24);
        }

        $headerCells = [];
        foreach ($columns as $column) {
            $headerCells[] = $this->cell((string) ($column['label'] ?? ''), 'Header');
        }
        $rows[] = $this->row($headerCells, 28);

        $alternate = false;
        foreach ($document->rows as $row) {
            $cells = [];
            foreach ($columns as $index => $column) {
                $cells[] = $this->dataCell($row[$index] ?? null, $column, $alternate);
            }
            $rows[] = $this->row($cells, 18);
            $alternate = !$alternate;
        }

        if ($document->totals !== []) {
            $cells = [];
            foreach ($columns as $index => $column) {
                $value = $document->totals[$index] ?? null;
                if ($index === 0 && ($value === null || $value === '')) {
                    $value = 'TOTAL';
                }
                $cells[] = $this->dataCell($value, $column, false, true);
            }
            $rows[] = $this->row($cells, 20, true);
        }

        $lastRow = count($rows);

        $columnXml = '';
        foreach ($columns as $column) {
            $width = (float) ($column['width'] ?? 1.0);
            $columnXml .= '<Column ss:AutoFitWidth="0" ss:Width="' . $this->number(max(46, $width * 62)) . '"/>';
        }

        $autofilter = '';
        if ($document->rows !== []) {
            $autofilter = '<AutoFilter x:Range="R' . $headerRow . 'C1:R' . $lastRow . 'C' . $columnCount
                . '" xmlns="urn:schemas-microsoft-com:office:excel"/>';
        }

        $freezeRow = $headerRow + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<?mso-application progid="Excel.Sheet"?>' . "\n"
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
            . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            . ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n"
            . '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">'
            . '<Title>' . $this->xml($document->title) . '</Title>'
            . '<Author>Suivi Stock</Author>'
            . '<Created>' . $this->xml(date('Y-m-d\TH:i:s\Z')) . '</Created>'
            . '</DocumentProperties>' . "\n"
            . $this->styles($document->currency) . "\n"
            . '<Worksheet ss:Name="' . $this->xml($sheetName) . '">' . "\n"
            . '<Table ss:ExpandedColumnCount="' . $columnCount . '" ss:ExpandedRowCount="' . $lastRow . '" x:FullColumns="1" x:FullRows="1">' . "\n"
            . $columnXml . "\n"
            . implode("\n", $rows) . "\n"
            . '</Table>' . "\n"
            . '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'
            . '<PageSetup><Layout x:Orientation="' . ($document->landscape ? 'Landscape' : 'Portrait') . '"/></PageSetup>'
            . '<FitToPage/><Print><ValidPrinterInfo/><PaperSizeIndex>9</PaperSizeIndex><HorizontalResolution>600</HorizontalResolution><VerticalResolution>600</VerticalResolution></Print>'
            . '<Selected/><FreezePanes/><FrozenNoSplit/><SplitHorizontal>' . $freezeRow . '</SplitHorizontal>'
            . '<TopRowBottomPane>' . $freezeRow . '</TopRowBottomPane><ActivePane>2</ActivePane>'
            . '</WorksheetOptions>' . "\n"
            . $autofilter . "\n"
            . '</Worksheet>' . "\n"
            . '</Workbook>';
    }

    private function styles(string $currency): string
    {
        $moneyFormat = '#,##0.00';
        $amountLabel = trim($currency) !== '' ? ' ' . $this->xml($currency) : '';

        return '<Styles>'
            . '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Bottom"/><Font ss:FontName="Calibri" ss:Size="10"/><Interior/><NumberFormat/><Protection/></Style>'
            . '<Style ss:ID="Title"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="17" ss:Bold="1" ss:Color="#0F2742"/></Style>'
            . '<Style ss:ID="Subtitle"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="10" ss:Color="#55657A"/></Style>'
            . '<Style ss:ID="Meta"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="8" ss:Color="#7A8798"/></Style>'
            . '<Style ss:ID="SummaryLabel"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="8" ss:Bold="1" ss:Color="#64748B"/><Interior ss:Color="#EEF4FA" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D8E3EE"/></Borders></Style>'
            . '<Style ss:ID="SummaryValue"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#113B5C"/><Interior ss:Color="#F6F9FC" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D8E3EE"/></Borders></Style>'
            . '<Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#16324F" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#16324F"/></Borders></Style>'
            . '<Style ss:ID="Text"><Alignment ss:Vertical="Top" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#26384D"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7ECF2"/></Borders></Style>'
            . '<Style ss:ID="TextAlt"><Alignment ss:Vertical="Top" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#26384D"/><Interior ss:Color="#F7FAFC" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7ECF2"/></Borders></Style>'
            . '<Style ss:ID="Number"><Alignment ss:Horizontal="Right" ss:Vertical="Top"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#26384D"/><NumberFormat ss:Format="#,##0.00"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7ECF2"/></Borders></Style>'
            . '<Style ss:ID="NumberAlt"><Alignment ss:Horizontal="Right" ss:Vertical="Top"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#26384D"/><Interior ss:Color="#F7FAFC" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0.00"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7ECF2"/></Borders></Style>'
            . '<Style ss:ID="Qty"><Alignment ss:Horizontal="Right" ss:Vertical="Top"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#26384D"/><NumberFormat ss:Format="#,##0.###"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7ECF2"/></Borders></Style>'
            . '<Style ss:ID="QtyAlt"><Alignment ss:Horizontal="Right" ss:Vertical="Top"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#26384D"/><Interior ss:Color="#F7FAFC" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0.###"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7ECF2"/></Borders></Style>'
            . '<Style ss:ID="Money"><Alignment ss:Horizontal="Right" ss:Vertical="Top"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#26384D"/><NumberFormat ss:Format="' . $moneyFormat . $amountLabel . '"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7ECF2"/></Borders></Style>'
            . '<Style ss:ID="MoneyAlt"><Alignment ss:Horizontal="Right" ss:Vertical="Top"/><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#26384D"/><Interior ss:Color="#F7FAFC" ss:Pattern="Solid"/><NumberFormat ss:Format="' . $moneyFormat . $amountLabel . '"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E7ECF2"/></Borders></Style>'
            . '<Style ss:ID="Total"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#113B5C"/><Interior ss:Color="#E8F1F8" ss:Pattern="Solid"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#AFC6D9"/></Borders></Style>'
            . '<Style ss:ID="TotalRight"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#113B5C"/><Interior ss:Color="#E8F1F8" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0.00"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#AFC6D9"/></Borders></Style>'
            . '<Style ss:ID="TotalQty"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="9" ss:Bold="1" ss:Color="#113B5C"/><Interior ss:Color="#E8F1F8" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0.###"/><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#AFC6D9"/></Borders></Style>'
            . '</Styles>';
    }

    /** @param array<int, string> $cells */
    private function row(array $cells, int $height = 18, bool $total = false): string
    {
        $height = max(12, $height);
        return '<Row ss:Height="' . $height . '">' . implode('', $cells) . '</Row>';
    }

    private function mergedCell(string $value, string $style, int $columns): string
    {
        $merge = $columns > 1 ? ' ss:MergeAcross="' . ($columns - 1) . '"' : '';
        return '<Cell' . $merge . ' ss:StyleID="' . $style . '"><Data ss:Type="String">' . $this->xml($value) . '</Data></Cell>';
    }

    private function cell(string $value, string $style): string
    {
        return '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . $this->xml($value) . '</Data></Cell>';
    }

    /** @param array{type?: string, align?: string} $column */
    private function dataCell(mixed $value, array $column, bool $alternate, bool $total = false): string
    {
        $type = (string) ($column['type'] ?? 'text');
        $isNumeric = in_array($type, ['money', 'qty', 'number'], true) && $value !== null && $value !== '' && is_numeric($value);

        if ($total) {
            $style = match ($type) {
                'money' => 'TotalRight',
                'qty' => 'TotalQty',
                'number' => 'TotalRight',
                default => 'Total',
            };
        } else {
            $style = match ($type) {
                'money' => $alternate ? 'MoneyAlt' : 'Money',
                'qty' => $alternate ? 'QtyAlt' : 'Qty',
                'number' => $alternate ? 'NumberAlt' : 'Number',
                default => $alternate ? 'TextAlt' : 'Text',
            };
        }

        if ($isNumeric) {
            return '<Cell ss:StyleID="' . $style . '"><Data ss:Type="Number">' . $this->number((float) $value) . '</Data></Cell>';
        }

        return '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . $this->xml((string) ($value ?? '')) . '</Data></Cell>';
    }

    private function sheetName(string $title): string
    {
        $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', ' ', $title) ?: 'Export';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?: 'Export');
        return mb_substr($name !== '' ? $name : 'Export', 0, 31);
    }

    private function number(float $value): string
    {
        if (abs($value - round($value)) < 0.0000001) {
            return (string) (int) round($value);
        }
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

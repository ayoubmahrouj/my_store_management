<?php
declare(strict_types=1);

namespace App\Infrastructure\Export;

/**
 * Modèle neutre d'un export tabulaire.
 *
 * Les contrôleurs construisent ce document une seule fois, puis le même contenu
 * est rendu soit en Excel (.xls), soit en PDF. Cela garantit des chiffres et des
 * filtres strictement identiques entre les deux formats.
 */
final class ExportDocument
{
    /**
     * @param array<int, string> $meta
     * @param array<int, array{label: string, value: string}> $summary
     * @param array<int, array{label: string, width?: float, align?: string, type?: string}> $columns
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, mixed> $totals
     */
    public function __construct(
        public readonly string $filename,
        public readonly string $title,
        public readonly string $subtitle = '',
        public readonly array $meta = [],
        public readonly array $summary = [],
        public readonly array $columns = [],
        public readonly array $rows = [],
        public readonly array $totals = [],
        public readonly bool $landscape = false,
        public readonly string $currency = 'MAD',
        public readonly string $generatedAt = '',
    ) {
    }

    public function generatedAtLabel(): string
    {
        return $this->generatedAt !== '' ? $this->generatedAt : date('d/m/Y H:i');
    }

    public function columnCount(): int
    {
        return count($this->columns);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /** @return array<int, string> */
    public function metaLine(): array
    {
        return array_values(array_filter(array_map('strval', $this->meta), static fn (string $v): bool => trim($v) !== ''));
    }
}

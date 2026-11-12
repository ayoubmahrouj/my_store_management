<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class InventorySession
{
    /** @param array<int, InventoryLine> $lines */
    public function __construct(
        private int $id,
        private string $numero,
        private int $locationId,
        private string $locationName,
        private string $status,
        private string $startedByName,
        private string $startedAt,
        private ?string $validatedByName,
        private ?string $validatedAt,
        private ?string $notes,
        private array $lines = [],
        private int $storedLineCount = 0,
        private int $storedCountedCount = 0,
        private float $storedGapValue = 0.0,
    ) {
    }

    public function id(): int { return $this->id; }
    public function numero(): string { return $this->numero; }
    public function locationId(): int { return $this->locationId; }
    public function locationName(): string { return $this->locationName; }
    public function status(): string { return $this->status; }
    public function startedByName(): string { return $this->startedByName; }
    public function startedAt(): string { return $this->startedAt; }
    public function validatedByName(): ?string { return $this->validatedByName; }
    public function validatedAt(): ?string { return $this->validatedAt; }
    public function notes(): ?string { return $this->notes; }

    /** @return array<int, InventoryLine> */
    public function lines(): array { return $this->lines; }

    public function isOpen(): bool { return $this->status === 'ouvert'; }
    public function isCounting(): bool { return $this->status === 'comptage'; }
    public function isValidated(): bool { return $this->status === 'valide'; }
    public function isCancelled(): bool { return $this->status === 'annule'; }
    public function isEditable(): bool { return $this->isOpen() || $this->isCounting(); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'ouvert' => 'Ouvert',
            'comptage' => 'Comptage',
            'valide' => 'Validé',
            'annule' => 'Annulé',
            default => ucfirst($this->status),
        };
    }

    public function lineCount(): int
    {
        return $this->storedLineCount > 0 ? $this->storedLineCount : count($this->lines);
    }

    public function countedLines(): int
    {
        return count(array_filter($this->lines, static fn (InventoryLine $line): bool => $line->hasCount()));
    }

    public function countedLineCount(): int
    {
        return $this->lines !== [] ? $this->countedLines() : $this->storedCountedCount;
    }

    public function gapValueTotal(): float
    {
        return $this->lines !== [] ? $this->gapValue() : $this->storedGapValue;
    }

    public function gapLines(): int
    {
        return count(array_filter($this->lines, static fn (InventoryLine $line): bool => $line->gap() !== null && abs($line->gap()) > 0.0005));
    }

    public function gapValue(): float
    {
        return round(array_sum(array_map(static fn (InventoryLine $line): float => $line->gapValue(), $this->lines)), 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'numero'          => $this->numero,
            'location'        => ['id' => $this->locationId, 'name' => $this->locationName],
            'status'          => $this->status,
            'started_by'      => $this->startedByName,
            'started_at'      => $this->startedAt,
            'validated_by'    => $this->validatedByName,
            'validated_at'    => $this->validatedAt,
            'notes'           => $this->notes,
            'lines'           => array_map(static fn (InventoryLine $line): array => $line->toArray(), $this->lines),
        ];
    }
}

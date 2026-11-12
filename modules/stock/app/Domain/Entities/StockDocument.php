<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class StockDocument
{
    /** @param array<int, StockDocumentLine> $lines */
    public function __construct(
        private int $id,
        private string $type,
        private ?string $motif,
        private string $numero,
        private string $docDate,
        private string $status,
        private int $locationId,
        private string $locationName,
        private ?int $destLocationId,
        private ?string $destLocationName,
        private ?int $supplierId,
        private ?string $supplierName,
        private ?string $reference,
        private ?string $notes,
        private float $totalCost,
        private float $totalSale,
        private string $createdByName,
        private string $createdAt,
        private ?string $validatedAt,
        private ?string $shippedAt,
        private ?string $receivedAt,
        private array $lines = [],
        private int $storedLineCount = 0,
    ) {
    }

    public function id(): int { return $this->id; }
    public function type(): string { return $this->type; }
    public function motif(): ?string { return $this->motif; }
    public function numero(): string { return $this->numero; }
    public function docDate(): string { return $this->docDate; }
    public function status(): string { return $this->status; }
    public function locationId(): int { return $this->locationId; }
    public function locationName(): string { return $this->locationName; }
    public function destLocationId(): ?int { return $this->destLocationId; }
    public function destLocationName(): ?string { return $this->destLocationName; }
    public function supplierId(): ?int { return $this->supplierId; }
    public function supplierName(): ?string { return $this->supplierName; }
    public function reference(): ?string { return $this->reference; }
    public function notes(): ?string { return $this->notes; }
    public function totalCost(): float { return $this->totalCost; }
    public function totalSale(): float { return $this->totalSale; }
    public function createdByName(): string { return $this->createdByName; }
    public function createdAt(): string { return $this->createdAt; }
    public function validatedAt(): ?string { return $this->validatedAt; }
    public function shippedAt(): ?string { return $this->shippedAt; }
    public function receivedAt(): ?string { return $this->receivedAt; }

    /** @return array<int, StockDocumentLine> */
    public function lines(): array { return $this->lines; }

    public function lineCount(): int { return $this->storedLineCount > 0 ? $this->storedLineCount : count($this->lines); }

    public function totalQty(): float
    {
        return array_sum(array_map(static fn (StockDocumentLine $line): float => $line->qty(), $this->lines));
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'entree' => 'Entrée',
            'sortie' => 'Sortie',
            'transfert' => 'Transfert',
            'inventaire' => 'Inventaire',
            'ouverture' => 'Ouverture',
            default => ucfirst($this->type),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'brouillon' => 'Brouillon',
            'valide' => 'Validé',
            'expedie' => 'Expédié',
            'recu' => 'Reçu',
            'annule' => 'Annulé',
            default => ucfirst($this->status),
        };
    }

    public function isDraft(): bool { return $this->status === 'brouillon'; }
    public function isShipped(): bool { return $this->status === 'expedie'; }
    public function isReceived(): bool { return $this->status === 'recu'; }
    public function isCancelled(): bool { return $this->status === 'annule'; }
    public function isTransfer(): bool { return $this->type === 'transfert'; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'type'            => $this->type,
            'motif'           => $this->motif,
            'numero'          => $this->numero,
            'doc_date'        => $this->docDate,
            'status'          => $this->status,
            'location'        => ['id' => $this->locationId, 'name' => $this->locationName],
            'destination'     => $this->destLocationId !== null
                ? ['id' => $this->destLocationId, 'name' => $this->destLocationName]
                : null,
            'supplier'        => $this->supplierId !== null
                ? ['id' => $this->supplierId, 'name' => $this->supplierName]
                : null,
            'reference'       => $this->reference,
            'notes'           => $this->notes,
            'total_cost'      => $this->totalCost,
            'total_sale'      => $this->totalSale,
            'created_by'      => $this->createdByName,
            'created_at'      => $this->createdAt,
            'validated_at'    => $this->validatedAt,
            'shipped_at'      => $this->shippedAt,
            'received_at'     => $this->receivedAt,
            'lines'           => array_map(static fn (StockDocumentLine $line): array => $line->toArray(), $this->lines),
        ];
    }
}

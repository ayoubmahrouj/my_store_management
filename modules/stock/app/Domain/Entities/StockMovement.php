<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class StockMovement
{
    public function __construct(
        private int $id,
        private string $occurredAt,
        private string $direction,
        private string $type,
        private ?string $motif,
        private ?string $numero,
        private string $product,
        private string $sku,
        private string $unit,
        private string $location,
        private float $qty,
        private float $unitCost,
        private float $unitPrice,
        private string $userName,
    ) {
    }

    public function id(): int { return $this->id; }
    public function occurredAt(): string { return $this->occurredAt; }
    public function direction(): string { return $this->direction; }
    public function type(): string { return $this->type; }
    public function motif(): ?string { return $this->motif; }
    public function numero(): ?string { return $this->numero; }
    public function product(): string { return $this->product; }
    public function sku(): string { return $this->sku; }
    public function unit(): string { return $this->unit; }
    public function location(): string { return $this->location; }
    public function qty(): float { return $this->qty; }
    public function unitCost(): float { return $this->unitCost; }
    public function unitPrice(): float { return $this->unitPrice; }
    public function userName(): string { return $this->userName; }

    public function isIn(): bool { return $this->direction === 'in'; }
    public function value(): float { return round($this->qty * $this->unitCost, 2); }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'occurred_at' => $this->occurredAt,
            'direction'   => $this->direction,
            'type'        => $this->type,
            'motif'       => $this->motif,
            'numero'      => $this->numero,
            'product'     => ['sku' => $this->sku, 'designation' => $this->product, 'unit' => $this->unit],
            'location'    => $this->location,
            'qty'         => $this->qty,
            'unit_cost'   => $this->unitCost,
            'unit_price'  => $this->unitPrice,
            'value'       => $this->value(),
            'user'        => $this->userName,
        ];
    }
}

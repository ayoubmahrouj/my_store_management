<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class InventoryLine
{
    public function __construct(
        private int $id,
        private int $productId,
        private string $sku,
        private string $designation,
        private string $unit,
        private float $theoretical,
        private ?float $counted,
        private ?float $gap,
        private float $unitCost,
    ) {
    }

    public function id(): int { return $this->id; }
    public function productId(): int { return $this->productId; }
    public function sku(): string { return $this->sku; }
    public function designation(): string { return $this->designation; }
    public function unit(): string { return $this->unit; }
    public function theoretical(): float { return $this->theoretical; }
    public function counted(): ?float { return $this->counted; }
    public function gap(): ?float { return $this->gap; }
    public function unitCost(): float { return $this->unitCost; }

    public function hasCount(): bool { return $this->counted !== null; }

    public function gapValue(): float
    {
        return $this->gap !== null ? round($this->gap * $this->unitCost, 2) : 0.0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'product_id'  => $this->productId,
            'sku'         => $this->sku,
            'designation' => $this->designation,
            'unit'        => $this->unit,
            'theoretical' => $this->theoretical,
            'counted'     => $this->counted,
            'gap'         => $this->gap,
            'unit_cost'   => $this->unitCost,
            'gap_value'   => $this->gapValue(),
        ];
    }
}

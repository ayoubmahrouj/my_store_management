<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class StockDocumentLine
{
    public function __construct(
        private int $id,
        private int $productId,
        private string $sku,
        private string $designation,
        private string $brand,
        private string $unit,
        private float $qty,
        private float $unitCost,
        private float $unitPrice,
    ) {
    }

    public function id(): int { return $this->id; }
    public function productId(): int { return $this->productId; }
    public function sku(): string { return $this->sku; }
    public function designation(): string { return $this->designation; }
    public function brand(): string { return $this->brand; }
    public function unit(): string { return $this->unit; }
    public function qty(): float { return $this->qty; }
    public function unitCost(): float { return $this->unitCost; }
    public function unitPrice(): float { return $this->unitPrice; }

    public function totalCost(): float
    {
        return round($this->qty * $this->unitCost, 2);
    }

    public function totalSale(): float
    {
        return round($this->qty * $this->unitPrice, 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'product_id'  => $this->productId,
            'sku'         => $this->sku,
            'designation' => $this->designation,
            'brand'       => $this->brand,
            'unit'        => $this->unit,
            'qty'         => $this->qty,
            'unit_cost'   => $this->unitCost,
            'unit_price'  => $this->unitPrice,
            'total_cost'  => $this->totalCost(),
            'total_sale'  => $this->totalSale(),
        ];
    }
}

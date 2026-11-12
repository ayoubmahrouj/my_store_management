<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class Product
{
    public function __construct(
        private int $id,
        private string $sku,
        private string $designation,
        private string $brand,
        private string $category,
        private string $unit,
        private float $purchaseCost,
        private float $salePrice,
        private float $minStock,
        private float $quantity,
        private float $stockValue,
        private string $positions,
        private bool $active,
    ) {
    }

    public function id(): int { return $this->id; }
    public function sku(): string { return $this->sku; }
    public function designation(): string { return $this->designation; }
    public function brand(): string { return $this->brand; }
    public function category(): string { return $this->category; }
    public function unit(): string { return $this->unit; }
    public function purchaseCost(): float { return $this->purchaseCost; }
    public function salePrice(): float { return $this->salePrice; }
    public function minStock(): float { return $this->minStock; }
    public function quantity(): float { return $this->quantity; }
    public function stockValue(): float { return $this->stockValue; }
    public function positions(): string { return $this->positions; }
    public function isActive(): bool { return $this->active; }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->minStock;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    public function marginRate(): float
    {
        if ($this->purchaseCost <= 0) {
            return 0.0;
        }
        return (($this->salePrice - $this->purchaseCost) / $this->purchaseCost) * 100;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'sku'          => $this->sku,
            'designation'  => $this->designation,
            'brand'        => $this->brand,
            'category'     => $this->category,
            'unit'         => $this->unit,
            'purchase_cost'=> $this->purchaseCost,
            'sale_price'   => $this->salePrice,
            'min_stock'    => $this->minStock,
            'quantity'     => $this->quantity,
            'stock_value'  => $this->stockValue,
            'positions'    => $this->positions,
            'margin_rate'  => round($this->marginRate(), 2),
            'low_stock'    => $this->isLowStock(),
            'out_of_stock' => $this->isOutOfStock(),
            'active'       => $this->active,
        ];
    }
}

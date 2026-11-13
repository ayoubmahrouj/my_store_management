<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

use ArrayAccess;
use Gos\Domain\Entities\Concerns\ReadOnlyArray;
use JsonSerializable;

/**
 * Produit — référentiel article **partagé** par les modules Crédits et Suivi Stock.
 *
 * Une seule classe décrit un produit, qu'il soit lu :
 *   - depuis la table `products` (Suivi Stock),
 *   - depuis la vue `credits_products` (module Crédits : `name`, `price`, `brand`…),
 *   - ou depuis une jointure enrichie (quantité en stock, emplacements…).
 *
 * {@see self::fromRow()} accepte donc aussi bien les colonnes canoniques que
 * les alias des vues historiques.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Produit implements ArrayAccess, JsonSerializable
{
    use ReadOnlyArray;

    public function __construct(
        private ?int $id = null,
        private string $designation = '',
        private ?string $sku = null,
        private ?string $barcode = null,
        private ?string $description = null,
        private ?int $brandId = null,
        private ?int $categoryId = null,
        private ?int $unitId = null,
        private float $purchaseCost = 0.0,
        private float $salePrice = 0.0,
        private ?float $avgSalePrice = null,
        private float $minStock = 0.0,
        private bool $active = true,
        private string $brand = '',
        private string $category = '',
        private string $unit = '',
        private float $quantity = 0.0,
        private float $stockValue = 0.0,
        private string $positions = '',
    ) {
    }

    /**
     * @param array<string, mixed> $row Ligne `products`, vue `credits_products` ou jointure enrichie.
     */
    public static function fromRow(array $row): self
    {
        $brand = $row['brand'] ?? null;
        $category = $row['category'] ?? null;
        $unit = $row['unit'] ?? null;

        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['designation'] ?? $row['name'] ?? ''),
            self::nullable($row['sku'] ?? null),
            self::nullable($row['barcode'] ?? null),
            self::nullable($row['description'] ?? null),
            isset($row['brand_id']) ? (int) $row['brand_id'] : null,
            isset($row['category_id']) ? (int) $row['category_id'] : null,
            isset($row['unit_id']) ? (int) $row['unit_id'] : null,
            (float) ($row['purchase_cost'] ?? 0),
            (float) ($row['sale_price'] ?? $row['price'] ?? 0),
            isset($row['avg_sale_price']) ? (float) $row['avg_sale_price'] : null,
            (float) ($row['min_stock'] ?? 0),
            (bool) ($row['active'] ?? $row['is_active'] ?? true),
            $brand !== null ? (string) $brand : '',
            $category !== null ? (string) $category : '',
            $unit !== null ? (string) $unit : '',
            (float) ($row['quantity'] ?? 0),
            (float) ($row['stock_value'] ?? 0),
            (string) ($row['positions'] ?? ''),
        );
    }

    public function id(): int
    {
        return (int) $this->id;
    }

    public function sku(): string
    {
        return (string) $this->sku;
    }

    public function barcode(): ?string
    {
        return $this->barcode;
    }

    public function designation(): string
    {
        return $this->designation;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function brand(): string
    {
        return $this->brand;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function brandId(): ?int
    {
        return $this->brandId;
    }

    public function categoryId(): ?int
    {
        return $this->categoryId;
    }

    public function unitId(): ?int
    {
        return $this->unitId;
    }

    public function purchaseCost(): float
    {
        return $this->purchaseCost;
    }

    public function salePrice(): float
    {
        return $this->salePrice;
    }

    public function avgSalePrice(): ?float
    {
        return $this->avgSalePrice;
    }

    public function minStock(): float
    {
        return $this->minStock;
    }

    public function quantity(): float
    {
        return $this->quantity;
    }

    public function stockValue(): float
    {
        return $this->stockValue;
    }

    public function positions(): string
    {
        return $this->positions;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->minStock;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    /** Taux de marge (%). */
    public function marginRate(): float
    {
        if ($this->purchaseCost <= 0) {
            return 0.0;
        }

        return (($this->salePrice - $this->purchaseCost) / $this->purchaseCost) * 100;
    }

    /** Marge unitaire en valeur. */
    public function margin(): float
    {
        return $this->salePrice - $this->purchaseCost;
    }

    /** Libellé complet « désignation — marque ». */
    public function fullName(): string
    {
        return $this->brand !== '' ? $this->designation . ' — ' . $this->brand : $this->designation;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'sku'            => $this->sku,
            'barcode'        => $this->barcode,
            'designation'    => $this->designation,
            'name'           => $this->designation,
            'description'    => $this->description,
            'brand'          => $this->brand,
            'brand_id'       => $this->brandId,
            'category'       => $this->category,
            'category_id'    => $this->categoryId,
            'unit'           => $this->unit,
            'unit_id'        => $this->unitId,
            'purchase_cost'  => $this->purchaseCost,
            'sale_price'     => $this->salePrice,
            'price'          => $this->salePrice,
            'avg_sale_price' => $this->avgSalePrice,
            'min_stock'      => $this->minStock,
            'quantity'       => $this->quantity,
            'stock_value'    => $this->stockValue,
            'positions'      => $this->positions,
            'margin'         => round($this->margin(), 2),
            'margin_rate'    => round($this->marginRate(), 2),
            'low_stock'      => $this->isLowStock(),
            'out_of_stock'   => $this->isOutOfStock(),
            'active'         => $this->active,
            'is_active'      => $this->active,
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

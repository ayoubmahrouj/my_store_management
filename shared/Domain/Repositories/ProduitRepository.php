<?php
declare(strict_types=1);

namespace Gos\Domain\Repositories;

use Gos\Domain\Entities\Produit;

/**
 * Dépôt des produits (table `products` / vue `credits_products`).
 *
 * Partagé par les modules Crédits et Suivi Stock : une seule définition du
 * produit, une seule façon de le lire et de l'écrire.
 */
final class ProduitRepository
{
    private const SELECT_ENRICHI = 'SELECT p.*, b.name AS brand, c.name AS category, u.code AS unit
                                    FROM products p
                                    LEFT JOIN brands b ON b.id = p.brand_id
                                    LEFT JOIN categories c ON c.id = p.category_id
                                    LEFT JOIN units u ON u.id = p.unit_id';

    /** @return array<int, Produit> */
    public function all(bool $activeOnly = true): array
    {
        $sql = self::SELECT_ENRICHI;
        if ($activeOnly) {
            $sql .= ' WHERE p.active = 1';
        }
        $sql .= ' ORDER BY p.designation ASC';

        return array_map(
            static fn (array $row): Produit => Produit::fromRow($row),
            core_all($sql)
        );
    }

    /** Lecture « module Crédits » (vue historique `credits_products`). */
    public function forCredits(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM credits_products';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        return array_map(
            static fn (array $row): Produit => Produit::fromRow($row),
            core_all($sql)
        );
    }

    public function find(int $id): ?Produit
    {
        $row = core_one(self::SELECT_ENRICHI . ' WHERE p.id = ? LIMIT 1', [$id]);

        return $row === null ? null : Produit::fromRow($row);
    }

    public function findByDesignationAndBrand(string $designation, string $brand): ?Produit
    {
        $row = core_one(
            'SELECT * FROM credits_products WHERE LOWER(brand) = LOWER(?) AND LOWER(name) = LOWER(?) LIMIT 1',
            [$brand, $designation]
        );

        return $row === null ? null : Produit::fromRow($row);
    }

    /** Crée ou met à jour un produit. Retourne son identifiant. */
    public function save(Produit $produit): int
    {
        $columns = [
            'sku'           => $produit->sku() !== '' ? $produit->sku() : null,
            'barcode'       => $produit->barcode(),
            'designation'   => $produit->designation(),
            'description'   => $produit->description(),
            'brand_id'      => $produit->brandId(),
            'category_id'   => $produit->categoryId(),
            'unit_id'       => $produit->unitId(),
            'purchase_cost' => round($produit->purchaseCost(), 2),
            'sale_price'    => round($produit->salePrice(), 2),
            'min_stock'     => round($produit->minStock(), 3),
            'active'        => $produit->isActive() ? 1 : 0,
        ];

        if ($produit->id() > 0) {
            $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = :{$c}", array_keys($columns)));
            $columns['id'] = $produit->id();
            core_q("UPDATE products SET {$set}, `updated_at` = NOW() WHERE `id` = :id", $columns);

            return $produit->id();
        }

        $columns['created_at'] = date('Y-m-d H:i:s');
        $cols = implode(', ', array_map(static fn (string $c): string => "`{$c}`", array_keys($columns)));
        $ph = implode(', ', array_map(static fn (string $c): string => ":{$c}", array_keys($columns)));
        core_q("INSERT INTO products ({$cols}) VALUES ({$ph})", $columns);

        return core_insert_id();
    }

    /** Réactive un produit existant et met à jour son prix / description. */
    public function reactivate(int $id, float $salePrice, ?string $description): void
    {
        core_q('UPDATE products SET active = 1, sale_price = ?, description = ? WHERE id = ?', [
            round($salePrice, 2),
            $description,
            $id,
        ]);
    }

    public function toggle(int $id): void
    {
        core_q('UPDATE products SET active = 1 - active WHERE id = ?', [$id]);
    }

    /**
     * Création rapide (endpoint « + » du module Crédits).
     * Retrouve le produit s'il existe (brand + désignation) ou le crée.
     */
    public function quickCreate(string $designation, ?string $brand, ?string $description, float $salePrice): Produit
    {
        $brand = $brand !== null ? trim($brand) : '';

        if ($brand !== '') {
            $brandId = (new MarqueRepository())->quickCreate($brand)->id();
        } else {
            $brandId = null;
        }

        $existing = $this->findByDesignationAndBrand($designation, $brand);
        if ($existing !== null) {
            if ($existing->isActive()) {
                return $existing;
            }

            $this->reactivate($existing->id(), $salePrice, $description);

            return new Produit($existing->id(), $designation, null, null, $description, $brandId, null, null, 0.0, $salePrice, null, 0.0, true, $brand);
        }

        $id = $this->save(new Produit(
            null,
            $designation,
            null,
            null,
            $description,
            $brandId,
            null,
            null,
            0.0,
            $salePrice,
            null,
            0.0,
            true,
            $brand,
        ));

        return new Produit($id, $designation, null, null, $description, $brandId, null, null, 0.0, $salePrice, null, 0.0, true, $brand);
    }
}

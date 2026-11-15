<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use Gos\Domain\Entities\Produit;
use PDO;
use PDOException;
use RuntimeException;

final class PdoProductRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Recherche avancée des produits (paginée).
     *
     * @param array{
     *     brand_id?: int, category_id?: int, unit_id?: int, location_id?: int,
     *     status?: string, min_qty?: float, max_qty?: float,
     *     min_value?: float, max_value?: float, sort?: string,
     *     limit?: int, offset?: int
     * } $filters
     * @return array<int, Produit>
     */
    public function search(?string $term = null, ?int $id = null, bool $activeOnly = true, array $filters = []): array
    {
        [$sql, $params] = $this->buildSearch($term, $id, $activeOnly, $filters);

        $sort = (string) ($filters['sort'] ?? 'name');
        $order = match ($sort) {
            'value' => 'stock_value DESC, p.designation ASC',
            'qty' => 'quantity DESC, p.designation ASC',
            'low' => 'quantity ASC, p.designation ASC',
            default => 'p.designation ASC',
        };
        $sql .= ' ORDER BY ' . $order . $this->limitClause($filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): Produit => $this->hydrate($row), $stmt->fetchAll());
    }

    /**
     * Totaux de la recherche courante, indépendants de la pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{total: int, quantity: float, value: float, low: int}
     */
    public function searchSummary(?string $term = null, ?int $id = null, bool $activeOnly = true, array $filters = []): array
    {
        [$sql, $params] = $this->buildSearch($term, $id, $activeOnly, $filters);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(quantity), 0) AS quantity,
                    COALESCE(SUM(stock_value), 0) AS value,
                    COALESCE(SUM(CASE WHEN quantity <= min_stock THEN 1 ELSE 0 END), 0) AS low
             FROM (' . $sql . ') AS t'
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total'    => (int) ($row['total'] ?? 0),
            'quantity' => (float) ($row['quantity'] ?? 0),
            'value'    => (float) ($row['value'] ?? 0),
            'low'      => (int) ($row['low'] ?? 0),
        ];
    }

    /**
     * Construit la requête de recherche filtrée, sans tri ni pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildSearch(?string $term, ?int $id, bool $activeOnly, array $filters): array
    {
        $locationId = (int) ($filters['location_id'] ?? 0);
        $locationJoin = '';
        if ($locationId > 0) {
            $locationJoin = ' AND sl.location_id = :location_join';
        }

        $sql = 'SELECT p.id, p.sku, p.designation, p.purchase_cost, p.sale_price, p.min_stock, p.active,
                       b.name AS brand, c.name AS category, u.code AS unit,
                       COALESCE(SUM(sl.qty), 0) AS quantity,
                       COALESCE(SUM(sl.qty * p.purchase_cost), 0) AS stock_value,
                       COALESCE(GROUP_CONCAT(CASE WHEN sl.qty <> 0 THEN CONCAT(l.code, " : ", sl.qty) END
                                             ORDER BY l.code SEPARATOR " · "), "—") AS positions
                FROM products p
                INNER JOIN brands b ON b.id = p.brand_id
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN units u ON u.id = p.unit_id
                LEFT JOIN stock_levels sl ON sl.product_id = p.id' . $locationJoin . '
                LEFT JOIN locations l ON l.id = sl.location_id
                WHERE 1 = 1';

        $params = [];
        if ($locationId > 0) {
            $params['location_join'] = $locationId;
        }
        if ($activeOnly) {
            $sql .= ' AND p.active = 1';
        }
        if ($term !== null && trim($term) !== '') {
            $sql .= ' AND (p.sku LIKE :term1 OR p.designation LIKE :term2 OR b.name LIKE :term3
                         OR c.name LIKE :term4 OR p.barcode LIKE :term5)';
            $like = '%' . trim($term) . '%';
            $params['term1'] = $like;
            $params['term2'] = $like;
            $params['term3'] = $like;
            $params['term4'] = $like;
            $params['term5'] = $like;
        }

        foreach ([
            'brand_id' => 'p.brand_id',
            'category_id' => 'p.category_id',
            'unit_id' => 'p.unit_id',
        ] as $key => $column) {
            $value = (int) ($filters[$key] ?? 0);
            if ($value > 0) {
                $sql .= ' AND ' . $column . ' = :' . $key;
                $params[$key] = $value;
            }
        }

        if ($id !== null) {
            $sql .= ' AND p.id = :id';
            $params['id'] = $id;
        }

        $sql .= ' GROUP BY p.id, p.sku, p.designation, p.purchase_cost, p.sale_price, p.min_stock, p.active,
                          b.name, c.name, u.code';

        $having = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status === 'out') {
            $having[] = 'quantity <= 0';
        } elseif ($status === 'low') {
            $having[] = 'quantity > 0 AND quantity <= p.min_stock';
        } elseif ($status === 'ok') {
            $having[] = 'quantity > p.min_stock';
        }

        if (isset($filters['min_qty']) && $filters['min_qty'] !== '' && $filters['min_qty'] !== null) {
            $having[] = 'quantity >= :min_qty';
            $params['min_qty'] = (float) $filters['min_qty'];
        }
        if (isset($filters['max_qty']) && $filters['max_qty'] !== '' && $filters['max_qty'] !== null) {
            $having[] = 'quantity <= :max_qty';
            $params['max_qty'] = (float) $filters['max_qty'];
        }
        if (isset($filters['min_value']) && $filters['min_value'] !== '' && $filters['min_value'] !== null) {
            $having[] = 'stock_value >= :min_value';
            $params['min_value'] = (float) $filters['min_value'];
        }
        if (isset($filters['max_value']) && $filters['max_value'] !== '' && $filters['max_value'] !== null) {
            $having[] = 'stock_value <= :max_value';
            $params['max_value'] = (float) $filters['max_value'];
        }
        if ($having !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $having);
        }

        return [$sql, $params];
    }

    /**
     * Clause LIMIT/OFFSET optionnelle (valeurs entières validées, sans liaison de paramètre).
     *
     * @param array<string, mixed> $filters
     */
    private function limitClause(array $filters): string
    {
        $limit = (int) ($filters['limit'] ?? 0);
        if ($limit <= 0) {
            return '';
        }

        return ' LIMIT ' . $limit . ' OFFSET ' . max(0, (int) ($filters['offset'] ?? 0));
    }

    public function find(int $id): ?Produit
    {
        $rows = $this->search(null, $id);
        return $rows[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function findRaw(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, barcode, designation, brand_id, category_id, unit_id,
                    purchase_cost, sale_price, min_stock, active
             FROM products WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array{id: int, sku: string, designation: string, brand: string, unit: string, purchase_cost: float, sale_price: float}> */
    public function allActiveSimple(): array
    {
        $stmt = $this->pdo->query(
            'SELECT p.id, p.sku, p.designation, COALESCE(b.name, \'\') AS brand,
                    u.code AS unit, p.purchase_cost, p.sale_price
             FROM products p
             INNER JOIN units u ON u.id = p.unit_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE p.active = 1 ORDER BY p.designation ASC'
        );

        return array_map(static fn (array $row): array => [
            'id'            => (int) $row['id'],
            'sku'           => (string) $row['sku'],
            'designation'   => (string) $row['designation'],
            'brand'         => (string) $row['brand'],
            'unit'          => (string) $row['unit'],
            'purchase_cost' => (float) $row['purchase_cost'],
            'sale_price'    => (float) $row['sale_price'],
        ], $stmt->fetchAll());
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO products (sku, barcode, designation, brand_id, category_id, unit_id,
                                       purchase_cost, sale_price, min_stock, active)
                 VALUES (:sku, :barcode, :designation, :brand_id, :category_id, :unit_id,
                         :purchase_cost, :sale_price, :min_stock, :active)'
            );
            $stmt->execute($this->bindings($data));
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new RuntimeException('Ce SKU ou code-barres existe déjà.', 0, $e);
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE products SET sku = :sku, barcode = :barcode, designation = :designation,
                        brand_id = :brand_id, category_id = :category_id, unit_id = :unit_id,
                        purchase_cost = :purchase_cost, sale_price = :sale_price,
                        min_stock = :min_stock, active = :active, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute($this->bindings($data) + ['id' => $id]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new RuntimeException('Ce SKU ou code-barres existe déjà.', 0, $e);
            }
            throw $e;
        }
    }

    public function toggle(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE products SET active = 1 - active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Produit introuvable.');
        }
    }

    public function stockAt(int $productId, int $locationId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(qty, 0) FROM stock_levels WHERE product_id = :product_id AND location_id = :location_id LIMIT 1'
        );
        $stmt->execute(['product_id' => $productId, 'location_id' => $locationId]);

        return (float) $stmt->fetchColumn();
    }

    public function purchaseCost(int $productId): float
    {
        $stmt = $this->pdo->prepare('SELECT purchase_cost FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);

        return (float) $stmt->fetchColumn();
    }

    /** @return array{total_value: float, total_quantity: float, products: int, low_stock: int, locations: int} */
    public function stats(): array
    {
        $totalValue = (float) $this->pdo->query(
            'SELECT COALESCE(SUM(sl.qty * p.purchase_cost), 0)
             FROM stock_levels sl INNER JOIN products p ON p.id = sl.product_id'
        )->fetchColumn();

        $totalQuantity = (float) $this->pdo->query('SELECT COALESCE(SUM(qty), 0) FROM stock_levels')->fetchColumn();

        $products = (int) $this->pdo->query('SELECT COUNT(*) FROM products WHERE active = 1')->fetchColumn();
        $locations = (int) $this->pdo->query('SELECT COUNT(*) FROM locations WHERE active = 1')->fetchColumn();

        $lowStock = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM (
                SELECT p.id, p.min_stock, COALESCE(SUM(sl.qty), 0) AS q
                FROM products p
                LEFT JOIN stock_levels sl ON sl.product_id = p.id
                WHERE p.active = 1
                GROUP BY p.id, p.min_stock
                HAVING q <= p.min_stock
            ) AS alerts'
        )->fetchColumn();

        return [
            'total_value'    => $totalValue,
            'total_quantity' => $totalQuantity,
            'products'       => $products,
            'low_stock'      => $lowStock,
            'locations'      => $locations,
        ];
    }

    /** @return array<int, array{name: string, type: string, quantity: float, value: float}> */
    public function stockByLocation(array $filters = []): array
    {
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $brandId = (int) ($filters['brand_id'] ?? 0);
        $locationId = (int) ($filters['location_id'] ?? 0);

        $productJoin = ' LEFT JOIN products p ON p.id = sl.product_id';
        $params = [];
        if ($categoryId > 0) {
            $productJoin .= ' AND p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }
        if ($brandId > 0) {
            $productJoin .= ' AND p.brand_id = :brand_id';
            $params['brand_id'] = $brandId;
        }

        $where = ' WHERE l.active = 1';
        if ($locationId > 0) {
            $where .= ' AND l.id = :location_id';
            $params['location_id'] = $locationId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT l.name, l.type,
                    COALESCE(SUM(sl.qty), 0) AS quantity,
                    COALESCE(SUM(sl.qty * p.purchase_cost), 0) AS value
             FROM locations l
             LEFT JOIN stock_levels sl ON sl.location_id = l.id' . $productJoin . $where . '
             GROUP BY l.id, l.name, l.type
             ORDER BY value DESC, l.name ASC'
        );
        $stmt->execute($params);

        return array_map(static fn (array $row): array => [
            'name'     => (string) $row['name'],
            'type'     => (string) $row['type'],
            'quantity' => (float) $row['quantity'],
            'value'    => (float) $row['value'],
        ], $stmt->fetchAll());
    }

    /** @return array<int, array{name: string, products: int, quantity: float, value: float}> */
    public function stockByCategory(array $filters = []): array
    {
        $brandId = (int) ($filters['brand_id'] ?? 0);
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $locationId = (int) ($filters['location_id'] ?? 0);

        $productJoin = ' AND p.active = 1';
        $params = [];
        if ($brandId > 0) {
            $productJoin .= ' AND p.brand_id = :brand_id';
            $params['brand_id'] = $brandId;
        }
        if ($categoryId > 0) {
            $productJoin .= ' AND p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $stockJoin = ' ON sl.product_id = p.id';
        if ($locationId > 0) {
            $stockJoin .= ' AND sl.location_id = :location_id';
            $params['location_id'] = $locationId;
        }

        $where = $categoryId > 0 ? ' WHERE c.id = :category_filter' : '';
        if ($categoryId > 0) {
            $params['category_filter'] = $categoryId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT c.name,
                    COUNT(DISTINCT p.id) AS products,
                    COALESCE(SUM(sl.qty), 0) AS quantity,
                    COALESCE(SUM(sl.qty * p.purchase_cost), 0) AS value
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id' . $productJoin . '
             LEFT JOIN stock_levels sl' . $stockJoin . $where . '
             GROUP BY c.id, c.name
             ORDER BY value DESC, c.name ASC'
        );
        $stmt->execute($params);

        return array_map(static fn (array $row): array => [
            'name'     => (string) $row['name'],
            'products' => (int) $row['products'],
            'quantity' => (float) $row['quantity'],
            'value'    => (float) $row['value'],
        ], $stmt->fetchAll());
    }

    /**
     * Vue de consultation : stock consolidé, positionnement et valorisation.
     *
     * @param array{q?: string, category_id?: int, brand_id?: int, unit_id?: int,
     *     location_id?: int, status?: string, min_qty?: float, max_qty?: float,
     *     min_value?: float, max_value?: float, min_margin?: float, max_margin?: float,
     *     sort?: string} $filters
     * @return array<int, array{
     *     id: int, sku: string, designation: string, brand: string, category: string, unit: string,
     *     purchase_cost: float, sale_price: float, min_stock: float, quantity: float,
     *     stock_value: float, sale_value: float, margin_value: float, positions: string
     * }>
     */
    /**
     * Consultation du stock (paginée).
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function consultation(array $filters = []): array
    {
        [$sql, $params] = $this->buildConsultation($filters);

        $sort = (string) ($filters['sort'] ?? 'value');
        $order = match ($sort) {
            'name' => 'p.designation ASC',
            'qty_desc' => 'quantity DESC, p.designation ASC',
            'qty_asc', 'low' => 'quantity ASC, p.designation ASC',
            'value' => 'stock_value DESC, p.designation ASC',
            'value_asc' => 'stock_value ASC, p.designation ASC',
            'margin' => '(p.sale_price - p.purchase_cost) * quantity DESC, p.designation ASC',
            default => 'stock_value DESC, p.designation ASC',
        };
        $sql .= ' ORDER BY ' . $order . $this->limitClause($filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            $quantity = (float) $row['quantity'];
            $cost = (float) $row['purchase_cost'];
            $price = (float) $row['sale_price'];

            return [
                'id'            => (int) $row['id'],
                'sku'           => (string) $row['sku'],
                'designation'   => (string) $row['designation'],
                'brand'         => (string) $row['brand'],
                'category'      => (string) $row['category'],
                'unit'          => (string) $row['unit'],
                'purchase_cost' => $cost,
                'sale_price'    => $price,
                'min_stock'     => (float) $row['min_stock'],
                'quantity'      => $quantity,
                'stock_value'   => (float) $row['stock_value'],
                'sale_value'    => (float) $row['sale_value'],
                'margin_value'  => ($price - $cost) * $quantity,
                'positions'     => (string) $row['positions'],
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Totaux de la consultation courante, indépendants de la pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{total: int, quantity: float, value: float, sale_value: float, margin: float, alerts: int}
     */
    public function consultationSummary(array $filters = []): array
    {
        [$sql, $params] = $this->buildConsultation($filters);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(quantity), 0) AS quantity,
                    COALESCE(SUM(stock_value), 0) AS value,
                    COALESCE(SUM(sale_value), 0) AS sale_value,
                    COALESCE(SUM((sale_price - purchase_cost) * quantity), 0) AS margin,
                    COALESCE(SUM(CASE WHEN quantity <= min_stock THEN 1 ELSE 0 END), 0) AS alerts
             FROM (' . $sql . ') AS t'
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total'      => (int) ($row['total'] ?? 0),
            'quantity'   => (float) ($row['quantity'] ?? 0),
            'value'      => (float) ($row['value'] ?? 0),
            'sale_value' => (float) ($row['sale_value'] ?? 0),
            'margin'     => (float) ($row['margin'] ?? 0),
            'alerts'     => (int) ($row['alerts'] ?? 0),
        ];
    }

    /**
     * Construit la requête de consultation filtrée, sans tri ni pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildConsultation(array $filters): array
    {
        $term = trim((string) ($filters['q'] ?? ''));
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $brandId = (int) ($filters['brand_id'] ?? 0);
        $unitId = (int) ($filters['unit_id'] ?? 0);
        $locationId = (int) ($filters['location_id'] ?? 0);
        $status = (string) ($filters['status'] ?? '');

        $joinLocation = '';
        $params = [];
        if ($locationId > 0) {
            $joinLocation = ' AND sl.location_id = :location_join';
            $params['location_join'] = $locationId;
        }

        $sql = 'SELECT p.id, p.sku, p.designation, p.purchase_cost, p.sale_price, p.min_stock,
                       b.name AS brand, c.name AS category, u.code AS unit,
                       COALESCE(SUM(sl.qty), 0) AS quantity,
                       COALESCE(SUM(sl.qty * p.purchase_cost), 0) AS stock_value,
                       COALESCE(SUM(sl.qty * p.sale_price), 0) AS sale_value,
                       COALESCE(GROUP_CONCAT(CASE WHEN sl.qty <> 0 THEN CONCAT(l.code, " : ", sl.qty) END
                                             ORDER BY l.code SEPARATOR " · "), "—") AS positions
                FROM products p
                INNER JOIN brands b ON b.id = p.brand_id
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN units u ON u.id = p.unit_id
                LEFT JOIN stock_levels sl ON sl.product_id = p.id' . $joinLocation . '
                LEFT JOIN locations l ON l.id = sl.location_id
                WHERE p.active = 1';

        if ($term !== '') {
            $sql .= ' AND (p.sku LIKE :term1 OR p.designation LIKE :term2 OR b.name LIKE :term3
                         OR c.name LIKE :term4 OR p.barcode LIKE :term5)';
            $like = '%' . $term . '%';
            $params['term1'] = $like;
            $params['term2'] = $like;
            $params['term3'] = $like;
            $params['term4'] = $like;
            $params['term5'] = $like;
        }

        foreach (['category_id' => $categoryId, 'brand_id' => $brandId, 'unit_id' => $unitId] as $key => $value) {
            if ($value > 0) {
                $sql .= ' AND p.' . $key . ' = :' . $key;
                $params[$key] = $value;
            }
        }

        $sql .= ' GROUP BY p.id, p.sku, p.designation, p.purchase_cost, p.sale_price, p.min_stock,
                           b.name, c.name, u.code';

        $having = [];
        if ($status === 'out') {
            $having[] = 'quantity <= 0';
        } elseif ($status === 'low') {
            $having[] = 'quantity > 0 AND quantity <= p.min_stock';
        } elseif ($status === 'ok') {
            $having[] = 'quantity > p.min_stock';
        }

        foreach ([
            'min_qty' => 'quantity >= :min_qty',
            'max_qty' => 'quantity <= :max_qty',
            'min_value' => 'stock_value >= :min_value',
            'max_value' => 'stock_value <= :max_value',
            'min_margin' => '(p.sale_price - p.purchase_cost) * quantity >= :min_margin',
            'max_margin' => '(p.sale_price - p.purchase_cost) * quantity <= :max_margin',
        ] as $key => $condition) {
            if (isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== null) {
                $having[] = $condition;
                $params[$key] = (float) $filters[$key];
            }
        }
        if ($having !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $having);
        }

        return [$sql, $params];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Produit
    {
        return Produit::fromRow($row);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function bindings(array $data): array
    {
        $barcode = trim((string) ($data['barcode'] ?? ''));

        return [
            'sku'           => trim((string) ($data['sku'] ?? '')),
            'barcode'       => $barcode === '' ? null : $barcode,
            'designation'   => trim((string) ($data['designation'] ?? '')),
            'brand_id'      => (int) ($data['brand_id'] ?? 0),
            'category_id'   => (int) ($data['category_id'] ?? 0),
            'unit_id'       => (int) ($data['unit_id'] ?? 0),
            'purchase_cost' => round((float) ($data['purchase_cost'] ?? 0), 2),
            'sale_price'    => round((float) ($data['sale_price'] ?? 0), 2),
            'min_stock'     => round((float) ($data['min_stock'] ?? 0), 3),
            'active'        => !empty($data['active']) ? 1 : 0,
        ];
    }
}

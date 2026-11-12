<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use DateTimeImmutable;
use PDO;

final class PdoStatisticsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @return array{
     *     total_value: float, total_quantity: float, potential_value: float, margin_value: float,
     *     products: int, stocked_products: int, locations: int, low_stock: int,
     *     in_qty: float, out_qty: float, in_value: float, out_value: float, net_value: float,
     *     days: int, start_date: string, end_date: string
     * }
     */
    public function summary(int $days, int $locationId = 0, int $categoryId = 0, int $brandId = 0): array
    {
        $days = max(1, $days);
        $stockWhere = '';
        $stockParams = [];
        $productWhere = $this->productFilter($categoryId, $brandId);
        $productParams = $this->productFilterParams($categoryId, $brandId);
        if ($locationId > 0) {
            $stockWhere = ' AND sl.location_id = :location_stock';
            $stockParams['location_stock'] = $locationId;
        }
        $stockParams += $productParams;

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(sl.qty), 0) AS total_quantity,
                    COALESCE(SUM(sl.qty * p.purchase_cost), 0) AS total_value,
                    COALESCE(SUM(sl.qty * p.sale_price), 0) AS potential_value,
                    COUNT(DISTINCT CASE WHEN sl.qty <> 0 THEN p.id END) AS stocked_products
             FROM stock_levels sl
             INNER JOIN products p ON p.id = sl.product_id
             WHERE 1 = 1' . $stockWhere . $productWhere
        );
        $stmt->execute($stockParams);
        $stock = $stmt->fetch() ?: [
            'total_quantity' => 0,
            'total_value' => 0,
            'potential_value' => 0,
            'stocked_products' => 0,
        ];

        $lowSql = 'SELECT COUNT(*) FROM (
                       SELECT p.id, p.min_stock, COALESCE(SUM(sl.qty), 0) AS q
                       FROM products p
                       LEFT JOIN stock_levels sl ON sl.product_id = p.id';
        $lowParams = [];
        if ($locationId > 0) {
            $lowSql .= ' AND sl.location_id = :location_low';
            $lowParams['location_low'] = $locationId;
        }
        $lowSql .= ' WHERE p.active = 1' . $productWhere . '
                      GROUP BY p.id, p.min_stock
                      HAVING q <= p.min_stock
                  ) AS alerts';
        $lowStmt = $this->pdo->prepare($lowSql);
        $lowStmt->execute($lowParams + $productParams);

        $today = new DateTimeImmutable('today');
        $start = $today->modify('-' . ($days - 1) . ' days');
        $startDate = $start->format('Y-m-d');
        $endDate = $today->format('Y-m-d');

        $movementWhere = '';
        $movementParams = [
            'start_movement' => $startDate . ' 00:00:00',
            'end_movement'   => $endDate . ' 23:59:59',
        ];
        if ($locationId > 0) {
            $movementWhere = ' AND m.location_id = :location_movement';
            $movementParams['location_movement'] = $locationId;
        }
        $movementParams += $productParams;

        $movementStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(CASE WHEN m.direction = "in" THEN m.qty ELSE 0 END), 0) AS in_qty,
                    COALESCE(SUM(CASE WHEN m.direction = "out" THEN m.qty ELSE 0 END), 0) AS out_qty,
                    COALESCE(SUM(CASE WHEN m.direction = "in" THEN m.qty * m.unit_cost ELSE 0 END), 0) AS in_value,
                    COALESCE(SUM(CASE WHEN m.direction = "out" THEN m.qty * m.unit_cost ELSE 0 END), 0) AS out_value
             FROM stock_movements m
             INNER JOIN products p ON p.id = m.product_id
             WHERE m.movement_date >= :start_movement
               AND m.movement_date <= :end_movement' . $movementWhere . $productWhere
        );
        $movementStmt->execute($movementParams);
        $movement = $movementStmt->fetch() ?: [
            'in_qty' => 0,
            'out_qty' => 0,
            'in_value' => 0,
            'out_value' => 0,
        ];

        $totalValue = (float) $stock['total_value'];
        $potentialValue = (float) $stock['potential_value'];

        $productCountStmt = $this->pdo->prepare('SELECT COUNT(*) FROM products p WHERE p.active = 1' . $productWhere);
        $productCountStmt->execute($productParams);

        return [
            'total_value'      => $totalValue,
            'total_quantity'   => (float) $stock['total_quantity'],
            'potential_value'  => $potentialValue,
            'margin_value'     => $potentialValue - $totalValue,
            'products'         => (int) $productCountStmt->fetchColumn(),
            'stocked_products' => (int) $stock['stocked_products'],
            'locations'        => (int) $this->pdo->query('SELECT COUNT(*) FROM locations WHERE active = 1')->fetchColumn(),
            'low_stock'        => (int) $lowStmt->fetchColumn(),
            'in_qty'           => (float) $movement['in_qty'],
            'out_qty'          => (float) $movement['out_qty'],
            'in_value'         => (float) $movement['in_value'],
            'out_value'        => (float) $movement['out_value'],
            'net_value'        => (float) $movement['in_value'] - (float) $movement['out_value'],
            'days'             => $days,
            'start_date'       => $startDate,
            'end_date'         => $endDate,
        ];
    }

    /**
     * Reconstruit le capital et la quantité à partir des mouvements valorisés.
     *
     * @return array{
     *     labels: array<int, string>, capital: array<int, float>, quantity: array<int, float>,
     *     in_qty: array<int, float>, out_qty: array<int, float>,
     *     in_value: array<int, float>, out_value: array<int, float>,
     *     start_date: string, end_date: string
     * }
     */
    public function history(int $days, int $locationId = 0, int $categoryId = 0, int $brandId = 0): array
    {
        $days = max(1, $days);
        $today = new DateTimeImmutable('today');
        $start = $today->modify('-' . ($days - 1) . ' days');
        $startDate = $start->format('Y-m-d');
        $endDate = $today->format('Y-m-d');

        $locationWhere = '';
        $stockWhere = '';
        $stockParams = [];
        $productWhere = $this->productFilter($categoryId, $brandId);
        $productParams = $this->productFilterParams($categoryId, $brandId);
        if ($locationId > 0) {
            $locationWhere = ' AND m.location_id = :location_history';
            $stockWhere = ' WHERE sl.location_id = :location_stock';
            $stockParams['location_stock'] = $locationId;
        }
        $stockParams += $productParams;

        $positionStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(sl.qty), 0) AS quantity,
                    COALESCE(SUM(sl.qty * p.purchase_cost), 0) AS value
             FROM stock_levels sl
             INNER JOIN products p ON p.id = sl.product_id' . $stockWhere . $productWhere
        );
        $positionStmt->execute($stockParams);
        $position = $positionStmt->fetch() ?: ['quantity' => 0, 'value' => 0];

        $baselineParams = ['start_baseline' => $startDate . ' 00:00:00'];
        if ($locationId > 0) {
            $baselineParams['location_history'] = $locationId;
        }
        $baselineParams += $productParams;

        $baselineStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(CASE WHEN m.direction = "in" THEN m.qty ELSE -m.qty END), 0) AS quantity,
                    COALESCE(SUM(CASE WHEN m.direction = "in" THEN m.qty * m.unit_cost ELSE -m.qty * m.unit_cost END), 0) AS value
             FROM stock_movements m
             INNER JOIN products p ON p.id = m.product_id
             WHERE m.movement_date < :start_baseline' . $locationWhere . $productWhere
        );
        $baselineStmt->execute($baselineParams);
        $baseline = $baselineStmt->fetch() ?: ['quantity' => 0, 'value' => 0];

        $dailyParams = [
            'start_daily' => $startDate . ' 00:00:00',
            'end_daily'   => $endDate . ' 23:59:59',
        ];
        if ($locationId > 0) {
            $dailyParams['location_history'] = $locationId;
        }
        $dailyParams += $productParams;

        $dailyStmt = $this->pdo->prepare(
            'SELECT DATE(m.movement_date) AS movement_day,
                    COALESCE(SUM(CASE WHEN m.direction = "in" THEN m.qty ELSE 0 END), 0) AS in_qty,
                    COALESCE(SUM(CASE WHEN m.direction = "out" THEN m.qty ELSE 0 END), 0) AS out_qty,
                    COALESCE(SUM(CASE WHEN m.direction = "in" THEN m.qty * m.unit_cost ELSE 0 END), 0) AS in_value,
                    COALESCE(SUM(CASE WHEN m.direction = "out" THEN m.qty * m.unit_cost ELSE 0 END), 0) AS out_value
             FROM stock_movements m
             INNER JOIN products p ON p.id = m.product_id
             WHERE m.movement_date >= :start_daily
               AND m.movement_date <= :end_daily' . $locationWhere . $productWhere . '
             GROUP BY DATE(m.movement_date)'
        );
        $dailyStmt->execute($dailyParams);

        $byDay = [];
        foreach ($dailyStmt->fetchAll() as $row) {
            $byDay[(string) $row['movement_day']] = [
                'in_qty'    => (float) $row['in_qty'],
                'out_qty'   => (float) $row['out_qty'],
                'in_value'  => (float) $row['in_value'],
                'out_value' => (float) $row['out_value'],
            ];
        }

        // Le dernier point est aligné sur la valorisation CUMP réelle du stock
        // pour neutraliser les écarts d'arrondi des coûts unitaires historisés.
        $capital = (float) $baseline['value'];
        $quantity = (float) $baseline['quantity'];
        $labels = [];
        $capitalSeries = [];
        $quantitySeries = [];
        $inQtySeries = [];
        $outQtySeries = [];
        $inValueSeries = [];
        $outValueSeries = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->modify('+' . $i . ' days');
            $key = $day->format('Y-m-d');
            $delta = $byDay[$key] ?? ['in_qty' => 0, 'out_qty' => 0, 'in_value' => 0, 'out_value' => 0];

            $quantity += $delta['in_qty'] - $delta['out_qty'];
            $capital += $delta['in_value'] - $delta['out_value'];

            $labels[] = $day->format('d/m');
            $capitalSeries[] = round($capital, 2);
            $quantitySeries[] = round($quantity, 3);
            $inQtySeries[] = round($delta['in_qty'], 3);
            $outQtySeries[] = round($delta['out_qty'], 3);
            $inValueSeries[] = round($delta['in_value'], 2);
            $outValueSeries[] = round($delta['out_value'], 2);
        }

        if ($capitalSeries !== []) {
            $last = count($capitalSeries) - 1;
            $capitalSeries[$last] = round((float) $position['value'], 2);
            $quantitySeries[$last] = round((float) $position['quantity'], 3);
        }

        return [
            'labels'     => $labels,
            'capital'    => $capitalSeries,
            'quantity'   => $quantitySeries,
            'in_qty'     => $inQtySeries,
            'out_qty'    => $outQtySeries,
            'in_value'   => $inValueSeries,
            'out_value'  => $outValueSeries,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];
    }

    /** @return array<int, array{name: string, products: int, quantity: float, value: float}> */
    public function categoryBreakdown(int $locationId = 0, int $categoryId = 0, int $brandId = 0): array
    {
        $join = '';
        $params = [];
        if ($locationId > 0) {
            $join = ' AND sl.location_id = :location_category';
            $params['location_category'] = $locationId;
        }

        $productJoin = ' AND p.active = 1';
        if ($brandId > 0) {
            $productJoin .= ' AND p.brand_id = :brand_category';
            $params['brand_category'] = $brandId;
        }
        $where = '';
        if ($categoryId > 0) {
            $where = ' WHERE c.id = :category_category';
            $params['category_category'] = $categoryId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT c.name,
                    COUNT(DISTINCT CASE WHEN sl.qty <> 0 THEN p.id END) AS products,
                    COALESCE(SUM(sl.qty), 0) AS quantity,
                    COALESCE(SUM(sl.qty * p.purchase_cost), 0) AS value
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id' . $productJoin . '
             LEFT JOIN stock_levels sl ON sl.product_id = p.id' . $join . $where . '
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

    /** @return array<int, array{sku: string, designation: string, category: string, unit: string, quantity: float, value: float, sale_value: float}> */
    public function topProducts(int $limit = 8, int $locationId = 0, int $categoryId = 0, int $brandId = 0): array
    {
        $limit = max(1, min(50, $limit));
        $join = '';
        $params = [];
        if ($locationId > 0) {
            $join = ' AND sl.location_id = :location_top';
            $params['location_top'] = $locationId;
        }

        $productWhere = $this->productFilter($categoryId, $brandId);
        $params += $this->productFilterParams($categoryId, $brandId);

        $stmt = $this->pdo->prepare(
            'SELECT p.sku, p.designation, c.name AS category, u.code AS unit,
                    COALESCE(SUM(sl.qty), 0) AS quantity,
                    COALESCE(SUM(sl.qty * p.purchase_cost), 0) AS value,
                    COALESCE(SUM(sl.qty * p.sale_price), 0) AS sale_value
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN units u ON u.id = p.unit_id
             LEFT JOIN stock_levels sl ON sl.product_id = p.id' . $join . '
             WHERE p.active = 1' . $productWhere . '
             GROUP BY p.id, p.sku, p.designation, c.name, u.code
             HAVING quantity > 0
             ORDER BY value DESC, p.designation ASC
             LIMIT ' . $limit
        );
        $stmt->execute($params);

        return array_map(static fn (array $row): array => [
            'sku'         => (string) $row['sku'],
            'designation' => (string) $row['designation'],
            'category'    => (string) $row['category'],
            'unit'        => (string) $row['unit'],
            'quantity'    => (float) $row['quantity'],
            'value'       => (float) $row['value'],
            'sale_value'  => (float) $row['sale_value'],
        ], $stmt->fetchAll());
    }

    private function productFilter(int $categoryId, int $brandId, string $alias = 'p'): string
    {
        $sql = '';
        if ($categoryId > 0) {
            $sql .= ' AND ' . $alias . '.category_id = :filter_category';
        }
        if ($brandId > 0) {
            $sql .= ' AND ' . $alias . '.brand_id = :filter_brand';
        }

        return $sql;
    }

    /** @return array<string, int> */
    private function productFilterParams(int $categoryId, int $brandId): array
    {
        $params = [];
        if ($categoryId > 0) {
            $params['filter_category'] = $categoryId;
        }
        if ($brandId > 0) {
            $params['filter_brand'] = $brandId;
        }

        return $params;
    }
}

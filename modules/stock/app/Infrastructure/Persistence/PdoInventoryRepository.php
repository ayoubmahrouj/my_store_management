<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use App\Domain\Entities\InventoryLine;
use App\Domain\Entities\InventorySession;
use PDO;

final class PdoInventoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @param array{status?: string, location_id?: int, q?: string} $filters
     * @return array<int, InventorySession>
     */
    public function search(array $filters = []): array
    {
        $sql = $this->baseSelect() . ' WHERE 1 = 1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND s.status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['location_id'])) {
            $sql .= ' AND s.location_id = :location_id';
            $params['location_id'] = (int) $filters['location_id'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (s.numero LIKE :q1 OR l.name LIKE :q2)';
            $like = '%' . trim((string) $filters['q']) . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }

        $sql .= ' ORDER BY s.started_at DESC, s.id DESC LIMIT 200';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): InventorySession => $this->hydrateSession($row), $stmt->fetchAll());
    }

    public function find(int $id): ?InventorySession
    {
        $stmt = $this->pdo->prepare($this->baseSelect() . ' WHERE s.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateSession($row, $this->lines($id));
    }

    /** @return array<int, InventoryLine> */
    public function lines(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT il.id, il.product_id, p.sku, p.designation, COALESCE(b.name, \'\') AS brand,
                    u.code AS unit, il.qty_theoretical, il.qty_counted, il.gap, il.unit_cost
             FROM inventory_lines il
             INNER JOIN products p ON p.id = il.product_id
             INNER JOIN units u ON u.id = p.unit_id
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE il.session_id = :id
             ORDER BY p.designation ASC'
        );
        $stmt->execute(['id' => $id]);

        return array_map(static fn (array $row): InventoryLine => new InventoryLine(
            (int) $row['id'],
            (int) $row['product_id'],
            (string) $row['sku'],
            (string) $row['designation'],
            (string) $row['brand'],
            (string) $row['unit'],
            (float) $row['qty_theoretical'],
            $row['qty_counted'] !== null ? (float) $row['qty_counted'] : null,
            $row['gap'] !== null ? (float) $row['gap'] : null,
            (float) $row['unit_cost'],
        ), $stmt->fetchAll());
    }

    /** @return array{open: int, counting: int, validated: int, total: int} */
    public function stats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COALESCE(SUM(status = 'ouvert'), 0) AS open,
                COALESCE(SUM(status = 'comptage'), 0) AS counting,
                COALESCE(SUM(status = 'valide'), 0) AS validated,
                COUNT(*) AS total
             FROM inventory_sessions"
        )->fetch() ?: [];

        return [
            'open'      => (int) ($row['open'] ?? 0),
            'counting'  => (int) ($row['counting'] ?? 0),
            'validated' => (int) ($row['validated'] ?? 0),
            'total'     => (int) ($row['total'] ?? 0),
        ];
    }

    private function baseSelect(): string
    {
        return 'SELECT s.id, s.numero, s.location_id, l.name AS location_name, s.status,
                       s.started_by, su.name AS started_by_name, s.started_at,
                       s.validated_by, vu.name AS validated_by_name, s.validated_at, s.notes,
                       (SELECT COUNT(*) FROM inventory_lines x WHERE x.session_id = s.id) AS line_count,
                       (SELECT COUNT(*) FROM inventory_lines x WHERE x.session_id = s.id AND x.qty_counted IS NOT NULL) AS counted_count,
                       (SELECT COALESCE(SUM(x.gap * x.unit_cost), 0) FROM inventory_lines x
                        WHERE x.session_id = s.id AND x.gap IS NOT NULL) AS gap_value
                FROM inventory_sessions s
                INNER JOIN locations l ON l.id = s.location_id
                INNER JOIN users su ON su.id = s.started_by
                LEFT JOIN users vu ON vu.id = s.validated_by';
    }

    /** @param array<string, mixed> $row
     *  @param array<int, InventoryLine> $lines
     */
    private function hydrateSession(array $row, array $lines = []): InventorySession
    {
        return new InventorySession(
            (int) $row['id'],
            (string) $row['numero'],
            (int) $row['location_id'],
            (string) $row['location_name'],
            (string) $row['status'],
            (string) $row['started_by_name'],
            (string) $row['started_at'],
            $row['validated_by_name'] !== null ? (string) $row['validated_by_name'] : null,
            $row['validated_at'] !== null ? (string) $row['validated_at'] : null,
            $row['notes'] !== null ? (string) $row['notes'] : null,
            $lines,
            (int) ($row['line_count'] ?? 0),
            (int) ($row['counted_count'] ?? 0),
            (float) ($row['gap_value'] ?? 0),
        );
    }
}

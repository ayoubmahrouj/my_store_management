<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use App\Domain\Entities\StockDocument;
use App\Domain\Entities\StockDocumentLine;
use App\Domain\Entities\StockMovement;
use PDO;

final class PdoDocumentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * Registre de documents (paginé).
     *
     * @param array{q?: string, status?: string, location_id?: int, date_from?: string, date_to?: string,
     *     motif?: string, supplier_id?: int, product_id?: int, product_q?: string, amount_from?: float,
     *     amount_to?: float, created_by?: int, limit?: int, offset?: int} $filters
     * @return array<int, StockDocument>
     */
    public function search(string $type, array $filters = []): array
    {
        [$sql, $params] = $this->buildSearch($type, $filters);
        $sql .= ' ORDER BY d.doc_date DESC, d.id DESC' . $this->limitClause($filters);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): StockDocument => $this->hydrateDocument($row), $stmt->fetchAll());
    }

    /**
     * Nombre de documents correspondant aux filtres, indépendamment de la pagination.
     *
     * @param array<string, mixed> $filters
     */
    public function countSearch(string $type, array $filters = []): int
    {
        [$sql, $params] = $this->buildSearch($type, $filters);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM (' . $sql . ') AS t');
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Construit la requête du registre, sans tri ni pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildSearch(string $type, array $filters): array
    {
        $sql = $this->baseSelect() . ' WHERE d.type = :type';
        $params = ['type' => $type];

        if (!empty($filters['status'])) {
            $sql .= ' AND d.status = :status';
            $params['status'] = (string) $filters['status'];
        }

        if (!empty($filters['location_id'])) {
            $sql .= ' AND (d.location_id = :location_id OR d.dest_location_id = :dest_location_id)';
            $params['location_id'] = (int) $filters['location_id'];
            $params['dest_location_id'] = (int) $filters['location_id'];
        }

        if (!empty($filters['supplier_id'])) {
            $sql .= ' AND d.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        if (!empty($filters['created_by'])) {
            $sql .= ' AND d.created_by = :created_by';
            $params['created_by'] = (int) $filters['created_by'];
        }

        if (!empty($filters['motif'])) {
            $sql .= ' AND d.motif = :motif';
            $params['motif'] = (string) $filters['motif'];
        }

        if (!empty($filters['product_id'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM stock_document_lines x
                                  WHERE x.document_id = d.id AND x.product_id = :product_id)';
            $params['product_id'] = (int) $filters['product_id'];
        }

        if (!empty($filters['product_q'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM stock_document_lines xq
                                  INNER JOIN products xqp ON xqp.id = xq.product_id
                                  WHERE xq.document_id = d.id
                                    AND (xqp.sku LIKE :pq1 OR xqp.designation LIKE :pq2))';
            $like = '%' . trim((string) $filters['product_q']) . '%';
            $params['pq1'] = $like;
            $params['pq2'] = $like;
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND d.doc_date >= :date_from';
            $params['date_from'] = (string) $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND d.doc_date <= :date_to';
            $params['date_to'] = (string) $filters['date_to'];
        }

        if (isset($filters['amount_from']) && $filters['amount_from'] !== '' && $filters['amount_from'] !== null) {
            $sql .= ' AND d.total_cost >= :amount_from';
            $params['amount_from'] = (float) $filters['amount_from'];
        }

        if (isset($filters['amount_to']) && $filters['amount_to'] !== '' && $filters['amount_to'] !== null) {
            $sql .= ' AND d.total_cost <= :amount_to';
            $params['amount_to'] = (float) $filters['amount_to'];
        }

        if (!empty($filters['q'])) {
            $sql .= ' AND (d.numero LIKE :q1 OR d.reference LIKE :q2 OR l.name LIKE :q3
                        OR l.code LIKE :q4 OR dl.name LIKE :q5 OR s.name LIKE :q6 OR d.motif LIKE :q7
                        OR EXISTS (SELECT 1 FROM stock_document_lines xl
                                   INNER JOIN products xp ON xp.id = xl.product_id
                                   WHERE xl.document_id = d.id
                                     AND (xp.sku LIKE :q8 OR xp.designation LIKE :q9)))';
            $like = '%' . trim((string) $filters['q']) . '%';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'q9'] as $key) {
                $params[$key] = $like;
            }
        }

        return [$sql, $params];
    }

    /**
     * Clause LIMIT/OFFSET optionnelle.
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

    public function find(int $id): ?StockDocument
    {
        $stmt = $this->pdo->prepare($this->baseSelect() . ' WHERE d.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateDocument($row, $this->lines($id));
    }

    /** @return array<int, StockDocumentLine> */
    public function lines(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.id, l.product_id, p.sku, p.designation, u.code AS unit,
                    l.qty, l.unit_cost, l.unit_price
             FROM stock_document_lines l
             INNER JOIN products p ON p.id = l.product_id
             INNER JOIN units u ON u.id = p.unit_id
             WHERE l.document_id = :id
             ORDER BY l.id ASC'
        );
        $stmt->execute(['id' => $id]);

        return array_map(static fn (array $row): StockDocumentLine => new StockDocumentLine(
            (int) $row['id'],
            (int) $row['product_id'],
            (string) $row['sku'],
            (string) $row['designation'],
            (string) $row['unit'],
            (float) $row['qty'],
            (float) $row['unit_cost'],
            (float) $row['unit_price'],
        ), $stmt->fetchAll());
    }

    /**
     * Détail ligne à ligne d'un registre de documents, dans le même ordre et avec
     * les mêmes filtres que la liste. C'est la base des exports « détail des entrées »
     * qui permettent de retrouver chaque produit, quantité et prix d'achat.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array{
     *     document_id: int, numero: string, doc_date: string, status: string, motif: ?string,
     *     reference: ?string, location_name: string, location_code: string,
     *     dest_name: ?string, supplier_name: ?string, sku: string, designation: string,
     *     unit: string, qty: float, unit_cost: float, unit_price: float,
     *     line_cost: float, line_sale: float, created_by_name: string
     * }>
     */
    public function lineReport(string $type, array $filters = []): array
    {
        $documents = $this->search($type, $filters);
        if ($documents === []) {
            return [];
        }

        $ids = array_map(static fn (StockDocument $document): int => $document->id(), $documents);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = 'SELECT d.id AS document_id, d.numero, d.doc_date, d.status, d.motif, d.reference,
                       l.name AS location_name, l.code AS location_code,
                       dest.name AS dest_name, s.name AS supplier_name,
                       p.sku, p.designation, u.code AS unit,
                       x.qty, x.unit_cost, x.unit_price,
                       (x.qty * x.unit_cost) AS line_cost,
                       (x.qty * x.unit_price) AS line_sale,
                       cu.name AS created_by_name
                FROM stock_document_lines x
                INNER JOIN stock_documents d ON d.id = x.document_id
                INNER JOIN products p ON p.id = x.product_id
                INNER JOIN units u ON u.id = p.unit_id
                INNER JOIN locations l ON l.id = d.location_id
                LEFT JOIN locations dest ON dest.id = d.dest_location_id
                LEFT JOIN suppliers s ON s.id = d.supplier_id
                INNER JOIN users cu ON cu.id = d.created_by
                WHERE x.document_id IN (' . $placeholders . ')';

        $lineParams = $ids;
        if (!empty($filters['product_id'])) {
            $sql .= ' AND x.product_id = ?';
            $lineParams[] = (int) $filters['product_id'];
        }

        $sql .= ' ORDER BY d.doc_date DESC, d.id DESC, x.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($lineParams);

        return array_map(static fn (array $row): array => [
            'document_id'    => (int) $row['document_id'],
            'numero'         => (string) $row['numero'],
            'doc_date'       => (string) $row['doc_date'],
            'status'         => (string) $row['status'],
            'motif'          => $row['motif'] !== null ? (string) $row['motif'] : null,
            'reference'      => $row['reference'] !== null ? (string) $row['reference'] : null,
            'location_name'  => (string) $row['location_name'],
            'location_code'  => (string) $row['location_code'],
            'dest_name'      => $row['dest_name'] !== null ? (string) $row['dest_name'] : null,
            'supplier_name'  => $row['supplier_name'] !== null ? (string) $row['supplier_name'] : null,
            'sku'            => (string) $row['sku'],
            'designation'    => (string) $row['designation'],
            'unit'           => (string) $row['unit'],
            'qty'            => (float) $row['qty'],
            'unit_cost'      => (float) $row['unit_cost'],
            'unit_price'     => (float) $row['unit_price'],
            'line_cost'      => (float) $row['line_cost'],
            'line_sale'      => (float) $row['line_sale'],
            'created_by_name' => (string) $row['created_by_name'],
        ], $stmt->fetchAll());
    }

    /** @return array{draft: int, done: int, shipped: int, total: int, total_cost: float} */
    public function stats(string $type): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(status = 'brouillon'), 0) AS draft,
                COALESCE(SUM(status IN ('valide', 'recu')), 0) AS done,
                COALESCE(SUM(status = 'expedie'), 0) AS shipped,
                COUNT(*) AS total,
                COALESCE(SUM(total_cost), 0) AS total_cost
             FROM stock_documents WHERE type = :type"
        );
        $stmt->execute(['type' => $type]);
        $row = $stmt->fetch() ?: [];

        return [
            'draft'      => (int) ($row['draft'] ?? 0),
            'done'       => (int) ($row['done'] ?? 0),
            'shipped'    => (int) ($row['shipped'] ?? 0),
            'total'      => (int) ($row['total'] ?? 0),
            'total_cost' => (float) ($row['total_cost'] ?? 0),
        ];
    }

    /**
     * Journal des mouvements (paginé).
     *
     * @param array{q?: string, location_id?: int, type?: string, date_from?: string, date_to?: string,
     *     category_id?: int, brand_id?: int, direction?: string, limit?: int, offset?: int} $filters
     * @return array<int, StockMovement>
     */
    public function movements(array $filters = []): array
    {
        [$sql, $params] = $this->buildMovements($filters);

        $limit = max(1, min(1000, (int) ($filters['limit'] ?? 300)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $sql .= ' ORDER BY m.movement_date DESC, m.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static function (array $row): StockMovement {
            $type = $row['doc_type'] !== null ? (string) $row['doc_type'] : 'inventaire';
            $numero = $row['numero'] !== null ? (string) $row['numero'] : ($row['inventory_numero'] !== null ? (string) $row['inventory_numero'] : null);

            return new StockMovement(
                (int) $row['id'],
                (string) $row['movement_date'],
                (string) $row['direction'],
                $type,
                $row['motif'] !== null ? (string) $row['motif'] : ($type === 'inventaire' ? 'inventaire' : null),
                $numero,
                (string) $row['designation'],
                (string) $row['sku'],
                (string) $row['unit'],
                (string) $row['location_name'],
                (float) $row['qty'],
                (float) $row['unit_cost'],
                (float) $row['unit_price'],
                (string) $row['user_name'],
            );
        }, $stmt->fetchAll());
    }

    /** @param array<string, mixed> $filters */
    public function movementsCount(array $filters = []): int
    {
        [$sql, $params] = $this->buildMovements($filters);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM (' . $sql . ') AS t');
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Construit la requête du journal, sans tri ni pagination.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildMovements(array $filters): array
    {
        $sql = 'SELECT m.id, m.movement_date, m.direction, m.qty, m.unit_cost, m.unit_price,
                       p.sku, p.designation, u.code AS unit, l.name AS location_name,
                       d.type AS doc_type, d.motif, d.numero, us.name AS user_name,
                       m.inventory_session_id, inv.numero AS inventory_numero
                FROM stock_movements m
                INNER JOIN products p ON p.id = m.product_id
                INNER JOIN units u ON u.id = p.unit_id
                INNER JOIN locations l ON l.id = m.location_id
                LEFT JOIN stock_documents d ON d.id = m.document_id
                LEFT JOIN inventory_sessions inv ON inv.id = m.inventory_session_id
                INNER JOIN users us ON us.id = m.created_by
                WHERE 1 = 1';

        $params = [];
        if (!empty($filters['location_id'])) {
            $sql .= ' AND m.location_id = :location_id';
            $params['location_id'] = (int) $filters['location_id'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['brand_id'])) {
            $sql .= ' AND p.brand_id = :brand_id';
            $params['brand_id'] = (int) $filters['brand_id'];
        }
        if (!empty($filters['direction']) && in_array($filters['direction'], ['in', 'out'], true)) {
            $sql .= ' AND m.direction = :direction';
            $params['direction'] = (string) $filters['direction'];
        }
        if (!empty($filters['type'])) {
            if ($filters['type'] === 'inventaire') {
                $sql .= ' AND m.inventory_session_id IS NOT NULL';
            } else {
                $sql .= ' AND d.type = :doc_type';
                $params['doc_type'] = (string) $filters['type'];
            }
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND DATE(m.movement_date) >= :date_from';
            $params['date_from'] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND DATE(m.movement_date) <= :date_to';
            $params['date_to'] = (string) $filters['date_to'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (p.sku LIKE :q1 OR p.designation LIKE :q2 OR d.numero LIKE :q3
                        OR inv.numero LIKE :q4 OR l.name LIKE :q5 OR d.reference LIKE :q6)';
            $like = '%' . trim((string) $filters['q']) . '%';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $key) {
                $params[$key] = $like;
            }
        }

        return [$sql, $params];
    }

    /** @return array<string, int> */
    public function pendingCounts(): array
    {
        $rows = $this->pdo->query(
            "SELECT type, COUNT(*) AS total FROM stock_documents
             WHERE status = 'brouillon' GROUP BY type"
        )->fetchAll();

        $counts = ['entree' => 0, 'sortie' => 0, 'transfert' => 0, 'inventaire' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['type']] = (int) $row['total'];
        }

        return $counts;
    }

    private function baseSelect(): string
    {
        return 'SELECT d.id, d.type, d.motif, d.numero, d.doc_date, d.status, d.location_id,
                       l.name AS location_name, l.code AS location_code,
                       d.dest_location_id, dl.name AS dest_name, dl.code AS dest_code,
                       d.supplier_id, s.name AS supplier_name,
                       d.reference, d.notes, d.total_cost, d.total_sale,
                       d.created_by, cu.name AS created_by_name, d.created_at,
                       d.validated_at, d.shipped_at, d.received_at,
                       (SELECT COUNT(*) FROM stock_document_lines x WHERE x.document_id = d.id) AS line_count
                FROM stock_documents d
                INNER JOIN locations l ON l.id = d.location_id
                LEFT JOIN locations dl ON dl.id = d.dest_location_id
                LEFT JOIN suppliers s ON s.id = d.supplier_id
                INNER JOIN users cu ON cu.id = d.created_by';
    }

    /** @param array<string, mixed> $row
     *  @param array<int, StockDocumentLine> $lines
     */
    private function hydrateDocument(array $row, array $lines = []): StockDocument
    {
        return new StockDocument(
            (int) $row['id'],
            (string) $row['type'],
            $row['motif'] !== null ? (string) $row['motif'] : null,
            (string) $row['numero'],
            (string) $row['doc_date'],
            (string) $row['status'],
            (int) $row['location_id'],
            (string) $row['location_name'],
            $row['dest_location_id'] !== null ? (int) $row['dest_location_id'] : null,
            $row['dest_name'] !== null ? (string) $row['dest_name'] : null,
            $row['supplier_id'] !== null ? (int) $row['supplier_id'] : null,
            $row['supplier_name'] !== null ? (string) $row['supplier_name'] : null,
            $row['reference'] !== null ? (string) $row['reference'] : null,
            $row['notes'] !== null ? (string) $row['notes'] : null,
            (float) $row['total_cost'],
            (float) $row['total_sale'],
            (string) $row['created_by_name'],
            (string) $row['created_at'],
            $row['validated_at'] !== null ? (string) $row['validated_at'] : null,
            $row['shipped_at'] !== null ? (string) $row['shipped_at'] : null,
            $row['received_at'] !== null ? (string) $row['received_at'] : null,
            $lines,
            (int) ($row['line_count'] ?? 0),
        );
    }
}

<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Core\Database;
use App\Infrastructure\Persistence\PdoSettingsRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final class StockService
{
    private PDO $pdo;
    private PdoSettingsRepository $settings;

    private const PREFIXES = [
        'entree'     => 'ENT',
        'sortie'     => 'SOR',
        'transfert'  => 'TRF',
        'inventaire' => 'INV',
        'ouverture'  => 'OUV',
    ];

    public function __construct()
    {
        $this->pdo = Database::pdo();
        $this->settings = new PdoSettingsRepository();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function createDocument(
        string $type,
        string $motif,
        string $docDate,
        int $locationId,
        ?int $destLocationId,
        ?int $supplierId,
        ?string $reference,
        ?string $notes,
        array $items,
        int $userId
    ): int {
        if (!in_array($type, ['entree', 'sortie', 'transfert'], true)) {
            throw new RuntimeException('Type de document invalide.');
        }

        $docDate = $this->validDate($docDate);
        $this->assertLocation($locationId);

        if ($type === 'transfert') {
            if ($destLocationId === null || $destLocationId <= 0) {
                throw new RuntimeException('Sélectionnez le site de destination.');
            }
            if ($destLocationId === $locationId) {
                throw new RuntimeException('Le site de destination doit être différent du site source.');
            }
            $this->assertLocation($destLocationId);
            $supplierId = null;
        } else {
            $destLocationId = null;
        }

        if ($type === 'entree' && $supplierId !== null && $supplierId > 0) {
            $this->assertSupplier($supplierId);
        } else {
            $supplierId = $type === 'entree' ? $supplierId : null;
        }

        $motif = trim($motif);
        if ($motif === '') {
            $motif = $this->defaultMotif($type);
        }
        $motif = mb_substr($motif, 0, 40);

        $reference = $this->nullableText($reference, 100);
        $notes = $this->nullableText($notes, 2000);

        $normalized = $this->normalizeItems($type, $items);
        if ($normalized === []) {
            throw new RuntimeException('Ajoutez au moins une ligne de produit avec une quantité.');
        }

        $this->pdo->beginTransaction();
        try {
            $numero = $this->nextNumber($type, $docDate);
            $stmt = $this->pdo->prepare(
                'INSERT INTO stock_documents
                    (type, motif, numero, doc_date, status, location_id, dest_location_id, supplier_id,
                     reference, notes, total_cost, total_sale, created_by)
                 VALUES
                    (:type, :motif, :numero, :doc_date, :status, :location_id, :dest_location_id, :supplier_id,
                     :reference, :notes, 0, 0, :created_by)'
            );
            $stmt->execute([
                'type'             => $type,
                'motif'            => $motif,
                'numero'           => $numero,
                'doc_date'         => $docDate,
                'status'           => 'brouillon',
                'location_id'      => $locationId,
                'dest_location_id' => $destLocationId,
                'supplier_id'      => $supplierId !== null && $supplierId > 0 ? $supplierId : null,
                'reference'        => $reference,
                'notes'            => $notes,
                'created_by'       => $userId,
            ]);

            $documentId = (int) $this->pdo->lastInsertId();
            $lineStmt = $this->pdo->prepare(
                'INSERT INTO stock_document_lines (document_id, product_id, qty, unit_cost, unit_price)
                 VALUES (:document_id, :product_id, :qty, :unit_cost, :unit_price)'
            );

            $totalCost = 0.0;
            $totalSale = 0.0;
            foreach ($normalized as $item) {
                $lineStmt->execute([
                    'document_id' => $documentId,
                    'product_id'  => $item['product_id'],
                    'qty'         => $item['qty'],
                    'unit_cost'   => $item['unit_cost'],
                    'unit_price'  => $item['unit_price'],
                ]);
                $totalCost += $item['qty'] * $item['unit_cost'];
                $totalSale += $item['qty'] * $item['unit_price'];
            }

            $this->pdo->prepare(
                'UPDATE stock_documents SET total_cost = :total_cost, total_sale = :total_sale WHERE id = :id'
            )->execute([
                'total_cost' => round($totalCost, 2),
                'total_sale' => round($totalSale, 2),
                'id'         => $documentId,
            ]);

            $this->audit($userId, 'document.create', 'stock_document', $documentId, [
                'type'   => $type,
                'numero' => $numero,
            ]);

            $this->pdo->commit();
            return $documentId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function validateDocument(int $documentId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $doc = $this->lockDocument($documentId);
            if ($doc['status'] !== 'brouillon') {
                throw new RuntimeException('Seul un brouillon peut être validé.');
            }

            if ($doc['type'] === 'transfert') {
                throw new RuntimeException('Utilisez l’action « Expédier » pour un transfert.');
            }
            if (!in_array($doc['type'], ['entree', 'sortie', 'ouverture'], true)) {
                throw new RuntimeException('Ce type de document ne peut pas être validé ici.');
            }

            $lines = $this->documentLines($documentId);
            if ($lines === []) {
                throw new RuntimeException('Le document ne contient aucune ligne.');
            }

            if ($doc['type'] === 'entree' || $doc['type'] === 'ouverture') {
                $this->applyEntry($doc, $lines, $userId);
            } else {
                $this->applyExit($doc, $lines, $userId);
            }

            $this->pdo->prepare(
                'UPDATE stock_documents SET status = :status, validated_by = :user, validated_at = NOW(), updated_at = NOW()
                 WHERE id = :id'
            )->execute(['status' => 'valide', 'user' => $userId, 'id' => $documentId]);

            $this->audit($userId, 'document.validate', 'stock_document', $documentId, [
                'numero' => $doc['numero'],
                'type'   => $doc['type'],
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function shipTransfer(int $documentId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $doc = $this->lockDocument($documentId);
            if ($doc['type'] !== 'transfert') {
                throw new RuntimeException('Ce document n’est pas un transfert.');
            }
            if ($doc['status'] !== 'brouillon') {
                throw new RuntimeException('Seul un transfert brouillon peut être expédié.');
            }

            $lines = $this->documentLines($documentId);
            if ($lines === []) {
                throw new RuntimeException('Le transfert ne contient aucune ligne.');
            }

            $locationId = (int) $doc['location_id'];
            $totalCost = 0.0;
            foreach ($this->sortedLines($lines) as $line) {
                $product = $this->lockProduct((int) $line['product_id']);
                $level = $this->lockLevel((int) $line['product_id'], $locationId);
                $available = (float) $level['qty'];
                $qty = (float) $line['qty'];

                $this->assertAvailable($product, $available, $qty);
                $this->updateLevel((int) $line['product_id'], $locationId, $available - $qty);

                $unitCost = (float) $product['purchase_cost'];
                $this->pdo->prepare('UPDATE stock_document_lines SET unit_cost = :cost WHERE id = :id')
                    ->execute(['cost' => $unitCost, 'id' => (int) $line['id']]);

                $this->insertMovement(
                    documentId: $documentId,
                    documentLineId: (int) $line['id'],
                    productId: (int) $line['product_id'],
                    locationId: $locationId,
                    direction: 'out',
                    qty: $qty,
                    unitCost: $unitCost,
                    unitPrice: 0.0,
                    movementDate: $this->movementDate((string) $doc['doc_date']),
                    userId: $userId,
                );
                $totalCost += $qty * $unitCost;
            }

            $this->pdo->prepare(
                'UPDATE stock_documents SET status = :status, shipped_by = :user, shipped_at = NOW(),
                        total_cost = :total_cost, updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'status'     => 'expedie',
                'user'       => $userId,
                'total_cost' => round($totalCost, 2),
                'id'         => $documentId,
            ]);

            $this->audit($userId, 'transfer.ship', 'stock_document', $documentId, ['numero' => $doc['numero']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function receiveTransfer(int $documentId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $doc = $this->lockDocument($documentId);
            if ($doc['type'] !== 'transfert') {
                throw new RuntimeException('Ce document n’est pas un transfert.');
            }
            if ($doc['status'] !== 'expedie') {
                throw new RuntimeException('Seul un transfert expédié peut être réceptionné.');
            }

            $destId = $doc['dest_location_id'] !== null ? (int) $doc['dest_location_id'] : 0;
            if ($destId <= 0) {
                throw new RuntimeException('Aucun site de destination défini.');
            }

            $lines = $this->documentLines($documentId);
            foreach ($this->sortedLines($lines) as $line) {
                $product = $this->lockProduct((int) $line['product_id']);
                $level = $this->lockLevel((int) $line['product_id'], $destId);
                $qty = (float) $line['qty'];
                $this->updateLevel((int) $line['product_id'], $destId, (float) $level['qty'] + $qty);

                $this->insertMovement(
                    documentId: $documentId,
                    documentLineId: (int) $line['id'],
                    productId: (int) $line['product_id'],
                    locationId: $destId,
                    direction: 'in',
                    qty: $qty,
                    unitCost: (float) $line['unit_cost'],
                    unitPrice: 0.0,
                    movementDate: $this->movementDate((string) $doc['doc_date']),
                    userId: $userId,
                );
            }

            $this->pdo->prepare(
                'UPDATE stock_documents SET status = :status, received_by = :user, received_at = NOW(), updated_at = NOW()
                 WHERE id = :id'
            )->execute(['status' => 'recu', 'user' => $userId, 'id' => $documentId]);

            $this->audit($userId, 'transfer.receive', 'stock_document', $documentId, ['numero' => $doc['numero']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancelDocument(int $documentId, int $userId, string $reason = ''): void
    {
        $this->pdo->beginTransaction();
        try {
            $doc = $this->lockDocument($documentId);
            if ($doc['status'] !== 'brouillon') {
                throw new RuntimeException('Seul un brouillon peut être annulé.');
            }

            $this->pdo->prepare(
                'UPDATE stock_documents SET status = :status, cancel_reason = :reason, updated_at = NOW() WHERE id = :id'
            )->execute([
                'status' => 'annule',
                'reason' => $this->nullableText($reason, 255),
                'id'     => $documentId,
            ]);

            $this->audit($userId, 'document.cancel', 'stock_document', $documentId, ['numero' => $doc['numero']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function createInventory(int $locationId, ?string $notes, int $userId): int
    {
        $this->assertLocation($locationId);
        $notes = $this->nullableText($notes, 2000);

        $this->pdo->beginTransaction();
        try {
            $numero = $this->nextNumber('inventaire', date('Y-m-d'));
            $this->pdo->prepare(
                'INSERT INTO inventory_sessions (numero, location_id, status, started_by, started_at, notes)
                 VALUES (:numero, :location_id, :status, :started_by, NOW(), :notes)'
            )->execute([
                'numero'      => $numero,
                'location_id' => $locationId,
                'status'      => 'ouvert',
                'started_by'  => $userId,
                'notes'       => $notes,
            ]);
            $sessionId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare(
                'INSERT INTO inventory_lines (session_id, product_id, qty_theoretical, unit_cost)
                 SELECT :session_id, p.id, COALESCE(sl.qty, 0), p.purchase_cost
                 FROM products p
                 LEFT JOIN stock_levels sl ON sl.product_id = p.id AND sl.location_id = :location_id
                 WHERE p.active = 1'
            )->execute(['session_id' => $sessionId, 'location_id' => $locationId]);

            $this->audit($userId, 'inventory.create', 'inventory_session', $sessionId, [
                'numero'      => $numero,
                'location_id' => $locationId,
            ]);

            $this->pdo->commit();
            return $sessionId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string|int, mixed> $counts */
    public function saveInventoryCounts(int $sessionId, array $counts, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $session = $this->lockInventorySession($sessionId);
            if (!in_array($session['status'], ['ouvert', 'comptage'], true)) {
                throw new RuntimeException('Cet inventaire n’est plus modifiable.');
            }

            $stmt = $this->pdo->prepare('SELECT id, qty_theoretical FROM inventory_lines WHERE session_id = :id');
            $stmt->execute(['id' => $sessionId]);
            $lines = $stmt->fetchAll();

            $update = $this->pdo->prepare(
                'UPDATE inventory_lines SET qty_counted = :counted, gap = :gap WHERE id = :id AND session_id = :session_id'
            );

            foreach ($lines as $line) {
                $lineId = (int) $line['id'];
                $key = (string) $lineId;
                if (!array_key_exists($key, $counts)) {
                    continue;
                }

                $raw = trim((string) $counts[$key]);
                if ($raw === '') {
                    $update->execute([
                        'counted'    => null,
                        'gap'        => null,
                        'id'         => $lineId,
                        'session_id' => $sessionId,
                    ]);
                    continue;
                }

                $counted = $this->parseNumber($raw);
                if ($counted < 0) {
                    throw new RuntimeException('Une quantité comptée ne peut pas être négative.');
                }
                $counted = round($counted, 3);
                $gap = round($counted - (float) $line['qty_theoretical'], 3);
                $update->execute([
                    'counted'    => $counted,
                    'gap'        => $gap,
                    'id'         => $lineId,
                    'session_id' => $sessionId,
                ]);
            }

            $this->pdo->prepare('UPDATE inventory_sessions SET status = :status WHERE id = :id')
                ->execute(['status' => 'comptage', 'id' => $sessionId]);

            $this->audit($userId, 'inventory.save', 'inventory_session', $sessionId, ['numero' => $session['numero']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function validateInventory(int $sessionId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $session = $this->lockInventorySession($sessionId);
            if (!in_array($session['status'], ['ouvert', 'comptage'], true)) {
                throw new RuntimeException('Cet inventaire n’est plus modifiable.');
            }

            $stmt = $this->pdo->prepare(
                'SELECT il.id, il.product_id, il.qty_theoretical, il.qty_counted, il.gap,
                        p.designation, p.purchase_cost
                 FROM inventory_lines il
                 INNER JOIN products p ON p.id = il.product_id
                 WHERE il.session_id = :id AND il.qty_counted IS NOT NULL
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $sessionId]);
            $lines = $stmt->fetchAll();

            if ($lines === []) {
                throw new RuntimeException('Enregistrez au moins une quantité comptée avant de valider.');
            }

            $updateLine = $this->pdo->prepare('UPDATE inventory_lines SET gap = :gap WHERE id = :id');
            $locationId = (int) $session['location_id'];

            foreach ($this->sortedLines($lines) as $line) {
                $counted = (float) $line['qty_counted'];
                $gap = round($counted - (float) $line['qty_theoretical'], 3);
                $updateLine->execute(['gap' => $gap, 'id' => (int) $line['id']]);

                if (abs($gap) < 0.0005) {
                    continue;
                }

                $productId = (int) $line['product_id'];
                $product = $this->lockProduct($productId);
                $level = $this->lockLevel($productId, $locationId);
                $this->updateLevel($productId, $locationId, $counted);

                $this->insertMovement(
                    documentId: null,
                    documentLineId: null,
                    productId: $productId,
                    locationId: $locationId,
                    direction: $gap > 0 ? 'in' : 'out',
                    qty: abs($gap),
                    unitCost: (float) $product['purchase_cost'],
                    unitPrice: 0.0,
                    movementDate: date('Y-m-d H:i:s'),
                    userId: $userId,
                    inventorySessionId: $sessionId,
                );
            }

            $this->pdo->prepare(
                'UPDATE inventory_sessions SET status = :status, validated_by = :user, validated_at = NOW() WHERE id = :id'
            )->execute(['status' => 'valide', 'user' => $userId, 'id' => $sessionId]);

            $this->audit($userId, 'inventory.validate', 'inventory_session', $sessionId, ['numero' => $session['numero']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancelInventory(int $sessionId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $session = $this->lockInventorySession($sessionId);
            if (!in_array($session['status'], ['ouvert', 'comptage'], true)) {
                throw new RuntimeException('Cet inventaire ne peut plus être annulé.');
            }

            $this->pdo->prepare('UPDATE inventory_sessions SET status = :status WHERE id = :id')
                ->execute(['status' => 'annule', 'id' => $sessionId]);

            $this->audit($userId, 'inventory.cancel', 'inventory_session', $sessionId, ['numero' => $session['numero']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<int, array<string, mixed>> $items
     *  @return array<int, array{product_id: int, qty: float, unit_cost: float, unit_price: float}>
     */
    private function normalizeItems(string $type, array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $qty = $this->parseNumber($item['qty'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if ($qty < 0) {
                throw new RuntimeException('Une quantité ne peut pas être négative.');
            }
            if ($qty <= 0) {
                continue;
            }

            $product = $this->productRow($productId);
            if ($product === null) {
                throw new RuntimeException('Un produit sélectionné est introuvable.');
            }

            $rawCost = trim((string) ($item['unit_cost'] ?? ''));
            $rawPrice = trim((string) ($item['unit_price'] ?? ''));

            if ($type === 'entree') {
                $unitCost = $rawCost === '' ? (float) $product['purchase_cost'] : $this->parseNumber($rawCost);
                $unitPrice = $rawPrice === '' ? (float) $product['sale_price'] : $this->parseNumber($rawPrice);
            } elseif ($type === 'sortie') {
                $unitCost = (float) $product['purchase_cost'];
                $unitPrice = $rawPrice === '' ? (float) $product['sale_price'] : $this->parseNumber($rawPrice);
            } else {
                $unitCost = (float) $product['purchase_cost'];
                $unitPrice = 0.0;
            }

            if ($unitCost < 0 || $unitPrice < 0) {
                throw new RuntimeException('Les prix ne peuvent pas être négatifs.');
            }

            $normalized[] = [
                'product_id' => $productId,
                'qty'        => round($qty, 3),
                'unit_cost'  => round($unitCost, 2),
                'unit_price' => round($unitPrice, 2),
            ];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $doc
     *  @param array<int, array<string, mixed>> $lines
     */
    private function applyEntry(array $doc, array $lines, int $userId): void
    {
        $locationId = (int) $doc['location_id'];
        $movementDate = $this->movementDate((string) $doc['doc_date']);

        foreach ($this->sortedLines($lines) as $line) {
            $productId = (int) $line['product_id'];
            $product = $this->lockProduct($productId);
            $level = $this->lockLevel($productId, $locationId);

            $locationQty = (float) $level['qty'];
            $globalQty = $this->globalQuantity($productId);
            $oldCost = (float) $product['purchase_cost'];
            $qty = (float) $line['qty'];
            $lineCost = (float) $line['unit_cost'];

            $newGlobalQty = $globalQty + $qty;
            $newCost = $newGlobalQty > 0
                ? (($globalQty * $oldCost) + ($qty * $lineCost)) / $newGlobalQty
                : $lineCost;

            $this->pdo->prepare('UPDATE products SET purchase_cost = :cost, updated_at = NOW() WHERE id = :id')
                ->execute(['cost' => round($newCost, 2), 'id' => $productId]);
            $this->updateLevel($productId, $locationId, $locationQty + $qty);

            $this->insertMovement(
                documentId: (int) $doc['id'],
                documentLineId: (int) $line['id'],
                productId: $productId,
                locationId: $locationId,
                direction: 'in',
                qty: $qty,
                unitCost: $lineCost,
                unitPrice: (float) $line['unit_price'],
                movementDate: $movementDate,
                userId: $userId,
            );
        }
    }

    /** @param array<string, mixed> $doc
     *  @param array<int, array<string, mixed>> $lines
     */
    private function applyExit(array $doc, array $lines, int $userId): void
    {
        $locationId = (int) $doc['location_id'];
        $movementDate = $this->movementDate((string) $doc['doc_date']);
        $allowNegative = $this->settings->get('allow_negative_stock', '0') === '1';
        $totalCost = 0.0;

        foreach ($this->sortedLines($lines) as $line) {
            $productId = (int) $line['product_id'];
            $product = $this->lockProduct($productId);
            $level = $this->lockLevel($productId, $locationId);
            $available = (float) $level['qty'];
            $qty = (float) $line['qty'];

            if (!$allowNegative) {
                $this->assertAvailable($product, $available, $qty);
            }

            $unitCost = (float) $product['purchase_cost'];
            $this->pdo->prepare('UPDATE stock_document_lines SET unit_cost = :cost WHERE id = :id')
                ->execute(['cost' => $unitCost, 'id' => (int) $line['id']]);
            $this->updateLevel($productId, $locationId, $available - $qty);

            $this->insertMovement(
                documentId: (int) $doc['id'],
                documentLineId: (int) $line['id'],
                productId: $productId,
                locationId: $locationId,
                direction: 'out',
                qty: $qty,
                unitCost: $unitCost,
                unitPrice: (float) $line['unit_price'],
                movementDate: $movementDate,
                userId: $userId,
            );

            $totalCost += $qty * $unitCost;
        }

        $this->pdo->prepare('UPDATE stock_documents SET total_cost = :total_cost, updated_at = NOW() WHERE id = :id')
            ->execute(['total_cost' => round($totalCost, 2), 'id' => (int) $doc['id']]);
    }

    private function nextNumber(string $type, string $docDate): string
    {
        $prefix = self::PREFIXES[$type] ?? strtoupper(substr($type, 0, 3));
        $year = (int) substr($docDate, 0, 4);
        if ($year < 2000) {
            $year = (int) date('Y');
        }

        $this->pdo->prepare(
            'INSERT INTO document_sequences (type, year, last_number) VALUES (:type, :year, 1)
             ON DUPLICATE KEY UPDATE last_number = last_number + 1'
        )->execute(['type' => $type, 'year' => $year]);

        $stmt = $this->pdo->prepare('SELECT last_number FROM document_sequences WHERE type = :type AND year = :year');
        $stmt->execute(['type' => $type, 'year' => $year]);
        $number = (int) $stmt->fetchColumn();

        return sprintf('%s-%d-%04d', $prefix, $year, $number);
    }

    /** @return array<string, mixed> */
    private function lockDocument(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stock_documents WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('Document introuvable.');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function lockInventorySession(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_sessions WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('Inventaire introuvable.');
        }

        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    private function documentLines(int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, product_id, qty, unit_cost, unit_price FROM stock_document_lines
             WHERE document_id = :id ORDER BY product_id ASC'
        );
        $stmt->execute(['id' => $documentId]);

        return $stmt->fetchAll();
    }

    /** @param array<int, array<string, mixed>> $lines
     *  @return array<int, array<string, mixed>>
     */
    private function sortedLines(array $lines): array
    {
        usort($lines, static fn (array $a, array $b): int => ((int) $a['product_id']) <=> ((int) $b['product_id']));
        return $lines;
    }

    /** @return array<string, mixed>|null */
    private function productRow(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, designation, purchase_cost, sale_price FROM products WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed> */
    private function lockProduct(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, designation, purchase_cost FROM products WHERE id = :id FOR UPDATE'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('Produit introuvable.');
        }

        return $row;
    }

    /** @return array{qty: float} */
    private function lockLevel(int $productId, int $locationId): array
    {
        $this->pdo->prepare(
            'INSERT INTO stock_levels (product_id, location_id, qty, updated_at) VALUES (:product_id, :location_id, 0, NOW())
             ON DUPLICATE KEY UPDATE qty = qty'
        )->execute(['product_id' => $productId, 'location_id' => $locationId]);

        $stmt = $this->pdo->prepare(
            'SELECT qty FROM stock_levels WHERE product_id = :product_id AND location_id = :location_id FOR UPDATE'
        );
        $stmt->execute(['product_id' => $productId, 'location_id' => $locationId]);
        $row = $stmt->fetch();

        return ['qty' => (float) ($row['qty'] ?? 0)];
    }

    private function globalQuantity(int $productId): float
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(qty), 0) FROM stock_levels WHERE product_id = :product_id');
        $stmt->execute(['product_id' => $productId]);

        return (float) $stmt->fetchColumn();
    }

    private function updateLevel(int $productId, int $locationId, float $qty): void
    {
        $this->pdo->prepare(
            'UPDATE stock_levels SET qty = :qty, updated_at = NOW() WHERE product_id = :product_id AND location_id = :location_id'
        )->execute([
            'qty'         => round($qty, 3),
            'product_id'  => $productId,
            'location_id' => $locationId,
        ]);
    }

    /** @param array<string, mixed> $product */
    private function assertAvailable(array $product, float $available, float $requested): void
    {
        if ($requested > $available + 0.0001) {
            throw new RuntimeException(
                'Stock insuffisant pour « ' . (string) $product['designation'] . ' » : disponible '
                . qty($available) . ', demandé ' . qty($requested) . '.'
            );
        }
    }

    private function insertMovement(
        ?int $documentId,
        ?int $documentLineId,
        int $productId,
        int $locationId,
        string $direction,
        float $qty,
        float $unitCost,
        float $unitPrice,
        string $movementDate,
        int $userId,
        ?int $inventorySessionId = null,
    ): void {
        $this->pdo->prepare(
            'INSERT INTO stock_movements
                (document_id, document_line_id, product_id, location_id, direction, qty, unit_cost, unit_price,
                 movement_date, inventory_session_id, created_by)
             VALUES
                (:document_id, :document_line_id, :product_id, :location_id, :direction, :qty, :unit_cost, :unit_price,
                 :movement_date, :inventory_session_id, :created_by)'
        )->execute([
            'document_id'          => $documentId,
            'document_line_id'     => $documentLineId,
            'product_id'           => $productId,
            'location_id'          => $locationId,
            'direction'            => $direction,
            'qty'                  => round($qty, 3),
            'unit_cost'            => round($unitCost, 2),
            'unit_price'           => round($unitPrice, 2),
            'movement_date'        => $movementDate,
            'inventory_session_id' => $inventorySessionId,
            'created_by'           => $userId,
        ]);
    }

    private function assertLocation(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM locations WHERE id = :id AND active = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Sélectionnez un site actif.');
        }
    }

    private function assertSupplier(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM suppliers WHERE id = :id AND active = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Fournisseur introuvable ou inactif.');
        }
    }

    private function validDate(string $date): string
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new RuntimeException('Date du document invalide.');
        }

        return $date;
    }

    private function movementDate(string $docDate): string
    {
        $timestamp = strtotime($docDate . ' ' . date('H:i:s'));
        return date('Y-m-d H:i:s', $timestamp === false ? time() : $timestamp);
    }

    private function parseNumber(mixed $value): float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $raw);
        if (!is_numeric($raw)) {
            throw new RuntimeException('Valeur numérique invalide : « ' . $raw . ' ».');
        }

        return (float) $raw;
    }

    private function nullableText(?string $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function defaultMotif(string $type): string
    {
        return match ($type) {
            'entree' => 'achat',
            'sortie' => 'vente',
            'transfert' => 'reapprovisionnement',
            default => 'operation',
        };
    }

    /** @param array<string, mixed> $newValues */
    private function audit(int $userId, string $action, string $entityType, int $entityId, array $newValues): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip, user_agent, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :new_values, :ip, :user_agent, NOW())'
        );
        $stmt->execute([
            'user_id'     => $userId > 0 ? $userId : null,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'new_values'  => json_encode($newValues, JSON_UNESCAPED_UNICODE),
            'ip'          => (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }
}

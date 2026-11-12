<?php
declare(strict_types=1);

/**
 * Rétro-remplissage du grand livre des mouvements de stock (stock_movements).
 *
 *   php database/backfill_stock_movements.php
 *
 * L'import de l'ancienne application a créé les documents d'entrée
 * (stock_documents de type « entree ») et leurs lignes, ainsi que les niveaux
 * de stock (stock_levels), mais pas les lignes du grand livre. Ce script
 * reconstitue les mouvements « in » à partir des entrées déjà enregistrées,
 * sans toucher aux niveaux de stock (qui restent la source de vérité du stock
 * net).
 *
 * - Idempotent : relançable sans risque (les documents déjà couverts sont
 *   ignorés grâce à une clé d'unicité sur document_line_id).
 * - Transactionnel : tout ou rien.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ce script est accessible uniquement en ligne de commande.');
}

define('GOS_ROOT', dirname(__DIR__));
$GLOBALS['GOS_CONFIG'] = require GOS_ROOT . '/shared/config.php';
define('GOS_BASE', (string) ($GLOBALS['GOS_CONFIG']['app']['base'] ?? ''));
define('GOS_APP_NAME', (string) ($GLOBALS['GOS_CONFIG']['app']['name'] ?? 'Gestion Our Stock'));
define('GOS_VERSION', (string) ($GLOBALS['GOS_CONFIG']['app']['version'] ?? '1.0'));

require GOS_ROOT . '/shared/helpers.php';
require GOS_ROOT . '/shared/db.php';

$pdo = core_db();

echo "=== Gestion Our Stock — rétro-remplissage des mouvements de stock ===\n";

// La colonne document_line_id référence une ligne. Pour éviter de dupliquer,
// on crée un index unique (idempotent) s'il n'existe pas encore.
$idx = $pdo->query("SHOW INDEX FROM stock_movements WHERE Key_name = 'uq_movements_line'")->fetch();
if ($idx === false) {
    // Supprime d'abord d'éventuels doublons existants, puis pose l'unicité.
    $pdo->exec(
        'DELETE m1 FROM stock_movements m1
         INNER JOIN stock_movements m2
           ON m2.document_line_id = m1.document_line_id
          AND m2.id < m1.id'
    );
    $pdo->exec(
        'ALTER TABLE stock_movements
         ADD UNIQUE KEY uq_movements_line (document_line_id)'
    );
    echo "[ OK ] Index unique uq_movements_line ajouté\n";
} else {
    echo "[ -- ] Index unique uq_movements_line déjà présent\n";
}

// Documents d'entrée réceptionnés qui ne sont pas encore couverts.
$docs = $pdo->query(
    "SELECT d.id
       FROM stock_documents d
      WHERE d.type = 'entree'
        AND d.status = 'recu'
        AND NOT EXISTS (
            SELECT 1 FROM stock_movements m WHERE m.document_id = d.id
        )
      ORDER BY d.id"
)->fetchAll(PDO::FETCH_COLUMN);

$covered = 0;
$movements = 0;
$missingProduct = 0;

try {
    $pdo->beginTransaction();

    $linesStmt = $pdo->prepare(
        'SELECT id, product_id, qty, unit_cost, unit_price
           FROM stock_document_lines
          WHERE document_id = ?
          ORDER BY id'
    );
    $insertStmt = $pdo->prepare(
        'INSERT INTO stock_movements
            (document_id, document_line_id, product_id, location_id, direction, qty,
             unit_cost, unit_price, movement_date, inventory_session_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)'
    );
    $docStmt = $pdo->prepare(
        'SELECT location_id, COALESCE(received_at, validated_at, created_at) AS mdate,
                COALESCE(received_by, validated_by, created_by) AS cby
           FROM stock_documents
          WHERE id = ?'
    );

    foreach ($docs as $docId) {
        $docId = (int) $docId;
        $docStmt->execute([$docId]);
        $doc = $docStmt->fetch();

        if ($doc === false) {
            continue;
        }

        $movementDate = (string) $doc['mdate'];
        if ($movementDate === '' || strtotime($movementDate) === false) {
            $movementDate = date('Y-m-d H:i:s');
        } else {
            $movementDate = date('Y-m-d H:i:s', strtotime($movementDate));
        }
        $createdBy = (int) ($doc['cby'] ?? 1) > 0 ? (int) $doc['cby'] : 1;
        $locationId = (int) $doc['location_id'];

        $linesStmt->execute([$docId]);
        $docMoved = 0;

        foreach ($linesStmt->fetchAll() as $line) {
            $lineId = (int) $line['id'];
            // Ignore les lignes déjà couvertes (sécurité supplémentaire).
            $already = $pdo->prepare('SELECT 1 FROM stock_movements WHERE document_line_id = ? LIMIT 1');
            $already->execute([$lineId]);
            if ($already->fetchColumn() !== false) {
                continue;
            }

            $insertStmt->execute([
                $docId,
                $lineId,
                (int) $line['product_id'],
                $locationId,
                'in',
                round((float) $line['qty'], 3),
                round((float) $line['unit_cost'], 2),
                round((float) $line['unit_price'], 2),
                $movementDate,
                $createdBy,
            ]);
            $movements++;
            $docMoved++;
        }

        if ($docMoved > 0) {
            $covered++;
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}

echo sprintf(
    "Documents couverts : %d · Mouvements créés : %d · Lignes sans produit : %d\n",
    $covered,
    $movements,
    $missingProduct
);

$total = (int) $pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
echo "Total stock_movements : {$total}\n";
echo "=== Rétro-remplissage terminé ===\n";

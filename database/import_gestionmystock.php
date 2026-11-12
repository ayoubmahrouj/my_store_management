<?php
declare(strict_types=1);

/**
 * Import des données de l'ancienne application « gestionmystock ».
 *
 *   php database/import_gestionmystock.php
 *   php database/import_gestionmystock.php --db=gestionmystock --force
 *   php database/import_gestionmystock.php --no-purchases
 *
 * Ce script est idempotent : la table import_map mémorise les correspondances
 * entre les identifiants legacy et les identifiants de la base unifiée.
 * Il peut donc être relancé sans créer de doublons.
 *
 * Ce qui est importé :
 *   - familles / sous-familles -> catégories
 *   - marques                  -> marques
 *   - unités                   -> unités
 *   - produits + quantité      -> produits + stock_levels
 *   - clients                  -> clients (dédupliqués)
 *   - fournisseurs             -> fournisseurs
 *   - ventes + lignes          -> sales + sale_items
 *   - soldes non réglés        -> credits
 *   - paiements clients        -> payments + payment_allocations
 *   - achats                   -> stock_documents (entrée, historique)
 *   - clients credit_project   -> clients (si la base existe)
 *   - catalogue stock          -> marques / catégories / produits (si la base existe)
 *
 * Les paiements fournisseurs ne sont pas importés : l'application unifiée
 * n'a pas encore d'écran « achats fournisseurs ». Les achats restent visibles
 * dans l'historique des entrées de stock.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Import accessible uniquement en ligne de commande.\n");
}

require dirname(__DIR__) . '/shared/bootstrap.php';

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--db=')) {
        $options['db'] = substr($arg, 5);
    } elseif ($arg === '--force') {
        $options['force'] = true;
    } elseif ($arg === '--no-purchases') {
        $options['purchases'] = false;
    } elseif ($arg === '--dry-run') {
        $options['dry_run'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php database/import_gestionmystock.php [--db=NAME] [--force] [--no-purchases] [--dry-run]\n";
        exit(0);
    } else {
        fwrite(STDERR, "Option inconnue : {$arg}\n");
        exit(1);
    }
}

$sourceDb = (string) ($options['db'] ?? getenv('LEGACY_MYSTOCK_DB') ?: 'gestionmystock');
$force = !empty($options['force']);
$withPurchases = $options['purchases'] ?? true;
$dryRun = !empty($options['dry_run']);

if (!preg_match('/^[A-Za-z0-9_]+$/', $sourceDb)) {
    fwrite(STDERR, "Nom de base legacy invalide.\n");
    exit(1);
}

$pdo = core_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out(string $message): void
{
    fwrite(STDOUT, $message . "\n");
}

function fail(string $message): never
{
    fwrite(STDERR, "ERREUR : {$message}\n");
    exit(1);
}

function source_exists(PDO $pdo, string $db): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
    $stmt->execute([$db]);
    return (bool) $stmt->fetchColumn();
}

function source_table_exists(PDO $pdo, string $db, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->execute([$db, $table]);
    return (bool) $stmt->fetchColumn();
}

function source_rows(PDO $pdo, string $db, string $table, string $orderBy = 'ID'): array
{
    return $pdo->query("SELECT * FROM `{$db}`.`{$table}` ORDER BY `{$orderBy}`")->fetchAll(PDO::FETCH_ASSOC);
}

function source_count(PDO $pdo, string $db, string $table): int
{
    if (!source_table_exists($pdo, $db, $table)) {
        return 0;
    }
    return (int) $pdo->query("SELECT COUNT(*) FROM `{$db}`.`{$table}`")->fetchColumn();
}

function clean_text(mixed $value, ?int $max = null): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    if ($max !== null && mb_strlen($text) > $max) {
        $text = mb_substr($text, 0, $max);
    }
    return $text;
}

function import_date(mixed $value): string
{
    $date = substr(trim((string) $value), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
}

function money(mixed $value): float
{
    return round((float) $value, 2);
}

function map_get(PDO $pdo, string $source, string $entity, string $legacyId, string $table): ?int
{
    $stmt = $pdo->prepare('SELECT target_id FROM import_map WHERE source = ? AND entity = ? AND legacy_id = ?');
    $stmt->execute([$source, $entity, (string) $legacyId]);
    $target = $stmt->fetchColumn();
    if ($target === false) {
        return null;
    }
    $target = (int) $target;
    $check = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE id = ?");
    $check->execute([$target]);
    if (!$check->fetchColumn()) {
        $delete = $pdo->prepare('DELETE FROM import_map WHERE source = ? AND entity = ? AND legacy_id = ?');
        $delete->execute([$source, $entity, (string) $legacyId]);
        return null;
    }
    return $target;
}

function map_set(PDO $pdo, string $source, string $entity, string $legacyId, int $targetId): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO import_map (source, entity, legacy_id, target_id)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE target_id = VALUES(target_id)'
    );
    $stmt->execute([$source, $entity, (string) $legacyId, $targetId]);
}

function unique_code(string $base, array &$used, int $max, string $fallback): string
{
    $base = clean_text($base, $max);
    if ($base === '') {
        $base = clean_text($fallback, $max);
    }
    $candidate = $base;
    $i = 1;
    while (isset($used[mb_strtolower($candidate)])) {
        $suffix = '-' . $i;
        $candidate = mb_substr($base, 0, max(1, $max - mb_strlen($suffix))) . $suffix;
        $i++;
        if ($i > 50000) {
            $candidate = mb_substr($base, 0, max(1, $max - 8)) . '-' . bin2hex(random_bytes(3));
            break;
        }
    }
    $used[mb_strtolower($candidate)] = true;
    return $candidate;
}

function load_used_values(PDO $pdo, string $sql): array
{
    $used = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $used[mb_strtolower($value)] = true;
        }
    }
    return $used;
}

function catalog_id(PDO $pdo, string $table, string $name, int $max = 120, string $fallback = 'Général'): int
{
    $name = clean_text($name, $max);
    if ($name === '') {
        $name = $fallback;
    }
    $stmt = $pdo->prepare("INSERT INTO `{$table}` (`name`) VALUES (?) ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)");
    $stmt->execute([$name]);
    return (int) $pdo->lastInsertId();
}

function unit_id(PDO $pdo, string $label, string $codeSeed): int
{
    $label = clean_text($label, 60);
    if ($label === '') {
        $label = 'Unité';
    }
    $find = $pdo->prepare('SELECT id FROM units WHERE label = ? LIMIT 1');
    $find->execute([$label]);
    $existing = $find->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $code = clean_text('U' . $codeSeed, 20);
    if ($code === 'U') {
        $code = 'U' . random_int(100, 999);
    }
    $insert = $pdo->prepare('INSERT INTO units (code, label) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $insert->execute([$code, $label]);
    return (int) $pdo->lastInsertId();
}

/* -------------------------------------------------------------------------
| Vérifications
|-------------------------------------------------------------------------*/

if (!source_exists($pdo, $sourceDb)) {
    fail("la base legacy « {$sourceDb} » est introuvable.");
}
if (!source_table_exists($pdo, $sourceDb, 'produit') || !source_table_exists($pdo, $sourceDb, 'vente')) {
    fail("la base « {$sourceDb} » ne ressemble pas à gestionmystock (tables produit / vente manquantes).");
}

$adminId = (int) $pdo->query(
    "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' ORDER BY u.id LIMIT 1"
)->fetchColumn();
if ($adminId <= 0) {
    $adminId = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
}
if ($adminId <= 0) {
    fail('aucun utilisateur trouvé dans la base unifiée.');
}

$defaultLocationId = (int) $pdo->query('SELECT id FROM locations ORDER BY id LIMIT 1')->fetchColumn();
if ($defaultLocationId <= 0) {
    fail('aucun emplacement (location) trouvé.');
}

$counts = [
    'clients' => 0,
    'clients_liés' => 0,
    'fournisseurs' => 0,
    'produits' => 0,
    'ventes' => 0,
    'lignes_vente' => 0,
    'paiements' => 0,
    'crédits' => 0,
    'achats' => 0,
    'lignes_achat' => 0,
    'clients_credit_project' => 0,
];

if ($dryRun) {
    out('=== Simulation (aucune écriture) ===');
    out('Base legacy : ' . $sourceDb);
    foreach (['produit', 'client', 'fournisseur', 'vente', 'venteitem', 'paiementclient', 'achat', 'achatitem'] as $table) {
        out(sprintf('  %-16s %6d lignes', $table, source_count($pdo, $sourceDb, $table)));
    }
    out('Achats : ' . ($withPurchases ? 'oui' : 'non'));
    exit(0);
}

out('=== Import gestionmystock → gestion_our_stock ===');
out('Base legacy : ' . $sourceDb);

/* -------------------------------------------------------------------------
| Nettoyage optionnel
|-------------------------------------------------------------------------*/

if ($force) {
    out('Nettoyage des ventes / paiements / crédits importés…');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['payment_allocations', 'payments', 'credits', 'sale_items', 'sales'] as $table) {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $del = $pdo->prepare('DELETE FROM import_map WHERE source = ? AND entity IN (?, ?, ?, ?, ?, ?)');
    $del->execute([$sourceDb, 'sale', 'sale_item', 'sale_items_done', 'payment', 'credit', 'purchase']);

    if (source_table_exists($pdo, $sourceDb, 'achat')) {
        $docIds = $pdo->query("SELECT id FROM stock_documents WHERE notes LIKE 'Import gestionmystock achat %'")->fetchAll(PDO::FETCH_COLUMN);
        if ($docIds) {
            $in = implode(',', array_map('intval', $docIds));
            // Les mouvements référencent les lignes/documents : on les supprime d'abord.
            $pdo->exec("DELETE FROM stock_movements WHERE document_id IN ({$in})");
            $pdo->exec("DELETE FROM stock_document_lines WHERE document_id IN ({$in})");
            $pdo->exec("DELETE FROM stock_documents WHERE id IN ({$in})");
        }
    }
}

/* -------------------------------------------------------------------------
| Catégories, marques, unités
|-------------------------------------------------------------------------*/

$categoryMap = [];
$brandMap = [];
$brandNameById = [];
$unitMap = [];

foreach (source_rows($pdo, $sourceDb, 'famille') as $row) {
    $categoryMap[(string) $row['ID']] = catalog_id($pdo, 'categories', (string) $row['LIBELLE'], 120, 'Général');
}
foreach (source_rows($pdo, $sourceDb, 'marqueproduit') as $row) {
    $brandMap[(string) $row['ID']] = catalog_id($pdo, 'brands', (string) $row['LIBELLE'], 120, 'Sans marque');
    $brandNameById[(string) $row['ID']] = clean_text($row['LIBELLE'], 120);
}
foreach (source_rows($pdo, $sourceDb, 'unitemesure') as $row) {
    $unitMap[(string) $row['ID']] = unit_id($pdo, (string) $row['LIBELLE'], (string) $row['ID']);
}
$defaultUnitId = unit_id($pdo, 'Unité', '0');
out(sprintf('Catégories : %d · Marques : %d · Unités : %d', count($categoryMap), count($brandMap), count($unitMap)));

/* -------------------------------------------------------------------------
| Clients
|-------------------------------------------------------------------------*/

$clientFindPhone = $pdo->prepare('SELECT id FROM clients WHERE name = ? AND phone = ? LIMIT 1');
$clientFindName = $pdo->prepare('SELECT id FROM clients WHERE name = ? LIMIT 1');
$clientInsert = $pdo->prepare(
    'INSERT INTO clients (name, phone, email, address, ice, type, credit_limit, is_active, is_passenger, notes, created_at)
     VALUES (?, ?, NULL, ?, ?, ?, 0, 1, ?, ?, NOW())'
);

$pdo->beginTransaction();
foreach (source_rows($pdo, $sourceDb, 'client') as $row) {
    $legacyId = (string) $row['ID'];
    if (map_get($pdo, $sourceDb, 'client', $legacyId, 'clients') !== null) {
        $counts['clients_liés']++;
        continue;
    }

    $name = clean_text($row['PRENOM'], 150);
    if ($name === '') {
        $name = 'Client #' . $legacyId;
    }
    $phone = clean_text($row['TEL'], 30);
    $address = clean_text($row['ADRESSE'], 255);
    $ice = clean_text($row['ICE'], 30);
    $type = $ice !== '' ? 'SOCIETE' : 'PERSONNE';
    $isPassenger = mb_stripos($name, 'PASSAGER') !== false ? 1 : 0;

    $notesParts = ['Import gestionmystock #' . $legacyId];
    if (clean_text($row['CIN'], 80) !== '') {
        $notesParts[] = 'CIN : ' . clean_text($row['CIN'], 80);
    }
    if (clean_text($row['DESCRIPTION'], 200) !== '') {
        $notesParts[] = clean_text($row['DESCRIPTION'], 200);
    }
    if ((float) $row['CREANCE'] !== 0.0) {
        $notesParts[] = 'Créance legacy : ' . number_format((float) $row['CREANCE'], 2, '.', ' ') . ' DH';
    }

    $existing = null;
    if ($phone !== '') {
        $clientFindPhone->execute([$name, $phone]);
        $existing = $clientFindPhone->fetchColumn();
    }
    if ($existing === false || $existing === null) {
        $clientFindName->execute([$name]);
        $existing = $clientFindName->fetchColumn();
    }

    if ($existing !== false && $existing !== null) {
        map_set($pdo, $sourceDb, 'client', $legacyId, (int) $existing);
        $counts['clients_liés']++;
        continue;
    }

    $clientInsert->execute([
        $name,
        $phone !== '' ? $phone : null,
        $address !== '' ? $address : null,
        $ice !== '' ? $ice : null,
        $type,
        $isPassenger,
        implode(' · ', $notesParts),
    ]);
    $newId = (int) $pdo->lastInsertId();
    map_set($pdo, $sourceDb, 'client', $legacyId, $newId);
    $counts['clients']++;
}
$pdo->commit();
out(sprintf('Clients : %d créés · %d déjà présents', $counts['clients'], $counts['clients_liés']));

/* -------------------------------------------------------------------------
| Fournisseurs
|-------------------------------------------------------------------------*/

$supplierInsert = $pdo->prepare(
    'INSERT INTO suppliers (name, ice, phone, email, address, active, created_at)
     VALUES (?, ?, ?, ?, ?, 1, NOW())
     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
);

$pdo->beginTransaction();
foreach (source_rows($pdo, $sourceDb, 'fournisseur') as $row) {
    $legacyId = (string) $row['ID'];
    if (map_get($pdo, $sourceDb, 'supplier', $legacyId, 'suppliers') !== null) {
        continue;
    }
    $name = clean_text($row['NOM'], 160);
    if ($name === '') {
        $name = 'Fournisseur #' . $legacyId;
    }
    $supplierInsert->execute([
        $name,
        clean_text($row['ICE'], 30) ?: null,
        clean_text($row['TEL'], 30) ?: null,
        clean_text($row['EMAIL'], 160) ?: null,
        clean_text($row['ADRESSE'], 255) ?: null,
    ]);
    $newId = (int) $pdo->lastInsertId();
    map_set($pdo, $sourceDb, 'supplier', $legacyId, $newId);
    $counts['fournisseurs']++;
}
$pdo->commit();
out(sprintf('Fournisseurs : %d', $counts['fournisseurs']));

/* -------------------------------------------------------------------------
| Produits + stock
|-------------------------------------------------------------------------*/

$usedSku = load_used_values($pdo, 'SELECT sku FROM products WHERE sku IS NOT NULL');
$usedBarcode = load_used_values($pdo, 'SELECT barcode FROM products WHERE barcode IS NOT NULL');

$productFind = $pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
$productInsert = $pdo->prepare(
    'INSERT INTO products
        (sku, barcode, designation, description, brand_id, category_id, unit_id, purchase_cost, sale_price, avg_sale_price, min_stock, active, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
);
$levelUpsert = $pdo->prepare(
    'INSERT INTO stock_levels (product_id, location_id, qty, updated_at) VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE qty = VALUES(qty), updated_at = NOW()'
);
$productBrandName = [];
$productDesignation = [];

$pdo->beginTransaction();
foreach (source_rows($pdo, $sourceDb, 'produit') as $row) {
    $legacyId = (string) $row['ID'];
    $qty = (float) $row['QTE'];
    $existingId = map_get($pdo, $sourceDb, 'product', $legacyId, 'products');

    if ($existingId !== null) {
        $levelUpsert->execute([$existingId, $defaultLocationId, $qty]);
        $productBrandName[$legacyId] = $brandNameById[(string) $row['MARQUEPRODUIT_ID']] ?? '';
        $productDesignation[$legacyId] = clean_text($row['DESIGNATION'], 180);
        continue;
    }

    $reference = clean_text($row['REFERENCE'], 40);
    $code = clean_text($row['CODE'], 60);
    $skuBase = $reference !== '' ? $reference : ($code !== '' ? $code : 'MYSTOCK-' . $legacyId);
    $sku = unique_code($skuBase, $usedSku, 40, 'MYSTOCK-' . $legacyId);

    $barcode = null;
    if ($code !== '' && mb_strlen($code) <= 60 && preg_match('/^[0-9]{8,14}$/', $code)
        && !isset($usedBarcode[mb_strtolower($code)])) {
        $barcode = $code;
        $usedBarcode[mb_strtolower($code)] = true;
    }

    $designation = clean_text($row['DESIGNATION'], 180);
    if ($designation === '') {
        $designation = clean_text($row['DESCRIPTION'], 180);
    }
    if ($designation === '') {
        $designation = 'Produit #' . $legacyId;
    }

    $description = trim((string) $row['DESCRIPTION']);
    if ($description === '') {
        $description = null;
    }

    $brandId = $brandMap[(string) $row['MARQUEPRODUIT_ID']] ?? null;
    $categoryId = $categoryMap[(string) $row['FAMILLE_ID']] ?? null;
    $unitId = $unitMap[(string) $row['UNITEMESURE_ID']] ?? $defaultUnitId;

    $purchaseCost = money($row['PRIXMOYENACHAT']);
    $salePrice = money($row['PRIXVENTEMAX']);
    if ($salePrice <= 0) {
        $salePrice = money($row['PRIXVENTEMIN']);
    }
    $avgSalePrice = money($row['PRIXVENTEMIN']);

    $productInsert->execute([
        $sku,
        $barcode,
        $designation,
        $description,
        $brandId,
        $categoryId,
        $unitId,
        $purchaseCost,
        $salePrice,
        $avgSalePrice,
        money($row['SEUILALERTE']),
    ]);
    $newId = (int) $pdo->lastInsertId();
    map_set($pdo, $sourceDb, 'product', $legacyId, $newId);
    $levelUpsert->execute([$newId, $defaultLocationId, $qty]);

    $productBrandName[$legacyId] = $brandNameById[(string) $row['MARQUEPRODUIT_ID']] ?? '';
    $productDesignation[$legacyId] = $designation;
    $counts['produits']++;
}
$pdo->commit();
out(sprintf('Produits : %d (stock initial importé)', $counts['produits']));

/* -------------------------------------------------------------------------
| Ventes + crédits
|-------------------------------------------------------------------------*/

$saleInsert = $pdo->prepare(
    'INSERT INTO sales (client_id, user_id, date, subtotal, discount, total, paid, payment_method, status, note, created_at)
     VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)'
);
$creditInsert = $pdo->prepare(
    'INSERT INTO credits (sale_id, client_id, amount, paid, remaining, due_date, status, created_at)
     VALUES (?, ?, ?, ?, ?, NULL, ?, ?)'
);
$creditMap = [];
$clientCreditIds = [];
$allocationCapacity = [];

$pdo->beginTransaction();
foreach (source_rows($pdo, $sourceDb, 'vente') as $row) {
    $legacyId = (string) $row['ID'];
    if (map_get($pdo, $sourceDb, 'sale', $legacyId, 'sales') !== null) {
        continue;
    }

    $clientId = map_get($pdo, $sourceDb, 'client', (string) $row['CLIENT_ID'], 'clients');
    $total = money($row['MONTANT']);
    $remaining = $total > 0 ? min(max(0.0, money($row['RESTE'])), $total) : 0.0;
    $paid = max(0.0, money($total - $remaining));
    $status = $remaining > 0.009 ? 'credit' : 'paid';
    $method = $remaining > 0.009 ? 'credit' : 'cash';
    $date = import_date($row['DATESYS']);
    $createdAt = $date . ' 12:00:00';

    $noteParts = ['Import gestionmystock vente #' . $legacyId];
    if (clean_text($row['IDBL'], 80) !== '') {
        $noteParts[] = 'BL : ' . clean_text($row['IDBL'], 80);
    }
    if (clean_text($row['DESCRIPTION'], 200) !== '') {
        $noteParts[] = clean_text($row['DESCRIPTION'], 200);
    }
    if (clean_text($row['USER_LOGIN'], 60) !== '') {
        $noteParts[] = 'Utilisateur legacy : ' . clean_text($row['USER_LOGIN'], 60);
    }

    $saleInsert->execute([
        $clientId,
        $adminId,
        $date,
        $total,
        $total,
        $paid,
        $method,
        $status,
        implode(' · ', $noteParts),
        $createdAt,
    ]);
    $saleId = (int) $pdo->lastInsertId();
    map_set($pdo, $sourceDb, 'sale', $legacyId, $saleId);
    $counts['ventes']++;

    if ($remaining > 0.009 && $clientId !== null) {
        $creditInsert->execute([$saleId, $clientId, $total, $paid, $remaining, 'open', $createdAt]);
        $creditId = (int) $pdo->lastInsertId();
        $creditMap[$legacyId] = $creditId;
        $clientCreditIds[(string) $clientId][] = $creditId;
        $allocationCapacity[$creditId] = $paid;
        $counts['crédits']++;
    }
}
$pdo->commit();
out(sprintf('Ventes : %d · Crédits ouverts : %d', $counts['ventes'], $counts['crédits']));

/* -------------------------------------------------------------------------
| Lignes de vente
|-------------------------------------------------------------------------*/

$itemsBySale = [];
foreach ($pdo->query("SELECT * FROM `{$sourceDb}`.`venteitem` ORDER BY ID")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $itemsBySale[(string) $row['VENTE_ID']][] = $row;
}

$itemInsert = $pdo->prepare(
    'INSERT INTO sale_items (sale_id, product_id, brand, product_name, qty, unit_price, total)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);

$pdo->beginTransaction();
foreach (source_rows($pdo, $sourceDb, 'vente') as $row) {
    $legacyId = (string) $row['ID'];
    $saleId = map_get($pdo, $sourceDb, 'sale', $legacyId, 'sales');
    if ($saleId === null) {
        continue;
    }
    if (map_get($pdo, $sourceDb, 'sale_items_done', $legacyId, 'sales') !== null) {
        continue;
    }

    foreach ($itemsBySale[$legacyId] ?? [] as $item) {
        $legacyProductId = (string) $item['PRODUIT_ID'];
        $productId = map_get($pdo, $sourceDb, 'product', $legacyProductId, 'products');
        $qty = (float) $item['QTE'];
        if ((float) $item['QTEAVOIR'] > 0) {
            $qty = max(0.0, $qty - (float) $item['QTEAVOIR']);
        }
        $unitPrice = money($item['PRIXVENTE']);
        $lineTotal = money($qty * $unitPrice);
        if ($qty == 0.0 && $lineTotal == 0.0) {
            continue;
        }
        $name = $productId !== null
            ? (string) ($productDesignation[$legacyProductId] ?? '')
            : 'Produit supprimé #' . $legacyProductId;
        if ($name === '') {
            $name = 'Produit #' . $legacyProductId;
        }
        $brand = $productId !== null ? (string) ($productBrandName[$legacyProductId] ?? '') : '';
        $itemInsert->execute([
            $saleId,
            $productId,
            clean_text($brand, 120),
            clean_text($name, 200),
            $qty,
            $unitPrice,
            $lineTotal,
        ]);
        $counts['lignes_vente']++;
    }
    map_set($pdo, $sourceDb, 'sale_items_done', $legacyId, $saleId);
}
$pdo->commit();
out(sprintf('Lignes de vente : %d', $counts['lignes_vente']));

/* -------------------------------------------------------------------------
| Paiements clients + affectations
|-------------------------------------------------------------------------*/

$methodMap = [
    'ESPECE' => 'cash',
    'CHEQUE' => 'check',
    'EFFET' => 'effect',
    'Virement' => 'transfer',
    'VIREMENT' => 'transfer',
    'CARTE' => 'card',
];

$paymentInsert = $pdo->prepare(
    'INSERT INTO payments (client_id, user_id, date, amount, method, note, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$allocationInsert = $pdo->prepare(
    'INSERT INTO payment_allocations (payment_id, credit_id, amount) VALUES (?, ?, ?)'
);

$pdo->beginTransaction();
foreach (source_rows($pdo, $sourceDb, 'paiementclient', 'ID') as $row) {
    $legacyId = (string) $row['ID'];
    $amount = money($row['MONTANT']);
    if ($amount <= 0) {
        continue;
    }
    if (map_get($pdo, $sourceDb, 'payment', $legacyId, 'payments') !== null) {
        continue;
    }

    $clientId = map_get($pdo, $sourceDb, 'client', (string) $row['CLIENT_ID'], 'clients');
    $date = import_date($row['DATEPAIEMENT']);
    $createdAt = $date . ' 12:00:00';
    $method = 'cash';
    if (source_table_exists($pdo, $sourceDb, 'modepaiement')) {
        $methodRow = $pdo->query("SELECT MODEPAIEMENT FROM `{$sourceDb}`.`modepaiement` WHERE ID = " . (int) $row['MODEPAIEMENT_ID'])->fetchColumn();
        if ($methodRow !== false) {
            $method = $methodMap[clean_text($methodRow, 30)] ?? 'other';
        }
    }

    $noteParts = ['Import gestionmystock paiement #' . $legacyId];
    if (clean_text($row['DESCRIPTION'], 200) !== '') {
        $noteParts[] = clean_text($row['DESCRIPTION'], 200);
    }
    if ((string) $row['VENTE_ID'] !== '' && (string) $row['VENTE_ID'] !== '0') {
        $noteParts[] = 'Vente legacy #' . (string) $row['VENTE_ID'];
    }
    if (clean_text($row['USER_LOGIN'], 60) !== '') {
        $noteParts[] = 'Utilisateur legacy : ' . clean_text($row['USER_LOGIN'], 60);
    }

    $paymentInsert->execute([
        $clientId,
        $adminId,
        $date,
        $amount,
        $method,
        implode(' · ', $noteParts),
        $createdAt,
    ]);
    $paymentId = (int) $pdo->lastInsertId();
    map_set($pdo, $sourceDb, 'payment', $legacyId, $paymentId);
    $counts['paiements']++;

    $creditId = null;
    $legacySaleId = (string) $row['VENTE_ID'];
    if ($legacySaleId !== '' && isset($creditMap[$legacySaleId])) {
        $creditId = $creditMap[$legacySaleId];
    } elseif ($clientId !== null) {
        foreach ($clientCreditIds[(string) $clientId] ?? [] as $candidate) {
            if (($allocationCapacity[$candidate] ?? 0) > 0.009) {
                $creditId = $candidate;
                break;
            }
        }
    }
    if ($creditId !== null) {
        $capacity = (float) ($allocationCapacity[$creditId] ?? 0);
        $allocated = min($amount, $capacity);
        if ($allocated > 0.009) {
            $allocationInsert->execute([$paymentId, $creditId, money($allocated)]);
            $allocationCapacity[$creditId] = money($capacity - $allocated);
        }
    }
}
$pdo->commit();
out(sprintf('Paiements clients : %d', $counts['paiements']));

/* -------------------------------------------------------------------------
| Achats -> documents d'entrée (historique)
|-------------------------------------------------------------------------*/

if ($withPurchases && source_table_exists($pdo, $sourceDb, 'achat') && source_table_exists($pdo, $sourceDb, 'achatitem')) {
    $usedNumeros = load_used_values($pdo, 'SELECT numero FROM stock_documents WHERE numero IS NOT NULL');
    $purchaseItems = [];
    foreach ($pdo->query("SELECT * FROM `{$sourceDb}`.`achatitem` ORDER BY ID")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $purchaseItems[(string) $row['ACHAT_ID']][] = $row;
    }

    $docInsert = $pdo->prepare(
        'INSERT INTO stock_documents
            (type, motif, numero, doc_date, status, location_id, supplier_id, reference, notes,
             total_cost, total_sale, created_by, validated_by, validated_at, received_by, received_at, created_at)
         VALUES ("entree", "achat", ?, ?, "recu", ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)'
    );
    $lineInsert = $pdo->prepare(
        'INSERT INTO stock_document_lines (document_id, product_id, qty, unit_cost, unit_price, notes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $pdo->beginTransaction();
    foreach (source_rows($pdo, $sourceDb, 'achat') as $row) {
        $legacyId = (string) $row['ID'];
        if (map_get($pdo, $sourceDb, 'purchase', $legacyId, 'stock_documents') !== null) {
            continue;
        }
        $supplierId = map_get($pdo, $sourceDb, 'supplier', (string) $row['FOURNISSEUR_ID'], 'suppliers');
        $date = import_date($row['DATEFACTURE'] !== null && trim((string) $row['DATEFACTURE']) !== '' ? $row['DATEFACTURE'] : $row['DATESYS']);
        $createdAt = $date . ' 12:00:00';
        $numero = unique_code('ACHAT-' . $legacyId, $usedNumeros, 40, 'ACHAT-' . $legacyId);
        $reference = clean_text($row['CODEFACTURE'], 100);
        $notes = 'Import gestionmystock achat #' . $legacyId;
        if (clean_text($row['DESCRIPTION'], 200) !== '') {
            $notes .= ' · ' . clean_text($row['DESCRIPTION'], 200);
        }
        if (clean_text($row['USER_LOGIN'], 60) !== '') {
            $notes .= ' · Utilisateur legacy : ' . clean_text($row['USER_LOGIN'], 60);
        }

        $docInsert->execute([
            $numero,
            $date,
            $defaultLocationId,
            $supplierId,
            $reference !== '' ? $reference : null,
            $notes,
            money($row['MONTANT']),
            $adminId,
            $adminId,
            $createdAt,
            $adminId,
            $createdAt,
            $createdAt,
        ]);
        $docId = (int) $pdo->lastInsertId();
        map_set($pdo, $sourceDb, 'purchase', $legacyId, $docId);
        $counts['achats']++;

        foreach ($purchaseItems[$legacyId] ?? [] as $item) {
            $productId = map_get($pdo, $sourceDb, 'product', (string) $item['PRODUIT_ID'], 'products');
            if ($productId === null) {
                continue;
            }
            $qty = (float) $item['QTE'];
            if ((float) $item['QTEAVOIR'] > 0) {
                $qty = max(0.0, $qty - (float) $item['QTEAVOIR']);
            }
            if ($qty <= 0) {
                continue;
            }
            $lineInsert->execute([
                $docId,
                $productId,
                $qty,
                money($item['PRIXACHAT']),
                money($item['PRIXVENTEMAX']),
                'Import achat #' . $legacyId,
            ]);
            $counts['lignes_achat']++;
        }
    }
    $pdo->commit();
    out(sprintf('Achats : %d · Lignes : %d', $counts['achats'], $counts['lignes_achat']));
} else {
    out('Achats : ignorés (--no-purchases)');
}

/* -------------------------------------------------------------------------
| Bases complémentaires : credit_project et stock
|-------------------------------------------------------------------------*/

if (source_exists($pdo, 'credit_project') && source_table_exists($pdo, 'credit_project', 'client')) {
    $findPhone = $pdo->prepare('SELECT id FROM clients WHERE name = ? AND phone = ? LIMIT 1');
    $findName = $pdo->prepare('SELECT id FROM clients WHERE name = ? LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO clients (name, phone, email, address, ice, type, credit_limit, is_active, is_passenger, notes, created_at)
         VALUES (?, ?, ?, ?, NULL, "PERSONNE", 0, ?, 0, ?, NOW())'
    );
    $pdo->beginTransaction();
    foreach ($pdo->query('SELECT * FROM `credit_project`.`client` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $legacyId = (string) $row['id'];
        if (map_get($pdo, 'credit_project', 'client', $legacyId, 'clients') !== null) {
            continue;
        }
        $name = clean_text($row['name'] ?? '', 150);
        if ($name === '') {
            $name = 'Client crédit #' . $legacyId;
        }
        $phone = clean_text($row['phone'] ?? '', 30);
        $email = clean_text($row['email'] ?? '', 160);
        $existing = null;
        if ($phone !== '') {
            $findPhone->execute([$name, $phone]);
            $existing = $findPhone->fetchColumn();
        }
        if ($existing === false || $existing === null) {
            $findName->execute([$name]);
            $existing = $findName->fetchColumn();
        }
        if ($existing !== false && $existing !== null) {
            map_set($pdo, 'credit_project', 'client', $legacyId, (int) $existing);
            continue;
        }
        $notes = ['Import credit_project #' . $legacyId];
        if (isset($row['remain_paid']) && (float) $row['remain_paid'] !== 0.0) {
            $notes[] = 'Reste legacy : ' . number_format((float) $row['remain_paid'], 2, '.', ' ') . ' DH';
        }
        if (!empty($row['etat'])) {
            $notes[] = 'État legacy : ' . clean_text($row['etat'], 80);
        }
        if (!empty($row['bloqued'])) {
            $notes[] = 'Client bloqué dans l’ancienne application';
        }
        $insert->execute([
            $name,
            $phone !== '' ? $phone : null,
            $email !== '' ? $email : null,
            clean_text($row['address'] ?? '', 255) ?: null,
            empty($row['bloqued']) ? 1 : 0,
            implode(' · ', $notes),
        ]);
        map_set($pdo, 'credit_project', 'client', $legacyId, (int) $pdo->lastInsertId());
        $counts['clients_credit_project']++;
    }
    $pdo->commit();
    out(sprintf('Clients credit_project : %d', $counts['clients_credit_project']));
}

if (source_exists($pdo, 'stock') && source_table_exists($pdo, 'stock', 'produit')) {
    $brandMapStock = [];
    $categoryMapStock = [];
    if (source_table_exists($pdo, 'stock', 'marque')) {
        foreach ($pdo->query('SELECT * FROM `stock`.`marque` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $brandMapStock[(string) $row['id']] = catalog_id($pdo, 'brands', (string) $row['libelle'], 120, 'Sans marque');
        }
    }
    if (source_table_exists($pdo, 'stock', 'categorie')) {
        foreach ($pdo->query('SELECT * FROM `stock`.`categorie` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $categoryMapStock[(string) $row['id']] = catalog_id($pdo, 'categories', (string) $row['libelle'], 120, 'Général');
        }
    }
    $pdo->beginTransaction();
    foreach ($pdo->query('SELECT * FROM `stock`.`produit` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $legacyId = (string) $row['id'];
        if (map_get($pdo, 'stock', 'product', $legacyId, 'products') !== null) {
            continue;
        }
        $reference = clean_text($row['reference'] ?? '', 40);
        $barcodeValue = clean_text($row['barcode'] ?? '', 60);
        $sku = unique_code($reference !== '' ? $reference : $barcodeValue, $usedSku, 40, 'STOCK-' . $legacyId);
        $barcode = null;
        if ($barcodeValue !== '' && mb_strlen($barcodeValue) <= 60 && !isset($usedBarcode[mb_strtolower($barcodeValue)])) {
            $barcode = $barcodeValue;
            $usedBarcode[mb_strtolower($barcodeValue)] = true;
        }
        $designation = clean_text($row['designation'] ?? '', 180) ?: 'Produit stock #' . $legacyId;
        $productInsert->execute([
            $sku,
            $barcode,
            $designation,
            'Import base stock #' . $legacyId,
            $brandMapStock[(string) ($row['marque_id'] ?? '')] ?? null,
            $categoryMapStock[(string) ($row['categorie_id'] ?? '')] ?? null,
            $defaultUnitId,
            0,
            0,
            0,
            0,
        ]);
        $newId = (int) $pdo->lastInsertId();
        map_set($pdo, 'stock', 'product', $legacyId, $newId);
        $levelUpsert->execute([$newId, $defaultLocationId, (float) ($row['qte_total'] ?? 0)]);
        $counts['produits']++;
    }
    $pdo->commit();
    out('Catalogue stock : importé');
}

/* -------------------------------------------------------------------------
| Contrôle final
|-------------------------------------------------------------------------*/

$legacySales = (float) $pdo->query("SELECT COALESCE(SUM(MONTANT), 0) FROM `{$sourceDb}`.`vente`")->fetchColumn();
$importedSales = (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM sales')->fetchColumn();
$legacyClients = source_count($pdo, $sourceDb, 'client');

out('');
out('=== Résumé ===');
out(sprintf('Produits importés        : %d', $counts['produits']));
out(sprintf('Clients legacy           : %d (base : %d)', $counts['clients'], $legacyClients));
out(sprintf('Fournisseurs             : %d', $counts['fournisseurs']));
out(sprintf('Ventes                   : %d', $counts['ventes']));
out(sprintf('Lignes de vente          : %d', $counts['lignes_vente']));
out(sprintf('Paiements                : %d', $counts['paiements']));
out(sprintf('Crédits ouverts          : %d', $counts['crédits']));
out(sprintf('Achats                   : %d', $counts['achats']));
out(sprintf('Total ventes legacy      : %s DH', number_format($legacySales, 2, '.', ' ')));
out(sprintf('Total ventes importées   : %s DH', number_format($importedSales, 2, '.', ' ')));
out('');
out('Import terminé. Lancez php database/doctor.php pour vérifier la base.');

<?php
declare(strict_types=1);

/**
 * Migration des trois anciennes bases vers la base unique.
 *
 *   php database/migrate_legacy.php --force
 *
 * Sources lues (si présentes) :
 *   - suivi_stock            (module Stock)
 *   - gestion_credits_php    (module Crédits, sinon gestion_credits)
 *   - gestion_cheques        (module Chèques)
 *
 * Noms surchargeables pour les bases préfixées par l'hébergeur :
 *   LEGACY_STOCK_DB, LEGACY_CREDITS_DB, LEGACY_CHEQUES_DB
 * Exemple cPanel :
 *   LEGACY_STOCK_DB=moncompte_suivi_stock php database/migrate_legacy.php --force
 *
 * Les anciennes bases ne sont jamais modifiées.
 * Lancez d'abord : php database/install.php --fresh
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Migration accessible uniquement en ligne de commande.');
}

define('GOS_ROOT', dirname(__DIR__));
$GLOBALS['GOS_CONFIG'] = require GOS_ROOT . '/shared/config.php';
define('GOS_BASE', (string) ($GLOBALS['GOS_CONFIG']['app']['base'] ?? ''));
define('GOS_APP_NAME', (string) ($GLOBALS['GOS_CONFIG']['app']['name'] ?? 'Gestion Our Stock'));
define('GOS_VERSION', (string) ($GLOBALS['GOS_CONFIG']['app']['version'] ?? '1.0'));

require GOS_ROOT . '/shared/helpers.php';
require GOS_ROOT . '/shared/db.php';

$force = in_array('--force', $argv ?? [], true);
$server = core_server_db();
$pdo = core_db();

/* -------------------------------------------------------------------------
| Outils
|-------------------------------------------------------------------------*/

function src_db_exists(PDO $server, string $db): bool
{
    $stmt = $server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
    $stmt->execute([$db]);
    return (int) $stmt->fetchColumn() > 0;
}

function src_exists(PDO $server, string $db, string $table): bool
{
    $stmt = $server->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([$db, $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function src_rows(PDO $server, string $db, string $table, string $order = 'id'): array
{
    if (!src_exists($server, $db, $table)) {
        return [];
    }
    return $server->query("SELECT * FROM `{$db}`.`{$table}` ORDER BY `{$order}`")->fetchAll(PDO::FETCH_ASSOC);
}

function src_cols(PDO $server, string $db, string $table): array
{
    if (!src_exists($server, $db, $table)) {
        return [];
    }
    $cols = $server->query("SHOW COLUMNS FROM `{$db}`.`{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    return is_array($cols) ? $cols : [];
}

function pick(array $row, array $keys, mixed $default = null): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null) {
            return $row[$key];
        }
    }
    return $default;
}

function is_true(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null) {
        return false;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    $value = (string) $value;
    return $value === '1' || $value === "\x01" || $value === "b'1'";
}

function normalize(string $value): string
{
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
}

/* -------------------------------------------------------------------------
| Vérifications
|-------------------------------------------------------------------------*/

$targetUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($targetUsers > 0 && !$force) {
    fwrite(STDERR, "La base cible contient déjà {$targetUsers} utilisateur(s).\nRelancez avec --force pour remplacer les données.\n");
    exit(1);
}

$pickDb = static function (array $candidates) use ($server): ?string {
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && src_db_exists($server, $candidate)) {
            return $candidate;
        }
    }
    return null;
};

// Noms surchargeables pour les hébergements qui préfixent les bases (cPanel).
$creditsDb = $pickDb([
    getenv('LEGACY_CREDITS_DB') ?: null,
    'gestion_credits_php',
    'gestion_credits',
]);
$stockDb = $pickDb([getenv('LEGACY_STOCK_DB') ?: null, 'suivi_stock']);
$chequesDb = $pickDb([getenv('LEGACY_CHEQUES_DB') ?: null, 'gestion_cheques']);

echo "=== Migration vers la base unifiée ===\n";
echo 'Stock    : ' . ($stockDb ?? 'absente') . "\n";
echo 'Crédits  : ' . ($creditsDb ?? 'absente') . "\n";
echo 'Chèques  : ' . ($chequesDb ?? 'absente') . "\n";

/* -------------------------------------------------------------------------
| Nettoyage cible
|-------------------------------------------------------------------------*/

if ($force) {
    echo "Nettoyage des tables cibles…\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = [
        'payment_allocations', 'payments', 'credits', 'sale_items', 'sales',
        'credit_bons',
        'cheques_alert_log', 'cheques_alert_config', 'cheques_documents', 'cheques_carnets',
        'inventory_lines', 'inventory_sessions', 'stock_movements', 'stock_levels',
        'stock_document_lines', 'stock_documents', 'login_attempts', 'audit_logs',
        'document_sequences', 'settings', 'products', 'units', 'categories',
        'brands', 'clients', 'users', 'locations', 'roles',
    ];
    foreach ($tables as $table) {
        $exists = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetchColumn();
        if ($exists !== false) {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/* -------------------------------------------------------------------------
| Référentiels
|-------------------------------------------------------------------------*/

$roles = [
    'admin' => 'Administrateur',
    'responsable' => 'Responsable',
    'magasinier' => 'Magasinier',
    'vendeur' => 'Vendeur',
    'auditeur' => 'Auditeur',
];
$roleStmt = $pdo->prepare('INSERT INTO roles (code, label) VALUES (?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label)');
foreach ($roles as $code => $label) {
    $roleStmt->execute([$code, $label]);
}
$roleIds = $pdo->query('SELECT code, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$roleMap = array_map('intval', $roleIds);

function ensure_location(PDO $pdo, string $code, string $name, string $type, ?string $address = null, ?string $phone = null, bool $active = true): int
{
    $stmt = $pdo->prepare('SELECT id FROM locations WHERE code = ?');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $insert = $pdo->prepare('INSERT INTO locations (code, name, type, address, phone, active) VALUES (?, ?, ?, ?, ?, ?)');
    $insert->execute([$code, $name, $type === 'point_vente' ? 'point_vente' : 'depot', $address, $phone, $active ? 1 : 0]);
    return (int) $pdo->lastInsertId();
}

function ensure_brand(PDO $pdo, string $name): int
{
    $name = trim($name) !== '' ? trim($name) : 'Sans marque';
    $stmt = $pdo->prepare('SELECT id FROM brands WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO brands (name) VALUES (?)')->execute([$name]);
    return (int) $pdo->lastInsertId();
}

function ensure_category(PDO $pdo, string $name): int
{
    $name = trim($name) !== '' ? trim($name) : 'Général';
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
    return (int) $pdo->lastInsertId();
}

function ensure_unit(PDO $pdo, string $code, string $label): int
{
    $code = trim($code) !== '' ? trim($code) : 'U';
    $stmt = $pdo->prepare('SELECT id FROM units WHERE code = ?');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO units (code, label) VALUES (?, ?)')->execute([$code, $label !== '' ? $label : $code]);
    return (int) $pdo->lastInsertId();
}

$defaultLocationId = ensure_location($pdo, 'DEP01', 'Dépôt principal', 'depot');
$defaultBrandId = ensure_brand($pdo, 'Sans marque');
$defaultCategoryId = ensure_category($pdo, 'Général');
$defaultUnitId = ensure_unit($pdo, 'U', 'Unité');

/* -------------------------------------------------------------------------
| Utilisateurs
|-------------------------------------------------------------------------*/

$userMap = [];       // source:id => target id
$userByLogin = [];   // login => target id

$findUser = $pdo->prepare('SELECT id FROM users WHERE login = ?');
$insertUser = $pdo->prepare(
    'INSERT INTO users (name, login, email, password_hash, role_id, location_id, modules, active, last_login_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

function import_user(array $row, string $source, string $login, string $name, string $hash, string $roleCode, bool $active, ?int $locationId = null): int
{
    global $pdo, $findUser, $insertUser, $roleMap, $userMap, $userByLogin;

    $login = trim($login);
    if ($login === '') {
        $login = $source . '_' . (int) ($row['id'] ?? 0);
    }
    $findUser->execute([$login]);
    $existing = $findUser->fetchColumn();
    if ($existing !== false) {
        $id = (int) $existing;
    } else {
        $roleId = $roleMap[$roleCode] ?? $roleMap['vendeur'] ?? 1;
        $insertUser->execute([
            $name !== '' ? $name : $login,
            $login,
            pick($row, ['email'], null),
            $hash,
            $roleId,
            $locationId,
            'stock,credits,cheques',
            $active ? 1 : 0,
            pick($row, ['last_login_at', 'derniere_connexion'], null),
            pick($row, ['created_at'], date('Y-m-d H:i:s')),
            pick($row, ['updated_at'], null),
        ]);
        $id = (int) $pdo->lastInsertId();
    }
    $userMap[$source . ':' . (int) ($row['id'] ?? 0)] = $id;
    $userByLogin[normalize($login)] = $id;
    return $id;
}

$adminId = 0;

if ($stockDb !== null) {
    $sourceRoles = [];
    foreach (src_rows($server, $stockDb, 'roles') as $role) {
        $sourceRoles[(int) $role['id']] = (string) $role['code'];
    }
    foreach (src_rows($server, $stockDb, 'users') as $row) {
        $id = import_user(
            $row,
            'stock',
            (string) $row['login'],
            (string) $row['name'],
            (string) $row['password_hash'],
            $sourceRoles[(int) $row['role_id']] ?? 'vendeur',
            is_true($row['active'] ?? 1),
            isset($row['location_id']) ? (int) $row['location_id'] : null
        );
        if (($sourceRoles[(int) $row['role_id']] ?? '') === 'admin' && $adminId === 0) {
            $adminId = $id;
        }
    }
}

if ($chequesDb !== null && src_exists($server, $chequesDb, 'utilisateur')) {
    foreach (src_rows($server, $chequesDb, 'utilisateur') as $row) {
        $role = strtoupper((string) ($row['role'] ?? ''));
        $id = import_user(
            $row,
            'cheques',
            (string) ($row['username'] ?? ''),
            (string) ($row['nom'] ?? ''),
            (string) ($row['password'] ?? ''),
            $role === 'ADMIN' ? 'admin' : 'vendeur',
            is_true($row['actif'] ?? 1)
        );
        if ($role === 'ADMIN' && $adminId === 0) {
            $adminId = $id;
        }
    }
}

if ($creditsDb !== null) {
    foreach (src_rows($server, $creditsDb, 'users') as $row) {
        $role = strtolower((string) ($row['role'] ?? ''));
        $id = import_user(
            $row,
            'credits',
            (string) ($row['username'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['password'] ?? ''),
            $role === 'admin' ? 'admin' : 'vendeur',
            is_true($row['is_active'] ?? 1)
        );
        if ($role === 'admin' && $adminId === 0) {
            $adminId = $id;
        }
    }
}

if ($adminId === 0) {
    $findUser->execute(['admin']);
    $existing = $findUser->fetchColumn();
    if ($existing !== false) {
        $adminId = (int) $existing;
    } else {
        $insertUser->execute([
            'Administrateur',
            'admin',
            null,
            password_hash('Admin@2026', PASSWORD_DEFAULT),
            $roleMap['admin'],
            $defaultLocationId,
            'stock,credits,cheques',
            1,
            null,
            date('Y-m-d H:i:s'),
            null,
        ]);
        $adminId = (int) $pdo->lastInsertId();
        echo "Compte admin créé (admin / Admin@2026).\n";
    }
}
echo 'Utilisateurs importés : ' . count($userMap) . "\n";

/* -------------------------------------------------------------------------
| Sites, marques, catégories, unités, fournisseurs
|-------------------------------------------------------------------------*/

$locationMap = [];
if ($stockDb !== null) {
    foreach (src_rows($server, $stockDb, 'locations') as $row) {
        $locationMap[(int) $row['id']] = ensure_location(
            $pdo,
            (string) $row['code'],
            (string) $row['name'],
            (string) ($row['type'] ?? 'depot'),
            $row['address'] ?? null,
            $row['phone'] ?? null,
            is_true($row['active'] ?? 1)
        );
    }
}

$brandMap = [];
if ($stockDb !== null) {
    foreach (src_rows($server, $stockDb, 'brands') as $row) {
        $brandMap['stock:' . (int) $row['id']] = ensure_brand($pdo, (string) $row['name']);
    }
}
if ($creditsDb !== null) {
    foreach (src_rows($server, $creditsDb, 'brands') as $row) {
        $brandMap['credits:' . (int) $row['id']] = ensure_brand($pdo, (string) $row['name']);
    }
}

$categoryMap = [];
if ($stockDb !== null) {
    foreach (src_rows($server, $stockDb, 'categories') as $row) {
        $categoryMap[(int) $row['id']] = ensure_category($pdo, (string) $row['name']);
    }
}

$unitMap = [];
if ($stockDb !== null) {
    foreach (src_rows($server, $stockDb, 'units') as $row) {
        $unitMap[(int) $row['id']] = ensure_unit($pdo, (string) $row['code'], (string) $row['label']);
    }
}

$supplierMap = [];
if ($stockDb !== null && src_exists($server, $stockDb, 'suppliers')) {
    $insertSupplier = $pdo->prepare(
        "INSERT INTO clients (name, type, ice, phone, email, address, is_active, created_at)
         VALUES (?, 'FOURNISSEUR', ?, ?, ?, ?, ?, ?)"
    );
    foreach (src_rows($server, $stockDb, 'suppliers') as $row) {
        $insertSupplier->execute([
            (string) $row['name'], $row['ice'] ?? null, $row['phone'] ?? null,
            $row['email'] ?? null, $row['address'] ?? null,
            is_true($row['active'] ?? 1) ? 1 : 0, $row['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $supplierMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }
}

echo "Référentiels importés.\n";

/* -------------------------------------------------------------------------
| Clients (crédits + bénéficiaires chèques)
|-------------------------------------------------------------------------*/

$clientMap = [];
$insertClient = $pdo->prepare(
    "INSERT INTO clients (name, type, nature, phone, email, address, ice, credit_limit, is_active, is_passenger, notes, created_at, updated_at)
     VALUES (?, 'CLIENT', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if ($creditsDb !== null && src_exists($server, $creditsDb, 'clients')) {
    foreach (src_rows($server, $creditsDb, 'clients') as $row) {
        $insertClient->execute([
            (string) $row['name'], 'PERSONNE', $row['phone'] ?? null, $row['email'] ?? null,
            $row['address'] ?? null, $row['ice'] ?? null,
            (float) ($row['credit_limit'] ?? 0), is_true($row['is_active'] ?? 1) ? 1 : 0,
            is_true($row['is_passenger'] ?? 0) ? 1 : 0, $row['notes'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s'), $row['updated_at'] ?? null,
        ]);
        $clientMap['credits:' . (int) $row['id']] = (int) $pdo->lastInsertId();
    }
}

if ($chequesDb !== null && src_exists($server, $chequesDb, 'beneficiaire')) {
    foreach (src_rows($server, $chequesDb, 'beneficiaire') as $row) {
        $insertClient->execute([
            (string) $row['nom'],
            strtoupper((string) ($row['type'] ?? 'PERSONNE')) === 'SOCIETE' ? 'SOCIETE' : 'PERSONNE',
            $row['telephone'] ?? null, $row['email'] ?? null,
            $row['adresse'] ?? null, $row['ice'] ?? null,
            0, 1, 0, null,
            $row['created_at'] ?? date('Y-m-d H:i:s'), null,
        ]);
        $clientMap['cheques:' . (int) $row['id']] = (int) $pdo->lastInsertId();
    }
}

// Client passager pour les ventes au comptoir.
$passenger = $pdo->query('SELECT id FROM clients WHERE is_passenger = 1 LIMIT 1')->fetchColumn();
if ($passenger === false) {
    $pdo->prepare("INSERT INTO clients (name, phone, address, credit_limit, is_active, is_passenger) VALUES ('Passager', '', '', 0, 1, 1)")->execute();
}
echo 'Clients importés : ' . count($clientMap) . "\n";

/* -------------------------------------------------------------------------
| Produits
|-------------------------------------------------------------------------*/

$productMap = [];
$insertProduct = $pdo->prepare(
    'INSERT INTO products (sku, barcode, designation, description, brand_id, category_id, unit_id,
        purchase_cost, sale_price, avg_sale_price, min_stock, active, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if ($stockDb !== null) {
    foreach (src_rows($server, $stockDb, 'products') as $row) {
        $insertProduct->execute([
            $row['sku'] ?? null,
            $row['barcode'] ?? null,
            (string) $row['designation'],
            $row['description'] ?? null,
            $brandMap['stock:' . (int) $row['brand_id']] ?? $defaultBrandId,
            $categoryMap[(int) $row['category_id']] ?? $defaultCategoryId,
            $unitMap[(int) $row['unit_id']] ?? $defaultUnitId,
            (float) ($row['purchase_cost'] ?? 0),
            (float) ($row['sale_price'] ?? 0),
            $row['avg_sale_price'] ?? null,
            (float) ($row['min_stock'] ?? 0),
            is_true($row['active'] ?? 1) ? 1 : 0,
            $row['created_at'] ?? date('Y-m-d H:i:s'),
            $row['updated_at'] ?? null,
        ]);
        $productMap['stock:' . (int) $row['id']] = (int) $pdo->lastInsertId();
    }
}

if ($creditsDb !== null && src_exists($server, $creditsDb, 'products')) {
    foreach (src_rows($server, $creditsDb, 'products') as $row) {
        $brandName = trim((string) ($row['brand'] ?? ''));
        $brandId = $brandName !== ''
            ? ensure_brand($pdo, $brandName)
            : ($brandMap['credits:' . (int) ($row['brand_id'] ?? 0)] ?? $defaultBrandId);
        $insertProduct->execute([
            $row['sku'] ?? null,
            $row['barcode'] ?? null,
            (string) pick($row, ['name_fr', 'name', 'name_ar', 'designation'], 'Article'),
            $row['description'] ?? null,
            $brandId,
            $defaultCategoryId,
            $defaultUnitId,
            (float) pick($row, ['purchase_price', 'purchase_cost'], 0),
            (float) pick($row, ['sale_price', 'price'], 0),
            null,
            (float) pick($row, ['stock_min', 'min_stock'], 0),
            is_true($row['is_active'] ?? 1) ? 1 : 0,
            $row['created_at'] ?? date('Y-m-d H:i:s'),
            $row['updated_at'] ?? null,
        ]);
        $productMap['credits:' . (int) $row['id']] = (int) $pdo->lastInsertId();
    }
}
echo 'Produits importés : ' . count($productMap) . "\n";

/* -------------------------------------------------------------------------
| Module Stock : documents, lignes, mouvements, niveaux, inventaires
|-------------------------------------------------------------------------*/

$documentMap = [];
if ($stockDb !== null) {
    foreach (src_rows($server, $stockDb, 'stock_documents') as $row) {
        $stmt = $pdo->prepare(
            'INSERT INTO stock_documents (type, motif, numero, doc_date, status, location_id, dest_location_id,
                supplier_id, reference, notes, total_cost, total_sale, created_by, validated_by, validated_at,
                shipped_by, shipped_at, received_by, received_at, cancel_reason, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $row['type'], $row['motif'] ?? null, $row['numero'], $row['doc_date'], $row['status'],
            $locationMap[(int) $row['location_id']] ?? $defaultLocationId,
            isset($row['dest_location_id']) ? ($locationMap[(int) $row['dest_location_id']] ?? null) : null,
            isset($row['supplier_id']) ? ($supplierMap[(int) $row['supplier_id']] ?? null) : null,
            $row['reference'] ?? null, $row['notes'] ?? null,
            (float) ($row['total_cost'] ?? 0), (float) ($row['total_sale'] ?? 0),
            $userMap['stock:' . (int) $row['created_by']] ?? $adminId,
            isset($row['validated_by']) ? ($userMap['stock:' . (int) $row['validated_by']] ?? null) : null,
            $row['validated_at'] ?? null,
            isset($row['shipped_by']) ? ($userMap['stock:' . (int) $row['shipped_by']] ?? null) : null,
            $row['shipped_at'] ?? null,
            isset($row['received_by']) ? ($userMap['stock:' . (int) $row['received_by']] ?? null) : null,
            $row['received_at'] ?? null, $row['cancel_reason'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s'), $row['updated_at'] ?? null,
        ]);
        $documentMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }

    foreach (src_rows($server, $stockDb, 'stock_document_lines') as $row) {
        if (!isset($documentMap[(int) $row['document_id']], $productMap['stock:' . (int) $row['product_id']])) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO stock_document_lines (document_id, product_id, qty, unit_cost, unit_price, notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $documentMap[(int) $row['document_id']],
            $productMap['stock:' . (int) $row['product_id']],
            (float) $row['qty'], (float) ($row['unit_cost'] ?? 0), (float) ($row['unit_price'] ?? 0), $row['notes'] ?? null,
        ]);
    }

    foreach (src_rows($server, $stockDb, 'stock_movements') as $row) {
        if (!isset($productMap['stock:' . (int) $row['product_id']])) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO stock_movements (document_id, document_line_id, product_id, location_id, direction, qty,
                unit_cost, unit_price, movement_date, inventory_session_id, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            isset($row['document_id']) ? ($documentMap[(int) $row['document_id']] ?? null) : null,
            null,
            $productMap['stock:' . (int) $row['product_id']],
            $locationMap[(int) $row['location_id']] ?? $defaultLocationId,
            $row['direction'], (float) $row['qty'], (float) ($row['unit_cost'] ?? 0), (float) ($row['unit_price'] ?? 0),
            $row['movement_date'], null,
            $userMap['stock:' . (int) $row['created_by']] ?? $adminId,
            $row['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    foreach (src_rows($server, $stockDb, 'stock_levels', 'product_id') as $row) {
        if (!isset($productMap['stock:' . (int) $row['product_id']])) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO stock_levels (product_id, location_id, qty, updated_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE qty = VALUES(qty), updated_at = VALUES(updated_at)'
        )->execute([
            $productMap['stock:' . (int) $row['product_id']],
            $locationMap[(int) $row['location_id']] ?? $defaultLocationId,
            (float) $row['qty'], $row['updated_at'] ?? null,
        ]);
    }

    $inventoryMap = [];
    foreach (src_rows($server, $stockDb, 'inventory_sessions') as $row) {
        $pdo->prepare(
            'INSERT INTO inventory_sessions (numero, location_id, status, started_by, started_at, validated_by, validated_at, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $row['numero'], $locationMap[(int) $row['location_id']] ?? $defaultLocationId, $row['status'],
            $userMap['stock:' . (int) $row['started_by']] ?? $adminId, $row['started_at'],
            isset($row['validated_by']) ? ($userMap['stock:' . (int) $row['validated_by']] ?? null) : null,
            $row['validated_at'] ?? null, $row['notes'] ?? null,
        ]);
        $inventoryMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }
    foreach (src_rows($server, $stockDb, 'inventory_lines') as $row) {
        if (!isset($inventoryMap[(int) $row['session_id']], $productMap['stock:' . (int) $row['product_id']])) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO inventory_lines (session_id, product_id, qty_theoretical, qty_counted, gap, unit_cost)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $inventoryMap[(int) $row['session_id']],
            $productMap['stock:' . (int) $row['product_id']],
            (float) $row['qty_theoretical'], $row['qty_counted'] ?? null, $row['gap'] ?? null, (float) ($row['unit_cost'] ?? 0),
        ]);
    }
    echo 'Documents stock importés : ' . count($documentMap) . "\n";
}

/* -------------------------------------------------------------------------
| Module Crédits : ventes, crédits, paiements
|-------------------------------------------------------------------------*/

if ($creditsDb !== null) {
    $saleMap = [];
    $insertSale = $pdo->prepare(
        'INSERT INTO sales (client_id, user_id, date, subtotal, discount, total, paid, payment_method, status, note, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach (src_rows($server, $creditsDb, 'sales') as $row) {
        $clientId = isset($row['client_id']) ? ($clientMap['credits:' . (int) $row['client_id']] ?? null) : null;
        $insertSale->execute([
            $clientId,
            isset($row['user_id']) ? ($userMap['credits:' . (int) $row['user_id']] ?? $adminId) : $adminId,
            $row['date'], (float) ($row['subtotal'] ?? 0), (float) ($row['discount'] ?? 0),
            (float) ($row['total'] ?? 0), (float) pick($row, ['paid', 'paid_amount'], 0),
            $row['payment_method'] ?? 'cash', $row['status'] ?? 'paid', pick($row, ['note', 'notes'], null),
            $row['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $saleMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }

    // Aide à la reconstruction des lignes de vente (schéma Laravel : quantity,
    // name_fr/brand_id au lieu de qty/product_name/brand).
    $creditBrands = [];
    foreach (src_rows($server, $creditsDb, 'brands') as $b) {
        $creditBrands[(int) $b['id']] = (string) $b['name'];
    }
    $creditProductInfo = [];
    foreach (src_rows($server, $creditsDb, 'products') as $p) {
        $creditProductInfo[(int) $p['id']] = [
            'name'  => (string) pick($p, ['name_fr', 'name', 'designation'], 'Article'),
            'brand' => $creditBrands[(int) ($p['brand_id'] ?? 0)] ?? (string) ($p['brand'] ?? ''),
        ];
    }

    foreach (src_rows($server, $creditsDb, 'sale_items') as $row) {
        if (!isset($saleMap[(int) $row['sale_id']])) {
            continue;
        }
        $info = $creditProductInfo[(int) ($row['product_id'] ?? 0)] ?? ['name' => 'Article', 'brand' => ''];
        $pdo->prepare(
            'INSERT INTO sale_items (sale_id, product_id, brand, product_name, qty, unit_price, total)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $saleMap[(int) $row['sale_id']],
            isset($row['product_id']) ? ($productMap['credits:' . (int) $row['product_id']] ?? null) : null,
            $row['brand'] ?? $info['brand'], $row['product_name'] ?? $info['name'],
            (float) pick($row, ['qty', 'quantity'], 1), (float) ($row['unit_price'] ?? 0), (float) ($row['total'] ?? 0),
        ]);
    }

    $creditMap = [];
    foreach (src_rows($server, $creditsDb, 'credits') as $row) {
        if (!isset($saleMap[(int) $row['sale_id']], $clientMap['credits:' . (int) $row['client_id']])) {
            continue;
        }
        $creditAmount = (float) pick($row, ['amount', 'principal', 'total_due'], 0);
        $creditPaid   = (float) pick($row, ['paid', 'amount_paid'], 0);
        $creditRemain = (float) pick($row, ['remaining', 'balance'], $creditAmount - $creditPaid);
        $pdo->prepare(
            'INSERT INTO credits (sale_id, client_id, amount, paid, remaining, due_date, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $saleMap[(int) $row['sale_id']],
            $clientMap['credits:' . (int) $row['client_id']],
            $creditAmount, $creditPaid, $creditRemain,
            $row['due_date'] ?? null, $row['status'] ?? 'open', $row['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $creditMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }

    $paymentMap = [];
    foreach (src_rows($server, $creditsDb, 'payments') as $row) {
        $pdo->prepare(
            'INSERT INTO payments (client_id, user_id, date, amount, method, note, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            isset($row['client_id']) ? ($clientMap['credits:' . (int) $row['client_id']] ?? null) : null,
            isset($row['user_id']) ? ($userMap['credits:' . (int) $row['user_id']] ?? $adminId) : $adminId,
            pick($row, ['date', 'paid_at'], date('Y-m-d')),
            (float) ($row['amount'] ?? 0), $row['method'] ?? 'cash', pick($row, ['note', 'notes'], null),
            $row['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $paymentMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }

    if (src_exists($server, $creditsDb, 'payment_allocations')) {
        // Le schéma Laravel pointe sur installment_id ; on remonte au crédit.
        $installmentCredit = [];
        foreach (src_rows($server, $creditsDb, 'installments') as $ins) {
            $installmentCredit[(int) $ins['id']] = (int) ($ins['credit_id'] ?? 0);
        }
        foreach (src_rows($server, $creditsDb, 'payment_allocations') as $row) {
            $legacyCreditId = isset($row['credit_id'])
                ? (int) $row['credit_id']
                : ($installmentCredit[(int) ($row['installment_id'] ?? 0)] ?? 0);
            if (!isset($paymentMap[(int) $row['payment_id']], $creditMap[$legacyCreditId])) {
                continue;
            }
            $pdo->prepare('INSERT INTO payment_allocations (payment_id, credit_id, amount) VALUES (?, ?, ?)')
                ->execute([$paymentMap[(int) $row['payment_id']], $creditMap[$legacyCreditId], (float) $row['amount']]);
        }
    }

    echo 'Ventes importées : ' . count($saleMap) . "\n";
}

/* -------------------------------------------------------------------------
| Module Chèques : personnes, carnets, documents, alertes
|-------------------------------------------------------------------------*/

if ($chequesDb !== null) {
    $personneMap = [];
    foreach (src_rows($server, $chequesDb, 'personne') as $row) {
        $pdo->prepare(
            "INSERT INTO clients (name, type, role, phone, email, address, created_at)
             VALUES (?, 'PERSONNE', ?, ?, ?, ?, ?)"
        )->execute([
            (string) $row['nom'], (string) ($row['role'] ?? 'EMPLOYE'),
            $row['telephone'] ?? null, $row['email'] ?? null, $row['adresse'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $personneMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }

    $carnetMap = [];
    foreach (src_rows($server, $chequesDb, 'carnet') as $row) {
        $pdo->prepare(
            'INSERT INTO cheques_carnets (banque, libelle, type, code, personne_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $row['banque'] ?? null, (string) $row['libelle'], (string) ($row['type'] ?? 'CHEQUE'),
            $row['code'] ?? null,
            isset($row['personne_id']) ? ($personneMap[(int) $row['personne_id']] ?? null) : null,
            $row['created_at'] ?? date('Y-m-d H:i:s'), $row['updated_at'] ?? null,
        ]);
        $carnetMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }

    $docMap = [];
    foreach (src_rows($server, $chequesDb, 'document_paiement') as $row) {
        if (!isset($clientMap['cheques:' . (int) $row['beneficiaire_id']])) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO cheques_documents (banque, date_emission, date_echeance, montant, numero, remarque, type,
                beneficiaire_id, personne_id, carnet, carnet_id, date_reglement, regle, rejete, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $row['banque'] ?? null, $row['date_emission'], $row['date_echeance'], (float) $row['montant'],
            (string) $row['numero'], $row['remarque'] ?? null, (string) ($row['type'] ?? 'CHEQUE'),
            $clientMap['cheques:' . (int) $row['beneficiaire_id']],
            isset($row['personne_id']) ? ($personneMap[(int) $row['personne_id']] ?? null) : null,
            $row['carnet'] ?? null,
            isset($row['carnet_id']) ? ($carnetMap[(int) $row['carnet_id']] ?? null) : null,
            $row['date_reglement'] ?? null,
            is_true($row['regle'] ?? 0) ? 1 : 0,
            is_true($row['rejete'] ?? 0) ? 1 : 0,
            $row['created_at'] ?? date('Y-m-d H:i:s'), $row['updated_at'] ?? null,
        ]);
        $docMap[(int) $row['id']] = (int) $pdo->lastInsertId();
    }

    if (src_exists($server, $chequesDb, 'alerte_config')) {
        foreach (src_rows($server, $chequesDb, 'alerte_config') as $row) {
            $pdo->prepare(
                'INSERT INTO cheques_alert_config (active, frequence, canal, jours_avant, heure_envoi, jour_semaine,
                    emails, telephones, derniere_execution, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                is_true($row['active'] ?? 0) ? 1 : 0, (string) ($row['frequence'] ?? 'HEBDOMADAIRE'),
                (string) ($row['canal'] ?? 'EMAIL'), (int) ($row['jours_avant'] ?? 7),
                (string) ($row['heure_envoi'] ?? '09:00:00'), $row['jour_semaine'] ?? null,
                $row['emails'] ?? null, $row['telephones'] ?? null,
                $row['derniere_execution'] ?? null, $row['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    if (src_exists($server, $chequesDb, 'alerte_log')) {
        foreach (src_rows($server, $chequesDb, 'alerte_log') as $row) {
            $pdo->prepare(
                'INSERT INTO cheques_alert_log (envoye_at, canal, destinataire, sujet, message, document_id,
                    document_numero, succes, simule, erreur)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $row['envoye_at'], (string) ($row['canal'] ?? 'EMAIL'), (string) $row['destinataire'],
                $row['sujet'] ?? null, $row['message'] ?? null,
                isset($row['document_id']) ? ($docMap[(int) $row['document_id']] ?? null) : null,
                $row['document_numero'] ?? null,
                is_true($row['succes'] ?? 0) ? 1 : 0, is_true($row['simule'] ?? 0) ? 1 : 0,
                $row['erreur'] ?? null,
            ]);
        }
    }

    if (src_exists($server, $chequesDb, 'parametres_societe')) {
        $company = $server->query("SELECT * FROM `{$chequesDb}`.`parametres_societe` ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (is_array($company)) {
            $map = [
                'company_name' => $company['nom'] ?? null,
                'company_address' => $company['adresse'] ?? null,
                'company_phone' => $company['telephone'] ?? null,
                'company_email' => $company['email'] ?? null,
            ];
            $set = $pdo->prepare('INSERT INTO settings (k, v, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            foreach ($map as $key => $value) {
                if ($value !== null && $value !== '') {
                    $set->execute([$key, $value]);
                }
            }
        }
    }

    echo 'Documents chèques importés : ' . count($docMap) . "\n";
}

/* -------------------------------------------------------------------------
| Paramètres & audit
|-------------------------------------------------------------------------*/

$setSetting = $pdo->prepare('INSERT INTO settings (k, v, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v)');

if ($stockDb !== null && src_exists($server, $stockDb, 'settings')) {
    foreach (src_rows($server, $stockDb, 'settings', 'k') as $row) {
        $setSetting->execute([(string) $row['k'], (string) ($row['v'] ?? '')]);
    }
}

if ($creditsDb !== null && src_exists($server, $creditsDb, 'settings')) {
    foreach (src_rows($server, $creditsDb, 'settings', 'key') as $row) {
        $setSetting->execute([(string) $row['key'], (string) ($row['value'] ?? '')]);
    }
}

foreach (['company_name' => 'Gestion Our Stock', 'currency' => 'MAD', 'credit_alert_days' => '30'] as $key => $value) {
    $setSetting->execute([$key, $value]);
}

if ($stockDb !== null && src_exists($server, $stockDb, 'audit_logs')) {
    foreach (src_rows($server, $stockDb, 'audit_logs') as $row) {
        $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            isset($row['user_id']) ? ($userMap['stock:' . (int) $row['user_id']] ?? null) : null,
            (string) $row['action'], $row['entity_type'] ?? null, $row['entity_id'] ?? null,
            $row['old_values'] ?? null, $row['new_values'] ?? null, $row['ip'] ?? null, $row['user_agent'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
    }
}

if ($chequesDb !== null && src_exists($server, $chequesDb, 'audit_log')) {
    foreach (src_rows($server, $chequesDb, 'audit_log') as $row) {
        $login = normalize((string) ($row['utilisateur'] ?? ''));
        $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $userByLogin[$login] ?? null,
            (string) ($row['action'] ?? 'ACTION'),
            $row['entite'] ?? null,
            $row['entite_id'] ?? null,
            $row['details'] !== null ? json_encode(['details' => $row['details']], JSON_UNESCAPED_UNICODE) : null,
            $row['date_action'] ?? date('Y-m-d H:i:s'),
        ]);
    }
}

/* -------------------------------------------------------------------------
| Séquences de documents
|-------------------------------------------------------------------------*/

if ($stockDb !== null && src_exists($server, $stockDb, 'document_sequences')) {
    foreach (src_rows($server, $stockDb, 'document_sequences', 'type') as $row) {
        $pdo->prepare(
            'INSERT INTO document_sequences (type, year, last_number) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))'
        )->execute([(string) $row['type'], (int) $row['year'], (int) $row['last_number']]);
    }
}

echo "=== Migration terminée ===\n";
echo "Vérifiez ensuite : php database/doctor.php\n";

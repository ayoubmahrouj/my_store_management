<?php
declare(strict_types=1);

/**
 * Fusion ADDITIVE des anciennes bases dans la base unifiée (ne supprime RIEN).
 *
 *   php database/merge_legacy_dumps.php --dry-run
 *   php database/merge_legacy_dumps.php
 *
 * Contrairement à database/migrate_legacy.php (qui vide puis réimporte tout),
 * ce script ajoute uniquement les enregistrements ABSENTS de la base unifiée.
 * Il est idempotent : on peut le relancer sans créer de doublons (recherche par
 * clé naturelle + table import_map).
 *
 * Sources (surchargeables) :
 *   LEGACY_CREDITS_DB (défaut : gestion_credits)
 *   LEGACY_STOCK_DB   (défaut : suivi_stock)
 *   LEGACY_CHEQUES_DB (défaut : gestion_cheques)
 *
 * Ce qui est fusionné :
 *   - marques / catégories / unités / emplacements / fournisseurs
 *   - clients (clients crédits + bénéficiaires chèques), dédupliqués par nom
 *   - produits (catalogue crédits + catalogue stock), dédupliqués par désignation
 *   - chèques : personnes, carnets, documents
 *   - crédits : ventes, lignes de vente, crédits
 *
 * Ce qui n'est PAS fusionné :
 *   - utilisateurs (le compte administrateur unifié est conservé)
 *   - échéances legacy (table `installments`) : pas de table cible ; la date est
 *     reprise dans `credits.due_date`.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Fusion accessible uniquement en ligne de commande.\n");
}

require dirname(__DIR__) . '/shared/bootstrap.php';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $opts['dry'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "Usage: php database/merge_legacy_dumps.php [--dry-run]\n";
        exit(0);
    } elseif (str_starts_with($arg, '--credits-db=')) {
        $opts['credits'] = substr($arg, 13);
    } elseif (str_starts_with($arg, '--stock-db=')) {
        $opts['stock'] = substr($arg, 11);
    } elseif (str_starts_with($arg, '--cheques-db=')) {
        $opts['cheques'] = substr($arg, 13);
    } else {
        fwrite(STDERR, "Option inconnue : {$arg}\n");
        exit(1);
    }
}

$dry = !empty($opts['dry']);
$creditsDb = $opts['credits'] ?? (getenv('LEGACY_CREDITS_DB') ?: 'gestion_credits');
$stockDb   = $opts['stock']   ?? (getenv('LEGACY_STOCK_DB')   ?: 'suivi_stock');
$chequesDb = $opts['cheques'] ?? (getenv('LEGACY_CHEQUES_DB') ?: 'gestion_cheques');

foreach ([$creditsDb, $stockDb, $chequesDb] as $db) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) {
        fwrite(STDERR, "Nom de base legacy invalide : {$db}\n");
        exit(1);
    }
}

$pdo = core_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$targetDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
foreach ([$creditsDb, $stockDb, $chequesDb] as $db) {
    if (strcasecmp($db, $targetDb) === 0) {
        fwrite(STDERR, "La base source « {$db} » est la base cible. Abandon.\n");
        exit(1);
    }
}

/* -------------------------------------------------------------------------
| Helpers
|-------------------------------------------------------------------------*/

$STATS = [];
function out(string $message): void
{
    fwrite(STDOUT, $message . "\n");
}
function warn(string $message): void
{
    fwrite(STDERR, "  ! {$message}\n");
}
function bump(string $key, int $n = 1): void
{
    global $STATS;
    $STATS[$key] = ($STATS[$key] ?? 0) + $n;
}
function norm(?string $value): string
{
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
}
function money(mixed $value): float
{
    return round((float) $value, 2);
}
function ts(mixed $value): string
{
    $value = trim((string) $value);
    return $value === '' ? date('Y-m-d H:i:s') : $value;
}
function db_ok(PDO $pdo, string $db): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
    $stmt->execute([$db]);
    return (bool) $stmt->fetchColumn();
}
function tbl_ok(PDO $pdo, string $db, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->execute([$db, $table]);
    return (bool) $stmt->fetchColumn();
}
function rows(PDO $pdo, string $db, string $table): array
{
    if (!tbl_ok($pdo, $db, $table)) {
        return [];
    }
    return $pdo->query("SELECT * FROM `{$db}`.`{$table}` ORDER BY `id`")->fetchAll(PDO::FETCH_ASSOC);
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
        $pdo->prepare('DELETE FROM import_map WHERE source = ? AND entity = ? AND legacy_id = ?')
            ->execute([$source, $entity, (string) $legacyId]);
        return null;
    }
    return $target;
}
function map_set(PDO $pdo, string $source, string $entity, string $legacyId, int $targetId): void
{
    $pdo->prepare(
        'INSERT INTO import_map (source, entity, legacy_id, target_id) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE target_id = VALUES(target_id)'
    )->execute([$source, $entity, (string) $legacyId, $targetId]);
}

/* -------------------------------------------------------------------------
| Référentiels (marques, catégories, unités, emplacements, fournisseurs)
|-------------------------------------------------------------------------*/

function ensure_named(PDO $pdo, string $table, string $name, string $fallback, string $extraCol = '', mixed $extraVal = null): int
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if ($name === '') {
        $name = $fallback;
    }
    $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE LOWER(TRIM(name)) = ? LIMIT 1");
    $stmt->execute([norm($name)]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    if ($extraCol !== '') {
        $pdo->prepare("INSERT INTO `{$table}` (name, {$extraCol}, active, created_at) VALUES (?, ?, 1, NOW())")
            ->execute([$name, $extraVal]);
    } else {
        $pdo->prepare("INSERT INTO `{$table}` (name, active, created_at) VALUES (?, 1, NOW())")->execute([$name]);
    }
    bump("{$table} ajoutés");
    return (int) $pdo->lastInsertId();
}
function ensure_brand(PDO $pdo, string $name): int
{
    return ensure_named($pdo, 'brands', $name, 'SANS MARQUE');
}
function ensure_category(PDO $pdo, string $name): int
{
    return ensure_named($pdo, 'categories', $name, 'DIVERS');
}
function ensure_unit(PDO $pdo, string $code, string $label): int
{
    $code = trim($code) !== '' ? trim($code) : 'pcs';
    $stmt = $pdo->prepare('SELECT id FROM units WHERE LOWER(TRIM(code)) = ? LIMIT 1');
    $stmt->execute([norm($code)]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO units (code, label, created_at) VALUES (?, ?, NOW())')
        ->execute([$code, trim($label) !== '' ? trim($label) : $code]);
    bump('units ajoutés');
    return (int) $pdo->lastInsertId();
}
function ensure_location(PDO $pdo, array $row): int
{
    $code = trim((string) ($row['code'] ?? ''));
    if ($code === '') {
        $code = 'LOC-' . (int) ($row['id'] ?? 0);
    }
    $stmt = $pdo->prepare('SELECT id FROM locations WHERE LOWER(TRIM(code)) = ? LIMIT 1');
    $stmt->execute([norm($code)]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO locations (code, name, type, address, phone, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $code, trim((string) ($row['name'] ?? $code)), (string) ($row['type'] ?? 'depot'),
            $row['address'] ?? null, $row['phone'] ?? null,
            (int) ($row['active'] ?? 1) ? 1 : 0, ts($row['created_at'] ?? null),
        ]);
    bump('locations ajoutés');
    return (int) $pdo->lastInsertId();
}
function ensure_supplier(PDO $pdo, array $row): int
{
    $name = trim((string) ($row['name'] ?? ''));
    if ($name === '') {
        $name = 'FOURNISSEUR-' . (int) ($row['id'] ?? 0);
    }
    $stmt = $pdo->prepare('SELECT id FROM suppliers WHERE LOWER(TRIM(name)) = ? LIMIT 1');
    $stmt->execute([norm($name)]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare('INSERT INTO suppliers (name, ice, phone, email, address, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $name, $row['ice'] ?? null, $row['phone'] ?? null, $row['email'] ?? null,
            $row['address'] ?? null, (int) ($row['active'] ?? 1) ? 1 : 0, ts($row['created_at'] ?? null),
        ]);
    bump('fournisseurs ajoutés');
    return (int) $pdo->lastInsertId();
}

/* -------------------------------------------------------------------------
| Clients & produits
|-------------------------------------------------------------------------*/

function find_client(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE LOWER(TRIM(name)) = ? LIMIT 1');
    $stmt->execute([norm($name)]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}
function insert_client(PDO $pdo, array $c): int
{
    $pdo->prepare(
        'INSERT INTO clients (name, phone, email, address, ice, type, credit_limit, is_active, is_passenger, notes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $c['name'], $c['phone'], $c['email'], $c['address'], $c['ice'], $c['type'],
        $c['credit_limit'], $c['is_active'], $c['is_passenger'], $c['notes'],
        $c['created_at'], $c['updated_at'],
    ]);
    bump('clients ajoutés');
    return (int) $pdo->lastInsertId();
}
function find_product(PDO $pdo, string $designation, ?int $brandId = null): ?int
{
    $sql = 'SELECT id FROM products WHERE LOWER(TRIM(designation)) = ? AND '
        . ($brandId === null ? 'brand_id IS NULL' : 'brand_id = ?') . ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($brandId === null ? [norm($designation)] : [norm($designation), $brandId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}
function insert_product(PDO $pdo, array $p): int
{
    $pdo->prepare(
        'INSERT INTO products (sku, barcode, designation, description, brand_id, category_id, unit_id,
            purchase_cost, sale_price, avg_sale_price, min_stock, active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $p['sku'], $p['barcode'], $p['designation'], $p['description'], $p['brand_id'], $p['category_id'],
        $p['unit_id'], $p['purchase_cost'], $p['sale_price'], $p['avg_sale_price'], $p['min_stock'],
        $p['active'], $p['created_at'], $p['updated_at'],
    ]);
    bump('produits ajoutés');
    return (int) $pdo->lastInsertId();
}

/* -------------------------------------------------------------------------
| Démarrage
|-------------------------------------------------------------------------*/

out($dry ? '=== FUSION (simulation, aucune écriture) ===' : '=== FUSION ADDITIVE ===');
out("Base cible : {$targetDb}");
foreach (['credits' => $creditsDb, 'stock' => $stockDb, 'cheques' => $chequesDb] as $label => $db) {
    out("  source {$label} : {$db} " . (db_ok($pdo, $db) ? '[ok]' : '[ABSENTE - ignorée]'));
}

$pdo->beginTransaction();

try {
    $adminId = (int) $pdo->query(
        "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' ORDER BY u.id LIMIT 1"
    )->fetchColumn();
    if ($adminId <= 0) {
        $adminId = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    }
    if ($adminId <= 0) {
        $adminId = 1;
    }

    // Défauts référentiels.
    $defaultBrandId = (int) ($pdo->query('SELECT id FROM brands ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($defaultBrandId === 0) {
        $defaultBrandId = ensure_brand($pdo, 'SANS MARQUE');
    }
    $defaultCategoryId = (int) ($pdo->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($defaultCategoryId === 0) {
        $defaultCategoryId = ensure_category($pdo, 'DIVERS');
    }
    $defaultUnitId = (int) ($pdo->query('SELECT id FROM units ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($defaultUnitId === 0) {
        $defaultUnitId = ensure_unit($pdo, 'pcs', 'Pièce');
    }

    /* ----------------------------------------------------------------
    | 1. Référentiels
    |----------------------------------------------------------------*/
    $brandMap = [];        // "credits:ID" / "stock:ID" => target id
    if (db_ok($pdo, $creditsDb)) {
        foreach (rows($pdo, $creditsDb, 'brands') as $row) {
            $brandMap['credits:' . (int) $row['id']] = ensure_brand($pdo, (string) $row['name']);
        }
    }
    if (db_ok($pdo, $stockDb)) {
        foreach (rows($pdo, $stockDb, 'brands') as $row) {
            $brandMap['stock:' . (int) $row['id']] = ensure_brand($pdo, (string) $row['name']);
        }
    }

    $categoryMap = [];
    if (db_ok($pdo, $stockDb)) {
        foreach (rows($pdo, $stockDb, 'categories') as $row) {
            $categoryMap[(int) $row['id']] = ensure_category($pdo, (string) $row['name']);
        }
    }

    $unitMap = [];
    if (db_ok($pdo, $stockDb)) {
        foreach (rows($pdo, $stockDb, 'units') as $row) {
            $unitMap[(int) $row['id']] = ensure_unit($pdo, (string) ($row['code'] ?? ''), (string) ($row['label'] ?? ''));
        }
    }

    $locationMap = [];
    if (db_ok($pdo, $stockDb)) {
        foreach (rows($pdo, $stockDb, 'locations') as $row) {
            $locationMap[(int) $row['id']] = ensure_location($pdo, $row);
        }
    }

    if (db_ok($pdo, $stockDb)) {
        foreach (rows($pdo, $stockDb, 'suppliers') as $row) {
            ensure_supplier($pdo, $row);
        }
    }

    /* ----------------------------------------------------------------
    | 2. Clients
    |----------------------------------------------------------------*/
    $clientMap = [];       // "credits:ID" / "cheques:ID" => target id

    // Client passager.
    $passengerId = (int) ($pdo->query('SELECT id FROM clients WHERE is_passenger = 1 ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($passengerId === 0) {
        $passengerId = insert_client($pdo, [
            'name' => 'Passager', 'phone' => '', 'email' => null, 'address' => '', 'ice' => null,
            'type' => 'PERSONNE', 'credit_limit' => 0, 'is_active' => 1, 'is_passenger' => 1,
            'notes' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => null,
        ]);
    }

    if (db_ok($pdo, $creditsDb)) {
        foreach (rows($pdo, $creditsDb, 'clients') as $row) {
            if ((int) ($row['is_passenger'] ?? 0) === 1) {
                $clientMap['credits:' . (int) $row['id']] = $passengerId;
                continue;
            }
            $name = trim((string) $row['name']);
            $existing = find_client($pdo, $name);
            if ($existing !== null) {
                $clientMap['credits:' . (int) $row['id']] = $existing;
                continue;
            }
            $type = stripos((string) ($row['type'] ?? ''), 'soc') !== false ? 'SOCIETE' : 'PERSONNE';
            $phone = trim((string) ($row['phone'] ?? '')) !== '' ? (string) $row['phone'] : ($row['whatsapp'] ?? null);
            $id = insert_client($pdo, [
                'name' => $name, 'phone' => $phone, 'email' => $row['email'] ?? null,
                'address' => $row['address'] ?? null, 'ice' => $row['ice'] ?? null, 'type' => $type,
                'credit_limit' => money($row['credit_limit'] ?? 0), 'is_active' => (int) ($row['is_active'] ?? 1) ? 1 : 0,
                'is_passenger' => 0, 'notes' => $row['notes'] ?? null,
                'created_at' => ts($row['created_at'] ?? null), 'updated_at' => $row['updated_at'] ?? null,
            ]);
            $clientMap['credits:' . (int) $row['id']] = $id;
        }
    }

    if (db_ok($pdo, $chequesDb)) {
        foreach (rows($pdo, $chequesDb, 'beneficiaire') as $row) {
            $name = trim((string) $row['nom']);
            $existing = find_client($pdo, $name);
            if ($existing !== null) {
                $clientMap['cheques:' . (int) $row['id']] = $existing;
                continue;
            }
            $type = strtoupper((string) ($row['type'] ?? 'PERSONNE')) === 'SOCIETE' ? 'SOCIETE' : 'PERSONNE';
            $id = insert_client($pdo, [
                'name' => $name, 'phone' => $row['telephone'] ?? null, 'email' => $row['email'] ?? null,
                'address' => $row['adresse'] ?? null, 'ice' => $row['ice'] ?? null, 'type' => $type,
                'credit_limit' => 0, 'is_active' => 1, 'is_passenger' => 0, 'notes' => null,
                'created_at' => ts($row['created_at'] ?? null), 'updated_at' => null,
            ]);
            $clientMap['cheques:' . (int) $row['id']] = $id;
        }
    }

    /* ----------------------------------------------------------------
    | 3. Produits
    |----------------------------------------------------------------*/
    $productMap = [];      // "credits:ID" / "stock:ID" => target id

    if (db_ok($pdo, $stockDb)) {
        foreach (rows($pdo, $stockDb, 'products') as $row) {
            $designation = trim((string) $row['designation']);
            $brandId = $brandMap['stock:' . (int) ($row['brand_id'] ?? 0)] ?? $defaultBrandId;
            $existing = find_product($pdo, $designation, $brandId);
            if ($existing !== null) {
                $productMap['stock:' . (int) $row['id']] = $existing;
                continue;
            }
            $id = insert_product($pdo, [
                'sku' => $row['sku'] ?? null, 'barcode' => $row['barcode'] ?? null, 'designation' => $designation,
                'description' => null,
                'brand_id' => $brandId,
                'category_id' => $categoryMap[(int) ($row['category_id'] ?? 0)] ?? $defaultCategoryId,
                'unit_id' => $unitMap[(int) ($row['unit_id'] ?? 0)] ?? $defaultUnitId,
                'purchase_cost' => money($row['purchase_cost'] ?? 0), 'sale_price' => money($row['sale_price'] ?? 0),
                'avg_sale_price' => $row['avg_sale_price'] ?? null, 'min_stock' => money($row['min_stock'] ?? 0),
                'active' => (int) ($row['active'] ?? 1) ? 1 : 0,
                'created_at' => ts($row['created_at'] ?? null), 'updated_at' => $row['updated_at'] ?? null,
            ]);
            $productMap['stock:' . (int) $row['id']] = $id;
        }
    }

    if (db_ok($pdo, $creditsDb)) {
        foreach (rows($pdo, $creditsDb, 'products') as $row) {
            $designation = trim((string) ($row['name_fr'] ?? ''));
            if ($designation === '') {
                $designation = trim((string) ($row['name_ar'] ?? '')) ?: ('ARTICLE-' . (int) $row['id']);
            }
            $brandName = trim((string) ($row['brand'] ?? ''));
            $brandId = $brandName !== ''
                ? ensure_brand($pdo, $brandName)
                : ($brandMap['credits:' . (int) ($row['brand_id'] ?? 0)] ?? $defaultBrandId);
            $existing = find_product($pdo, $designation, $brandId);
            if ($existing !== null) {
                $productMap['credits:' . (int) $row['id']] = $existing;
                continue;
            }
            $id = insert_product($pdo, [
                'sku' => $row['sku'] ?? null, 'barcode' => $row['barcode'] ?? null, 'designation' => $designation,
                'description' => $row['description'] ?? null, 'brand_id' => $brandId,
                'category_id' => $defaultCategoryId, 'unit_id' => $defaultUnitId,
                'purchase_cost' => money($row['purchase_price'] ?? 0), 'sale_price' => money($row['sale_price'] ?? 0),
                'avg_sale_price' => null, 'min_stock' => money($row['stock_min'] ?? 0),
                'active' => (int) ($row['is_active'] ?? 1) ? 1 : 0,
                'created_at' => ts($row['created_at'] ?? null), 'updated_at' => $row['updated_at'] ?? null,
            ]);
            $productMap['credits:' . (int) $row['id']] = $id;
        }
    }

    /* ----------------------------------------------------------------
    | 4. Chèques : personnes, carnets, documents
    |----------------------------------------------------------------*/
    $personneMap = [];
    $carnetMap = [];
    $docMap = [];

    if (db_ok($pdo, $chequesDb)) {
        foreach (rows($pdo, $chequesDb, 'personne') as $row) {
            $nom = trim((string) $row['nom']);
            $stmt = $pdo->prepare('SELECT id FROM cheques_personnes WHERE LOWER(TRIM(nom)) = ? LIMIT 1');
            $stmt->execute([norm($nom)]);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                $pdo->prepare('INSERT INTO cheques_personnes (nom, role, telephone, email, adresse, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $nom, (string) ($row['role'] ?? 'EMPLOYE'), $row['telephone'] ?? null,
                        $row['email'] ?? null, $row['adresse'] ?? null, ts($row['created_at'] ?? null), null,
                    ]);
                $id = (int) $pdo->lastInsertId();
                bump('personnes chèques ajoutées');
            }
            $personneMap[(int) $row['id']] = (int) $id;
        }

        foreach (rows($pdo, $chequesDb, 'carnet') as $row) {
            $libelle = trim((string) $row['libelle']);
            $code = (string) ($row['code'] ?? '');
            $stmt = $pdo->prepare('SELECT id FROM cheques_carnets WHERE LOWER(TRIM(libelle)) = ? AND COALESCE(code, "") = ? LIMIT 1');
            $stmt->execute([norm($libelle), $code]);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                $pdo->prepare('INSERT INTO cheques_carnets (banque, libelle, type, code, personne_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $row['banque'] ?? null, $libelle, (string) ($row['type'] ?? 'CHEQUE'), $row['code'] ?? null,
                        isset($row['personne_id']) ? ($personneMap[(int) $row['personne_id']] ?? null) : null,
                        ts($row['created_at'] ?? null), $row['updated_at'] ?? null,
                    ]);
                $id = (int) $pdo->lastInsertId();
                bump('carnets chèques ajoutés');
            }
            $carnetMap[(int) $row['id']] = (int) $id;
        }

        foreach (rows($pdo, $chequesDb, 'document_paiement') as $row) {
            if (!isset($clientMap['cheques:' . (int) $row['beneficiaire_id']])) {
                continue;
            }
            $numero = (string) $row['numero'];
            $stmt = $pdo->prepare('SELECT id FROM cheques_documents WHERE numero = ? LIMIT 1');
            $stmt->execute([$numero]);
            $id = $stmt->fetchColumn();
            if ($id === false) {
                $pdo->prepare(
                    'INSERT INTO cheques_documents (banque, date_emission, date_echeance, montant, numero, remarque, type,
                        beneficiaire_id, personne_id, carnet, carnet_id, date_reglement, regle, rejete, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $row['banque'] ?? null, $row['date_emission'] ?? null, $row['date_echeance'] ?? null,
                    money($row['montant'] ?? 0), $numero, $row['remarque'] ?? null, (string) ($row['type'] ?? 'CHEQUE'),
                    $clientMap['cheques:' . (int) $row['beneficiaire_id']],
                    isset($row['personne_id']) ? ($personneMap[(int) $row['personne_id']] ?? null) : null,
                    $row['carnet'] ?? null,
                    isset($row['carnet_id']) ? ($carnetMap[(int) $row['carnet_id']] ?? null) : null,
                    $row['date_reglement'] ?? null, (int) ($row['regle'] ?? 0) ? 1 : 0, (int) ($row['rejete'] ?? 0) ? 1 : 0,
                    ts($row['created_at'] ?? null), $row['updated_at'] ?? null,
                ]);
                $id = (int) $pdo->lastInsertId();
                bump('documents chèques ajoutés');
            }
            $docMap[(int) $row['id']] = (int) $id;
        }
    }

    /* ----------------------------------------------------------------
    | 5. Crédits : ventes, lignes, crédits
    |----------------------------------------------------------------*/
    if (db_ok($pdo, $creditsDb)) {
        $saleMap = [];
        foreach (rows($pdo, $creditsDb, 'sales') as $row) {
            $mapped = map_get($pdo, $creditsDb, 'sale', (string) $row['id'], 'sales');
            if ($mapped !== null) {
                $saleMap[(int) $row['id']] = $mapped;
                continue;
            }
            $clientId = isset($row['client_id']) ? ($clientMap['credits:' . (int) $row['client_id']] ?? null) : null;
            $paid = money($row['paid_amount'] ?? 0);
            $total = money($row['total'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            if ($status === '' || $status === 'unpaid') {
                $status = ($paid >= $total && $total > 0) ? 'paid' : (($paid > 0) ? 'partial' : 'credit');
            } elseif ($status === 'paid') {
                $status = 'paid';
            } elseif ($status === 'partial') {
                $status = 'partial';
            } else {
                $status = 'credit';
            }
            $pdo->prepare(
                'INSERT INTO sales (client_id, user_id, date, subtotal, discount, total, paid, payment_method, status, note, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $clientId, $adminId, (string) $row['date'], money($row['subtotal'] ?? 0), money($row['discount'] ?? 0),
                $total, $paid, ($row['type'] ?? 'credit') === 'credit' ? 'credit' : 'cash', $status,
                $row['notes'] ?? null, ts($row['created_at'] ?? null),
            ]);
            $saleId = (int) $pdo->lastInsertId();
            $saleMap[(int) $row['id']] = $saleId;
            map_set($pdo, $creditsDb, 'sale', (string) $row['id'], $saleId);
            bump('ventes ajoutées');
        }

        foreach (rows($pdo, $creditsDb, 'sale_items') as $row) {
            if (!isset($saleMap[(int) $row['sale_id']])) {
                continue;
            }
            if (map_get($pdo, $creditsDb, 'sale_item', (string) $row['id'], 'sale_items') !== null) {
                continue;
            }
            $productId = isset($row['product_id']) ? ($productMap['credits:' . (int) $row['product_id']] ?? null) : null;
            $brandName = '';
            $productName = 'Article';
            if ($productId !== null) {
                $st = $pdo->prepare('SELECT p.designation, b.name FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.id = ?');
                $st->execute([$productId]);
                $prow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $brandName = (string) ($prow['name'] ?? '');
                $productName = (string) ($prow['designation'] ?? 'Article');
            }
            $pdo->prepare(
                'INSERT INTO sale_items (sale_id, product_id, brand, product_name, qty, unit_price, total) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $saleMap[(int) $row['sale_id']], $productId, $brandName, $productName,
                money($row['quantity'] ?? 1), money($row['unit_price'] ?? 0), money($row['total'] ?? 0),
            ]);
            map_set($pdo, $creditsDb, 'sale_item', (string) $row['id'], (int) $pdo->lastInsertId());
            bump('lignes de vente ajoutées');
        }

        foreach (rows($pdo, $creditsDb, 'credits') as $row) {
            $mapped = map_get($pdo, $creditsDb, 'credit', (string) $row['id'], 'credits');
            if ($mapped !== null) {
                continue;
            }
            if (!isset($saleMap[(int) $row['sale_id']])) {
                continue;
            }
            $clientId = $clientMap['credits:' . (int) $row['client_id']] ?? null;
            $amount = money($row['principal'] ?? ($row['total_due'] ?? 0));
            $paid = money($row['amount_paid'] ?? 0);
            $remaining = money($row['balance'] ?? max(0, $amount - $paid));
            $status = ($remaining <= 0.0001) ? 'paid' : 'open';
            $pdo->prepare(
                'INSERT INTO credits (sale_id, client_id, amount, paid, remaining, due_date, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $saleMap[(int) $row['sale_id']], $clientId, $amount, $paid, $remaining,
                $row['due_date'] ?? null, $status, ts($row['created_at'] ?? null),
            ]);
            map_set($pdo, $creditsDb, 'credit', (string) $row['id'], (int) $pdo->lastInsertId());
            bump('crédits ajoutés');
        }
    }

    if ($dry) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . "\n");
    exit(1);
}

/* -------------------------------------------------------------------------
| Rapport
|-------------------------------------------------------------------------*/

out('');
if ($STATS === []) {
    out('Rien à ajouter : la base unifiée est déjà complète pour ces sources.');
} else {
    out($dry ? 'Aperçu des ajouts (simulation) :' : 'Ajouts effectués :');
    foreach ($STATS as $key => $count) {
        out('  - ' . str_pad($key, 32) . $count);
    }
}
out('');
out($dry ? 'Simulation terminée (aucune modification enregistrée).' : 'Fusion terminée.');

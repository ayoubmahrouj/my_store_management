<?php
declare(strict_types=1);

/**
 * Sépare physiquement les tiers auparavant fusionnés dans la table `clients`.
 *
 *   clients       → reste la table des clients (crédits / ventes)
 *   beneficiaire  → nouvelle table des bénéficiaires de chèques / effets
 *   personne      → nouvelle table des personnes internes
 *   suppliers     → nouvelle table des fournisseurs du module Stock
 *
 * Les identifiants sont conservés, ce qui permet de repointer les clés
 * étrangères sans réécrire les documents existants.
 *
 *   php database/migrate_split_tiers.php --force
 *
 * Pré-requis : sauvegarde récente (`php database/backup.php`).
 * Idempotent : si `clients.type` n'existe plus, le script s'arrête sans rien faire.
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
$force = in_array('--force', $argv ?? [], true);

echo "=== Gestion Our Stock — séparation des tiers ===\n";

if (!$force) {
    echo "Cette opération restructure la base.\n";
    echo "1. Sauvegardez : php database/backup.php\n";
    echo "2. Relancez  : php database/migrate_split_tiers.php --force\n";
    exit(1);
}

/** Nombre de lignes d'une requête scalaire. */
function scalar_int(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

/** Vrai si la colonne existe. */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

/** Vrai si l'objet est une vue. */
function is_view(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.views
          WHERE table_schema = DATABASE() AND table_name = ?"
    );
    $stmt->execute([$name]);

    return (int) $stmt->fetchColumn() > 0;
}

/** Vrai si la colonne porte déjà une clé étrangère. */
function fk_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

/** Nom de la clé étrangère d'une colonne vers `clients`, s'il y en a une. */
function fk_to_clients(PDO $pdo, string $table, string $column): ?string
{
    $stmt = $pdo->prepare(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME = 'clients' LIMIT 1"
    );
    $stmt->execute([$table, $column]);
    $name = $stmt->fetchColumn();

    return $name === false ? null : (string) $name;
}

// Base déjà séparée : on saute la copie mais on vérifie quand même les clés
// étrangères (reprise après une exécution interrompue).
$alreadySplit = !column_exists($pdo, 'clients', 'type');

if ($alreadySplit) {
    echo "La base est déjà séparée : contrôle des clés étrangères uniquement.\n";
} else {
    $before = [
        'clients'      => scalar_int($pdo, "SELECT COUNT(*) FROM clients WHERE type = 'CLIENT'"),
        'benef'        => scalar_int($pdo, "SELECT COUNT(*) FROM clients WHERE type = 'CLIENT' AND id IN (SELECT beneficiaire_id FROM cheques_documents)"),
        'personnes'    => scalar_int($pdo, "SELECT COUNT(*) FROM clients WHERE type = 'PERSONNE'"),
        'fournisseurs' => scalar_int($pdo, "SELECT COUNT(*) FROM clients WHERE type = 'FOURNISSEUR'"),
    ];
    echo sprintf(
        "Avant : %d clients · %d bénéficiaires · %d personnes · %d fournisseurs\n",
        $before['clients'],
        $before['benef'],
        $before['personnes'],
        $before['fournisseurs']
    );
}

/* -------------------------------------------------------------------------
| 1. Supprimer les vues de compatibilité (elles masquent les nouvelles tables)
|-------------------------------------------------------------------------*/
foreach (['beneficiaire', 'suppliers', 'personne'] as $view) {
    if (is_view($pdo, $view)) {
        $pdo->exec("DROP VIEW `{$view}`");
        echo "Vue `{$view}` supprimée.\n";
    }
}

/* -------------------------------------------------------------------------
| 2. Créer les tables physiques
|-------------------------------------------------------------------------*/
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `beneficiaire` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nom` VARCHAR(160) NOT NULL,
        `type` ENUM('PERSONNE','SOCIETE') NOT NULL DEFAULT 'SOCIETE',
        `ice` VARCHAR(30) DEFAULT NULL,
        `telephone` VARCHAR(30) DEFAULT NULL,
        `email` VARCHAR(160) DEFAULT NULL,
        `adresse` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `beneficiaire_nom_index` (`nom`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `personne` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `nom` VARCHAR(160) NOT NULL,
        `role` ENUM('EMPLOYE','GERANT','PROPRIETAIRE_SOCIETE') NOT NULL DEFAULT 'EMPLOYE',
        `telephone` VARCHAR(30) DEFAULT NULL,
        `email` VARCHAR(160) DEFAULT NULL,
        `adresse` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `personne_nom_index` (`nom`),
        KEY `personne_role_index` (`role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `suppliers` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(160) NOT NULL,
        `ice` VARCHAR(30) DEFAULT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `email` VARCHAR(160) DEFAULT NULL,
        `address` VARCHAR(255) DEFAULT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `suppliers_name_index` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "Tables `beneficiaire`, `personne` et `suppliers` prêtes.\n";

/* -------------------------------------------------------------------------
| 3. Détacher les clés étrangères qui pointent encore vers `clients`
|    (DDL : commite implicitement, donc AVANT la transaction de données)
|-------------------------------------------------------------------------*/
$fks = [
    ['stock_documents', 'supplier_id'],
    ['cheques_carnets', 'personne_id'],
    ['cheques_documents', 'beneficiaire_id'],
    ['cheques_documents', 'personne_id'],
];
$dropped = [];
foreach ($fks as [$table, $column]) {
    $name = fk_to_clients($pdo, $table, $column);
    if ($name !== null) {
        $pdo->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        $dropped[] = [$table, $column, $name];
        echo "Clé `{$table}.{$column}` détachée de `clients`.\n";
    }
}

/* -------------------------------------------------------------------------
| 4. Copier les données en conservant les identifiants
|-------------------------------------------------------------------------*/
if (!$alreadySplit) {
$pdo->beginTransaction();

$pdo->exec(
    "INSERT IGNORE INTO `beneficiaire` (id, nom, type, ice, telephone, email, adresse, created_at, updated_at)
     SELECT id, name, COALESCE(nature, 'PERSONNE'), ice, phone, email, address, created_at, updated_at
       FROM `clients`
      WHERE type = 'CLIENT'
        AND id IN (SELECT DISTINCT beneficiaire_id FROM cheques_documents)"
);

$pdo->exec(
    "INSERT IGNORE INTO `personne` (id, nom, role, telephone, email, adresse, created_at, updated_at)
     SELECT id, name, COALESCE(role, 'EMPLOYE'), phone, email, address, created_at, updated_at
       FROM `clients`
      WHERE type = 'PERSONNE'"
);

$pdo->exec(
    "INSERT IGNORE INTO `suppliers` (id, name, ice, phone, email, address, active, created_at, updated_at)
     SELECT id, name, ice, phone, email, address, is_active, created_at, updated_at
       FROM `clients`
      WHERE type = 'FOURNISSEUR'"
);

echo sprintf(
    "Copiés : %d bénéficiaires · %d personnes · %d fournisseurs\n",
    scalar_int($pdo, 'SELECT COUNT(*) FROM beneficiaire'),
    scalar_int($pdo, 'SELECT COUNT(*) FROM personne'),
    scalar_int($pdo, 'SELECT COUNT(*) FROM suppliers')
);

// Ne supprime un client que s'il n'est référencé par aucune vente / crédit /
// paiement / bon : un tiers à la fois client et bénéficiaire reste dans les
// deux tables (identifiants indépendants).
$pdo->exec(
    "DELETE FROM `clients`
      WHERE type = 'FOURNISSEUR'
         OR type = 'PERSONNE'
         OR (type = 'CLIENT'
             AND id IN (SELECT DISTINCT beneficiaire_id FROM cheques_documents)
             AND NOT EXISTS (SELECT 1 FROM sales s       WHERE s.client_id = clients.id)
             AND NOT EXISTS (SELECT 1 FROM credits c     WHERE c.client_id = clients.id)
             AND NOT EXISTS (SELECT 1 FROM payments p    WHERE p.client_id = clients.id)
             AND NOT EXISTS (SELECT 1 FROM credit_bons b WHERE b.client_id = clients.id))"
);

if ($pdo->inTransaction()) {
    $pdo->commit();
}
} // !$alreadySplit

/* -------------------------------------------------------------------------
| 5. Nettoyer la table `clients`
|-------------------------------------------------------------------------*/
if (column_exists($pdo, 'clients', 'type') || column_exists($pdo, 'clients', 'role')) {
    $indexExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = 'clients' AND index_name = 'clients_type_index'"
    )->fetchColumn();

    $sql = 'ALTER TABLE `clients`';
    $parts = [];
    if ($indexExists > 0) {
        $parts[] = 'DROP INDEX `clients_type_index`';
    }
    if (column_exists($pdo, 'clients', 'type')) {
        $parts[] = 'DROP COLUMN `type`';
    }
    if (column_exists($pdo, 'clients', 'role')) {
        $parts[] = 'DROP COLUMN `role`';
    }
    $pdo->exec($sql . ' ' . implode(', ', $parts));
    echo "Colonnes `type` et `role` retirées de `clients`.\n";
}

/* -------------------------------------------------------------------------
| 6. Rattacher les clés étrangères aux nouvelles tables
|-------------------------------------------------------------------------*/
$expectedFks = [
    ['stock_documents', 'supplier_id', 'suppliers', 'fk_documents_supplier', ''],
    ['cheques_carnets', 'personne_id', 'personne', 'fk_cheques_carnet_personne', ' ON DELETE SET NULL'],
    ['cheques_documents', 'beneficiaire_id', 'beneficiaire', 'fk_cheques_beneficiaire', ''],
    ['cheques_documents', 'personne_id', 'personne', 'fk_cheques_personne', ' ON DELETE SET NULL'],
];
foreach ($expectedFks as [$table, $column, $target, $name, $onDelete]) {
    if (fk_exists($pdo, $table, $column)) {
        continue;
    }
    $pdo->exec(
        "ALTER TABLE `{$table}`
           ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`)
           REFERENCES `{$target}` (`id`){$onDelete}"
    );
    echo "Clé `{$table}.{$column}` → `{$target}`.\n";
}

/* -------------------------------------------------------------------------
| 7. Contrôle final
|-------------------------------------------------------------------------*/
$after = [
    'clients'      => scalar_int($pdo, 'SELECT COUNT(*) FROM clients'),
    'beneficiaire' => scalar_int($pdo, 'SELECT COUNT(*) FROM beneficiaire'),
    'personne'     => scalar_int($pdo, 'SELECT COUNT(*) FROM personne'),
    'suppliers'    => scalar_int($pdo, 'SELECT COUNT(*) FROM suppliers'),
];
$orphans = [
    'documents sans bénéficiaire' => scalar_int(
        $pdo,
        'SELECT COUNT(*) FROM cheques_documents d LEFT JOIN beneficiaire b ON b.id = d.beneficiaire_id WHERE b.id IS NULL'
    ),
    'documents avec personne inconnue' => scalar_int(
        $pdo,
        'SELECT COUNT(*) FROM cheques_documents d LEFT JOIN personne p ON p.id = d.personne_id WHERE d.personne_id IS NOT NULL AND p.id IS NULL'
    ),
    'documents stock avec fournisseur inconnu' => scalar_int(
        $pdo,
        'SELECT COUNT(*) FROM stock_documents d LEFT JOIN suppliers s ON s.id = d.supplier_id WHERE d.supplier_id IS NOT NULL AND s.id IS NULL'
    ),
];

echo "\nAprès : ";
foreach ($after as $table => $count) {
    echo "{$table}={$count} ";
}
echo "\n";
foreach ($orphans as $label => $count) {
    echo ($count === 0 ? '  OK  ' : '  KO  ') . $label . ' : ' . $count . "\n";
}

$ok = array_sum($orphans) === 0;
echo $ok ? "\nSéparation terminée avec succès.\n" : "\nSéparation terminée mais des incohérences subsistent.\n";
exit($ok ? 0 : 1);

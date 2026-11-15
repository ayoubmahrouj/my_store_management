<?php
declare(strict_types=1);

/**
 * Vide les données métier de la base, sans toucher au schéma.
 *
 *   php database/reset_data.php            (demande confirmation)
 *   php database/reset_data.php --force    (sans confirmation)
 *
 * Conservés : rôles, comptes utilisateurs, paramètres, configuration des
 * alertes chèques. Tout le reste est vidé (produits, ventes, crédits,
 * paiements, chèques, clients, fournisseurs, stock, journal…), puis les
 * référentiels minimaux sont recréés pour que la création rapide fonctionne.
 *
 * Les AUTO_INCREMENT repartent de 1. Irréversible : faites une sauvegarde
 * avant (`mysqldump gestion_our_stock > sauvegarde.sql`).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script réservé à la ligne de commande.\n");
}

define('GOS_ROOT', dirname(__DIR__));
$GLOBALS['GOS_CONFIG'] = require GOS_ROOT . '/shared/config.php';
define('GOS_BASE', (string) ($GLOBALS['GOS_CONFIG']['app']['base'] ?? ''));
define('GOS_APP_NAME', (string) ($GLOBALS['GOS_CONFIG']['app']['name'] ?? 'Gestion Our Stock'));
define('GOS_VERSION', (string) ($GLOBALS['GOS_CONFIG']['app']['version'] ?? '1.0'));

require GOS_ROOT . '/shared/helpers.php';
require GOS_ROOT . '/shared/db.php';

/** Tables conservées : comptes, configuration, référentiels de base. */
const KEEP_TABLES = ['roles', 'users', 'settings', 'cheques_alert_config'];

$force = in_array('--force', $argv ?? [], true);
$pdo = core_db();
$dbName = (string) sh_config('db.name', 'gestion_our_stock');

$stmt = $pdo->prepare(
    'SELECT table_name FROM information_schema.tables
      WHERE table_schema = ? AND table_type = \'BASE TABLE\'
      ORDER BY table_name'
);
$stmt->execute([$dbName]);
$tables = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

$toEmpty = array_values(array_diff($tables, KEEP_TABLES));

if ($toEmpty === []) {
    echo "Rien à vider.\n";
    exit(0);
}

echo "Base : {$dbName}\n";
echo 'Tables conservées : ' . implode(', ', KEEP_TABLES) . "\n\n";
echo "Tables qui seront vidées (" . count($toEmpty) . ") :\n";
foreach ($toEmpty as $t) {
    $n = (int) $pdo->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
    printf("  - %-24s %d ligne(s)\n", $t, $n);
}

if (!$force) {
    echo "\nTaper « oui » pour confirmer : ";
    $answer = trim((string) fgets(STDIN));
    if (strtolower($answer) !== 'oui') {
        echo "Annulé.\n";
        exit(1);
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
try {
    foreach ($toEmpty as $t) {
        $pdo->exec('TRUNCATE TABLE `' . $t . '`');
    }
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// Référentiels minimaux : la création rapide (« + ») doit fonctionner.
$seeds = [
    'INSERT IGNORE INTO `brands` (`name`, `active`) VALUES (?, 1)' => ['Sans marque'],
    'INSERT IGNORE INTO `categories` (`name`, `active`) VALUES (?, 1)' => ['Général'],
    'INSERT IGNORE INTO `units` (`code`, `label`) VALUES (?, ?)' => ['U', 'Unité'],
    'INSERT IGNORE INTO `locations` (`code`, `name`, `type`, `active`) VALUES (?, ?, ?, 1)' => ['DEP01', 'Dépôt principal', 'depot'],
];
foreach ($seeds as $sql => $params) {
    try {
        $pdo->prepare($sql)->execute($params);
    } catch (Throwable $e) {
        fwrite(STDERR, "Référentiel ignoré : " . $e->getMessage() . "\n");
    }
}

echo "\nTerminé : " . count($toEmpty) . " table(s) vidée(s), référentiels minimaux recréés.\n";
echo "Comptes conservés : " . (int) $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn() . "\n";

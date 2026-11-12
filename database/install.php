<?php
declare(strict_types=1);

/**
 * Installeur en ligne de commande.
 *
 *   php database/install.php --fresh          (recrée la base, données de base)
 *   php database/install.php                  (crée les tables manquantes + seeds)
 *
 * Refuse toute exécution depuis le navigateur.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Installeur accessible uniquement en ligne de commande.');
}

define('GOS_ROOT', dirname(__DIR__));
$GLOBALS['GOS_CONFIG'] = require GOS_ROOT . '/shared/config.php';
define('GOS_BASE', (string) ($GLOBALS['GOS_CONFIG']['app']['base'] ?? ''));
define('GOS_APP_NAME', (string) ($GLOBALS['GOS_CONFIG']['app']['name'] ?? 'Gestion Our Stock'));
define('GOS_VERSION', (string) ($GLOBALS['GOS_CONFIG']['app']['version'] ?? '1.0'));

require GOS_ROOT . '/shared/helpers.php';
require GOS_ROOT . '/shared/db.php';

$fresh = in_array('--fresh', $argv ?? [], true);
$dbName = (string) sh_config('db.name', 'gestion_our_stock');

if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
    fwrite(STDERR, "Nom de base invalide : {$dbName}\n");
    exit(1);
}

echo "=== Gestion Our Stock — installation ===\n";
echo "Base cible : {$dbName}\n";

$server = core_server_db();

if ($fresh) {
    echo "Suppression de la base existante…\n";
    $server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
}

$server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Base créée.\n";

$schema = (string) file_get_contents(GOS_ROOT . '/database/schema.sql');
// Retire les commentaires et les instructions de création/selection de base :
// l'installeur gère lui-même la base configurée.
$schema = preg_replace('/^\s*--.*$/m', '', $schema) ?? $schema;
$schema = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $schema) ?? $schema;
$schema = preg_replace('/USE\s+`[^`]+`\s*;/i', '', $schema) ?? $schema;

$statements = preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [];
$pdo = core_db();
$executed = 0;
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '') {
        continue;
    }
    $pdo->exec($statement);
    $executed++;
}
echo "{$executed} instructions SQL exécutées.\n";

/* -------------------------------------------------------------------------
| Données de base
|-------------------------------------------------------------------------*/

$roles = [
    'admin'       => 'Administrateur',
    'responsable' => 'Responsable',
    'magasinier'  => 'Magasinier',
    'vendeur'     => 'Vendeur',
    'auditeur'    => 'Auditeur',
];
$roleStmt = $pdo->prepare('INSERT INTO roles (code, label) VALUES (?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label)');
foreach ($roles as $code => $label) {
    $roleStmt->execute([$code, $label]);
}
echo "Rôles prêts (" . count($roles) . ").\n";

$adminLogin = getenv('ADMIN_LOGIN') ?: 'admin';
$adminPassword = getenv('ADMIN_PASSWORD') ?: 'Admin@2026';
$roleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'admin'")->fetchColumn();
$exists = $pdo->prepare('SELECT id FROM users WHERE login = ?');
$exists->execute([$adminLogin]);
if ($exists->fetchColumn() === false) {
    $insert = $pdo->prepare(
        "INSERT INTO users (name, login, email, password_hash, role_id, modules, active)
         VALUES ('Administrateur', ?, NULL, ?, ?, 'stock,credits,cheques', 1)"
    );
    $insert->execute([$adminLogin, password_hash($adminPassword, PASSWORD_DEFAULT), $roleId]);
    echo "Compte administrateur créé : {$adminLogin} / {$adminPassword}\n";
    echo "⚠ Changez ce mot de passe après la première connexion.\n";
} else {
    echo "Compte administrateur existant conservé.\n";
}

$settings = [
    'company_name'       => 'Gestion Our Stock',
    'currency'           => 'MAD',
    'invoice_prefix'     => 'FAC',
    'credit_alert_days'  => '30',
    'low_stock_alerts'   => '1',
    'cheques_company_name' => 'Gestion Our Stock',
];
$setStmt = $pdo->prepare('INSERT INTO settings (k, v, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()');
foreach ($settings as $key => $value) {
    $setStmt->execute([$key, $value]);
}

// Référentiels minimaux pour que la création rapide fonctionne immédiatement.
$pdo->exec("INSERT INTO brands (name, active) VALUES ('Sans marque', 1) ON DUPLICATE KEY UPDATE name = VALUES(name)");
$pdo->exec("INSERT INTO categories (name, active) VALUES ('Général', 1) ON DUPLICATE KEY UPDATE name = VALUES(name)");
$pdo->exec("INSERT INTO units (code, label) VALUES ('U', 'Unité') ON DUPLICATE KEY UPDATE label = VALUES(label)");
$pdo->exec("INSERT INTO locations (code, name, type, active) VALUES ('DEP01', 'Dépôt principal', 'depot', 1) ON DUPLICATE KEY UPDATE name = VALUES(name)");

echo "Paramètres et référentiels par défaut prêts.\n";
echo "=== Installation terminée ===\n";
echo "Ouvrez : " . (sh_config('app.url', 'http://localhost') ) . "\n";

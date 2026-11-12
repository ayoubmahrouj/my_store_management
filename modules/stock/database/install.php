<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    exit('Installation disponible uniquement en ligne de commande.' . PHP_EOL);
}

require_once __DIR__ . '/bootstrap.php';

$fresh = in_array('--fresh', $argv ?? [], true);
$empty = in_array('--empty', $argv ?? [], true);
$dbName = (string) Config::get('db.name');
if (preg_match('/^[A-Za-z0-9_]+$/', $dbName) !== 1) {
    fwrite(STDERR, "Nom de base invalide : {$dbName}" . PHP_EOL);
    exit(1);
}

$server = Database::serverPdo();

if ($fresh) {
    $server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    echo "Base `{$dbName}` supprimée." . PHP_EOL;
}

$server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Base `{$dbName}` prête." . PHP_EOL;

Database::reset();
$pdo = Database::pdo();

$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "schema.sql introuvable." . PHP_EOL);
    exit(1);
}

$statements = preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [];
$count = 0;
foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '') {
        continue;
    }
    $pdo->exec($statement);
    $count++;
}
echo "{$count} instructions SQL exécutées." . PHP_EOL;

require __DIR__ . ($empty ? '/seed_empty.php' : '/seed.php');

echo PHP_EOL . 'Installation terminée' . ($empty ? ' — base vide' : ' — données de démonstration') . '.' . PHP_EOL;
echo "URL locale : http://127.0.0.1:8080" . PHP_EOL;

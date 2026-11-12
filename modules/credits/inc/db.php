<?php
declare(strict_types=1);

/**
 * Accès base de données — Gestion Crédits.
 *
 * Le module réutilise la connexion PDO du socle unifié (shared/db.php).
 * Les assistants `q()`, `one()`, `all()`, `scalar()` restent disponibles
 * pour ne pas réécrire les pages existantes.
 */

function db(): PDO
{
    return core_db();
}

/* -------------------------------------------------------------------------
| Petits assistants de requêtes (délégués au socle)
|-------------------------------------------------------------------------*/

function q(string $sql, array $params = []): PDOStatement
{
    return core_q($sql, $params);
}

function one(string $sql, array $params = []): ?array
{
    return core_one($sql, $params);
}

function all(string $sql, array $params = []): array
{
    return core_all($sql, $params);
}

function scalar(string $sql, array $params = []): mixed
{
    return core_scalar($sql, $params);
}

/**
 * Lecture d'un paramètre applicatif (table `settings` du socle).
 */
function setting(string $key, string $default = ''): string
{
    return sh_setting($key, $default);
}

/**
 * Taille approximative de la base (octets), pour l'écran Paramètres.
 */
function database_size(): int
{
    try {
        return (int) scalar(
            'SELECT COALESCE(SUM(data_length + index_length), 0)
             FROM information_schema.tables WHERE table_schema = ?',
            [(string) sh_config('db.name', 'gestion_our_stock')]
        );
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * Sauvegarde SQL simple (structure + données) sans dépendre de mysqldump.
 */
function backup_database(string $path): bool
{
    $pdo = db();
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $dbName = (string) sh_config('db.name', 'gestion_our_stock');

    $sql = "-- Sauvegarde " . $dbName . " - " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";

    foreach ($tables as $table) {
        $safe = str_replace('`', '``', (string) $table);
        $create = $pdo->query('SHOW CREATE TABLE `' . $safe . '`')->fetch(PDO::FETCH_NUM);
        $sql .= "\nDROP TABLE IF EXISTS `" . $safe . "`;\n" . $create[1] . ";\n";

        $rows = $pdo->query('SELECT * FROM `' . $safe . '`')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $cols = array_map(fn ($c) => '`' . str_replace('`', '``', (string) $c) . '`', array_keys($row));
            $vals = array_map(
                fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                array_values($row)
            );
            $sql .= 'INSERT INTO `' . $safe . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    return file_put_contents($path, $sql) !== false;
}

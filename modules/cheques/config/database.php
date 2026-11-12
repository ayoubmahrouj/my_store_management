<?php
declare(strict_types=1);

/**
 * Accès base de données du module Chèques — délégué au socle unifié.
 */

function db(): PDO
{
    return core_db();
}

/** Exécute une requête préparée en conservant le type des paramètres. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = core_db()->prepare($sql);
    foreach (array_values($params) as $index => $value) {
        $position = $index + 1;
        if (is_bool($value)) {
            $st->bindValue($position, $value ? 1 : 0, PDO::PARAM_INT);
        } elseif (is_int($value)) {
            $st->bindValue($position, $value, PDO::PARAM_INT);
        } elseif ($value === null) {
            $st->bindValue($position, null, PDO::PARAM_NULL);
        } else {
            $st->bindValue($position, (string) $value, PDO::PARAM_STR);
        }
    }
    $st->execute();
    return $st;
}

function fetch_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function scalar(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = q($sql, $params)->fetchColumn();
    return $value === false ? $default : $value;
}

function last_insert_id(): int
{
    return (int) db()->lastInsertId();
}

/** Convertit une colonne MySQL BIT(1) en booléen. */
function bit_true(mixed $value): bool
{
    if ($value === true) {
        return true;
    }
    if ($value === false || $value === null) {
        return false;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    $value = (string) $value;
    return $value === '1' || $value === "\x01" || $value === 'b\'1\'';
}

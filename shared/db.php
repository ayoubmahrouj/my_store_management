<?php
declare(strict_types=1);

/**
 * Connexion PDO unique à la base unifiée + petits assistants.
 */

function core_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) sh_config('db.host', '127.0.0.1');
    $port = (string) sh_config('db.port', '3306');
    $name = (string) sh_config('db.name', 'gestion_our_stock');
    $charset = (string) sh_config('db.charset', 'utf8mb4');
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    $pdo = new PDO(
        $dsn,
        (string) sh_config('db.user', 'root'),
        (string) sh_config('db.pass', ''),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '+01:00'");

    return $pdo;
}

/** Connexion sans base (installeur). */
function core_server_db(): PDO
{
    $host = (string) sh_config('db.host', '127.0.0.1');
    $port = (string) sh_config('db.port', '3306');
    return new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        (string) sh_config('db.user', 'root'),
        (string) sh_config('db.pass', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function core_q(string $sql, array $params = []): PDOStatement
{
    $stmt = core_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function core_one(string $sql, array $params = []): ?array
{
    $row = core_q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function core_all(string $sql, array $params = []): array
{
    return core_q($sql, $params)->fetchAll();
}

function core_scalar(string $sql, array $params = []): mixed
{
    return core_q($sql, $params)->fetchColumn();
}

function core_insert_id(): int
{
    return (int) core_db()->lastInsertId();
}

/**
 * Journal d'audit commun.
 */
function core_audit(string $action, ?string $entityType = null, int|string|null $entityId = null, ?string $details = null, ?array $newValues = null): void
{
    try {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        core_q(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $userId ?: null,
                mb_substr($action, 0, 60),
                $entityType !== null ? mb_substr($entityType, 0, 60) : null,
                $entityId !== null ? (int) $entityId : null,
                $details !== null ? json_encode(['details' => $details], JSON_UNESCAPED_UNICODE) : ($newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null),
                mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]
        );
    } catch (Throwable) {
        // L'audit ne doit jamais bloquer une action métier.
    }
}

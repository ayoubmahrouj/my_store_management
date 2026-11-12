<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) Config::get('db.host');
        $port = (string) Config::get('db.port');
        $name = (string) Config::get('db.name');
        $charset = (string) Config::get('db.charset', 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, (string) Config::get('db.user'), (string) Config::get('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Connexion base de données impossible : ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    /** Connexion sans base, utilisée par l'installeur. */
    public static function serverPdo(): PDO
    {
        $host = (string) Config::get('db.host');
        $port = (string) Config::get('db.port');
        return new PDO("mysql:host={$host};port={$port};charset=utf8mb4", (string) Config::get('db.user'), (string) Config::get('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}

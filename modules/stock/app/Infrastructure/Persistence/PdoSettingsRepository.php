<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use PDO;

final class PdoSettingsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $settings = [];
        foreach ($this->pdo->query('SELECT k, v FROM settings ORDER BY k ASC')->fetchAll() as $row) {
            $settings[(string) $row['k']] = (string) ($row['v'] ?? '');
        }
        return $settings;
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->pdo->prepare('SELECT v FROM settings WHERE k = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) ($value ?? '');
    }

    /** @param array<string, string> $values */
    public function setMany(array $values): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO settings (k, v, updated_at) VALUES (:k, :v, NOW())
             ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()'
        );
        foreach ($values as $key => $value) {
            $stmt->execute(['k' => $key, 'v' => $value]);
        }
    }
}

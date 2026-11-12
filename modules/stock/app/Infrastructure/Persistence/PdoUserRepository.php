<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use App\Domain\Entities\User;
use PDO;
use PDOException;
use RuntimeException;

final class PdoUserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare($this->baseQuery() . ' WHERE u.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByLogin(string $login): ?User
    {
        $stmt = $this->pdo->prepare($this->baseQuery() . ' WHERE u.login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /** @return array<int, User> */
    public function all(): array
    {
        $stmt = $this->pdo->query($this->baseQuery() . ' ORDER BY u.active DESC, u.name ASC');

        return array_map(fn (array $row): User => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function findRaw(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, login, email, role_id, location_id, active FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array{id: int, code: string, label: string}> */
    public function roles(): array
    {
        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'code'  => (string) $row['code'],
            'label' => (string) $row['label'],
        ], $this->pdo->query('SELECT id, code, label FROM roles ORDER BY label ASC')->fetchAll());
    }

    public function roleId(string $code): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (name, login, email, password_hash, role_id, location_id, active)
                 VALUES (:name, :login, :email, :password_hash, :role_id, :location_id, :active)'
            );
            $stmt->execute([
                'name'          => trim((string) ($data['name'] ?? '')),
                'login'         => trim((string) ($data['login'] ?? '')),
                'email'         => $this->nullable($data['email'] ?? null),
                'password_hash' => (string) ($data['password_hash'] ?? ''),
                'role_id'       => (int) ($data['role_id'] ?? 0),
                'location_id'   => !empty($data['location_id']) ? (int) $data['location_id'] : null,
                'active'        => !empty($data['active']) ? 1 : 0,
            ]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new RuntimeException('Ce login est déjà utilisé.', 0, $e);
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE users SET name = :name, login = :login, email = :email, role_id = :role_id,
                       location_id = :location_id, active = :active, updated_at = NOW()';
        $params = [
            'name'        => trim((string) ($data['name'] ?? '')),
            'login'       => trim((string) ($data['login'] ?? '')),
            'email'       => $this->nullable($data['email'] ?? null),
            'role_id'     => (int) ($data['role_id'] ?? 0),
            'location_id' => !empty($data['location_id']) ? (int) $data['location_id'] : null,
            'active'      => !empty($data['active']) ? 1 : 0,
            'id'          => $id,
        ];

        if (!empty($data['password_hash'])) {
            $sql .= ', password_hash = :password_hash';
            $params['password_hash'] = (string) $data['password_hash'];
        }

        $sql .= ' WHERE id = :id';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new RuntimeException('Ce login est déjà utilisé.', 0, $e);
            }
            throw $e;
        }
    }

    public function toggle(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET active = 1 - active, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Utilisateur introuvable.');
        }
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.active = 1 AND r.code = 'admin'"
        )->fetchColumn();
    }

    private function baseQuery(): string
    {
        return 'SELECT u.id, u.name, u.login, u.password_hash, u.active,
                       r.code AS role_code, r.label AS role_label,
                       u.location_id, l.name AS location_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN locations l ON l.id = u.location_id';
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['login'],
            (string) $row['password_hash'],
            (string) $row['role_code'],
            (string) $row['role_label'],
            $row['location_id'] !== null ? (int) $row['location_id'] : null,
            $row['location_name'] !== null ? (string) $row['location_name'] : null,
            (bool) $row['active'],
        );
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}

<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Database;
use App\Domain\Entities\Category;
use App\Domain\Entities\Location;
use App\Domain\Entities\Unit;
use Gos\Domain\Entities\Fournisseur;
use Gos\Domain\Entities\Marque;
use PDO;
use PDOException;
use RuntimeException;

final class PdoReferenceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array<int, Marque> */
    public function brands(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, active FROM brands';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        return array_map(
            static fn (array $row): Marque => Marque::fromRow($row),
            $this->pdo->query($sql)->fetchAll()
        );
    }

    /** @return array<int, Category> */
    public function categories(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, active FROM categories';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        return array_map(
            static fn (array $row): Category => new Category((int) $row['id'], (string) $row['name'], (bool) $row['active']),
            $this->pdo->query($sql)->fetchAll()
        );
    }

    /** @return array<int, Unit> */
    public function units(): array
    {
        return array_map(
            static fn (array $row): Unit => new Unit((int) $row['id'], (string) $row['code'], (string) $row['label']),
            $this->pdo->query('SELECT id, code, label FROM units ORDER BY code ASC')->fetchAll()
        );
    }

    /** @return array<int, Fournisseur> */
    public function suppliers(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, ice, phone, email, address, active FROM suppliers';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        return array_map(
            static fn (array $row): Fournisseur => Fournisseur::fromRow($row),
            $this->pdo->query($sql)->fetchAll()
        );
    }

    /** @return array<int, Location> */
    public function locations(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, code, name, type, address, phone, active FROM locations';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY type ASC, name ASC';

        return array_map(
            static fn (array $row): Location => new Location(
                (int) $row['id'],
                (string) $row['code'],
                (string) $row['name'],
                (string) $row['type'],
                $row['address'] !== null ? (string) $row['address'] : null,
                $row['phone'] !== null ? (string) $row['phone'] : null,
                (bool) $row['active'],
            ),
            $this->pdo->query($sql)->fetchAll()
        );
    }

    /** @param array<string, mixed> $data */
    public function create(string $type, array $data): int
    {
        $type = strtolower($type);

        try {
            switch ($type) {
                case 'brand':
                    $stmt = $this->pdo->prepare('INSERT INTO brands (name) VALUES (:name)');
                    $stmt->execute(['name' => $this->required($data, 'name')]);
                    break;
                case 'category':
                    $stmt = $this->pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
                    $stmt->execute(['name' => $this->required($data, 'name')]);
                    break;
                case 'unit':
                    $stmt = $this->pdo->prepare('INSERT INTO units (code, label) VALUES (:code, :label)');
                    $stmt->execute([
                        'code'  => mb_strtoupper($this->required($data, 'code')),
                        'label' => $this->required($data, 'label'),
                    ]);
                    break;
                case 'supplier':
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO clients (name, type, ice, phone, email, address)
                         VALUES (:name, 'FOURNISSEUR', :ice, :phone, :email, :address)"
                    );
                    $stmt->execute([
                        'name'    => $this->required($data, 'name'),
                        'ice'     => $this->optional($data, 'ice'),
                        'phone'   => $this->optional($data, 'phone'),
                        'email'   => $this->optional($data, 'email'),
                        'address' => $this->optional($data, 'address'),
                    ]);
                    break;
                case 'location':
                    $locationType = (string) ($data['type'] ?? 'depot');
                    if (!in_array($locationType, ['depot', 'point_vente'], true)) {
                        throw new RuntimeException('Type de site invalide.');
                    }
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO locations (code, name, type, address, phone)
                         VALUES (:code, :name, :type, :address, :phone)'
                    );
                    $stmt->execute([
                        'code'    => mb_strtoupper($this->required($data, 'code')),
                        'name'    => $this->required($data, 'name'),
                        'type'    => $locationType,
                        'address' => $this->optional($data, 'address'),
                        'phone'   => $this->optional($data, 'phone'),
                    ]);
                    break;
                default:
                    throw new RuntimeException('Référentiel inconnu.');
            }
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new RuntimeException('Cette valeur existe déjà dans le référentiel.', 0, $e);
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function toggle(string $type, int $id): void
    {
        $type = strtolower($type);

        if ($type === 'supplier') {
            $stmt = $this->pdo->prepare(
                "UPDATE clients SET is_active = 1 - is_active WHERE id = :id AND type = 'FOURNISSEUR'"
            );
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Élément introuvable.');
            }
            return;
        }

        $table = match ($type) {
            'brand' => 'brands',
            'category' => 'categories',
            'location' => 'locations',
            default => throw new RuntimeException('Ce référentiel ne peut pas être désactivé.'),
        };

        $stmt = $this->pdo->prepare("UPDATE {$table} SET active = 1 - active WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Élément introuvable.');
        }
    }

    public function exists(string $type, int $id, bool $activeOnly = true): bool
    {
        $table = match (strtolower($type)) {
            'brand' => 'brands',
            'category' => 'categories',
            'unit' => 'units',
            'supplier' => 'suppliers',
            'location' => 'locations',
            default => null,
        };
        if ($table === null) {
            return false;
        }

        $sql = "SELECT id FROM {$table} WHERE id = :id";
        if ($activeOnly && $table !== 'units') {
            $sql .= ' AND active = 1';
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $data */
    private function required(array $data, string $key): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException('Le champ « ' . $key . ' » est obligatoire.');
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private function optional(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));
        return $value === '' ? null : $value;
    }
}

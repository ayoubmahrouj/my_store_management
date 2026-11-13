<?php
declare(strict_types=1);

namespace Gos\Domain\Repositories;

use Gos\Domain\Entities\Marque;

/**
 * Dépôt des marques (table `brands`) — partagé Crédits / Suivi Stock.
 */
final class MarqueRepository
{
    /** @return array<int, Marque> */
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, name, active FROM brands';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name ASC';

        return array_map(
            static fn (array $row): Marque => Marque::fromRow($row),
            core_all($sql)
        );
    }

    public function find(int $id): ?Marque
    {
        $row = core_one('SELECT id, name, active FROM brands WHERE id = ? LIMIT 1', [$id]);

        return $row === null ? null : Marque::fromRow($row);
    }

    public function findByName(string $name): ?Marque
    {
        $row = core_one('SELECT id, name, active FROM brands WHERE LOWER(name) = LOWER(?) LIMIT 1', [$name]);

        return $row === null ? null : Marque::fromRow($row);
    }

    public function save(Marque $marque): int
    {
        if ($marque->id() > 0) {
            core_q('UPDATE brands SET name = ?, active = ? WHERE id = ?', [
                $marque->name(),
                $marque->isActive() ? 1 : 0,
                $marque->id(),
            ]);

            return $marque->id();
        }

        core_q('INSERT INTO brands (name, active) VALUES (?, ?)', [
            $marque->name(),
            $marque->isActive() ? 1 : 0,
        ]);

        return core_insert_id();
    }

    /** Retrouve la marque par son nom ou la crée (endpoints « + »). */
    public function quickCreate(string $name): Marque
    {
        $existing = $this->findByName($name);
        if ($existing !== null) {
            return $existing;
        }

        $id = $this->save(new Marque(null, $name, true));

        return new Marque($id, $name, true);
    }

    public function toggle(int $id): void
    {
        core_q('UPDATE brands SET active = 1 - active WHERE id = ?', [$id]);
    }
}

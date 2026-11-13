<?php
declare(strict_types=1);

namespace Gos\Domain\Entities\Concerns;

use LogicException;

/**
 * Rend une entité du domaine lisible comme un tableau associatif.
 *
 * Le code historique (modules Crédits / Chèques) manipule des tableaux
 * `$row['name']`. Les entités exposent donc aussi l'accès tableau, en lecture
 * seule, pour rester compatibles sans dupliquer la couche d'accès.
 *
 * @implements \ArrayAccess<string, mixed>
 */
trait ReadOnlyArray
{
    /** @return array<string, mixed> */
    abstract public function toArray(): array;

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Les entités du domaine sont en lecture seule.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Les entités du domaine sont en lecture seule.');
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

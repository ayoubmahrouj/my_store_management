<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

use ArrayAccess;
use Gos\Domain\Entities\Concerns\ReadOnlyArray;
use JsonSerializable;

/**
 * Marque — référentiel **partagé** par les modules Crédits et Suivi Stock.
 *
 * Correspond à la table `brands`. Le module Stock l'exposait sous le nom
 * `Brand` ; il utilise désormais cette classe unique.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Marque implements ArrayAccess, JsonSerializable
{
    use ReadOnlyArray;

    public function __construct(
        private ?int $id = null,
        private string $name = '',
        private bool $active = true,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['name'] ?? ''),
            (bool) ($row['active'] ?? true),
        );
    }

    public function id(): int
    {
        return (int) $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'active' => $this->active,
        ];
    }
}

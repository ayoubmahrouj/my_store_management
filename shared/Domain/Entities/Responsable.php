<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

/**
 * Responsable — personne interne (propriétaire, gérant, employé).
 *
 * Stocké dans sa propre table `personne` (module Chèques).
 */
final class Responsable extends Personne
{
    public const ROLE_PROPRIETAIRE = 'PROPRIETAIRE_SOCIETE';
    public const ROLE_GERANT       = 'GERANT';
    public const ROLE_EMPLOYE      = 'EMPLOYE';

    public const ROLES = [
        self::ROLE_PROPRIETAIRE,
        self::ROLE_GERANT,
        self::ROLE_EMPLOYE,
    ];

    public function __construct(
        ?int $id = null,
        string $name = '',
        string $nature = self::NATURE_PERSONNE,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
        ?string $ice = null,
        ?string $notes = null,
        bool $active = true,
        private ?string $role = null,
    ) {
        parent::__construct($id, $name, $nature, $phone, $email, $address, $ice, $notes, $active);
    }

    public static function type(): string
    {
        return 'PERSONNE';
    }

    public static function fromRow(array $row): static
    {
        $args = self::commonArgs($row);
        $role = isset($row['role']) ? (string) $row['role'] : null;

        return new static(
            $args['id'],
            $args['name'],
            $args['nature'],
            $args['phone'],
            $args['email'],
            $args['address'],
            $args['ice'],
            $args['notes'],
            $args['active'],
            $role !== '' ? $role : null,
        );
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_PROPRIETAIRE => 'Propriétaire',
            self::ROLE_GERANT       => 'Gérant',
            self::ROLE_EMPLOYE      => 'Employé',
            default                 => (string) $this->role,
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return parent::toArray() + ['role' => $this->role];
    }
}

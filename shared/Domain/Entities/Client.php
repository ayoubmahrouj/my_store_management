<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

/**
 * Client — tiers auquel on vend (module Crédits / Suivi Stock).
 *
 * Correspond aux lignes `clients` dont `type = 'CLIENT'`.
 * Côté module Chèques, le même tiers est manipulé via {@see Beneficiaire}.
 */
class Client extends Personne
{
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
        private float $creditLimit = 0.0,
        private bool $passenger = false,
    ) {
        parent::__construct($id, $name, $nature, $phone, $email, $address, $ice, $notes, $active);
    }

    public static function type(): string
    {
        return 'CLIENT';
    }

    public static function fromRow(array $row): static
    {
        $args = self::commonArgs($row);

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
            (float) ($row['credit_limit'] ?? 0),
            (bool) ($row['is_passenger'] ?? false),
        );
    }

    public function creditLimit(): float
    {
        return $this->creditLimit;
    }

    public function isPassenger(): bool
    {
        return $this->passenger;
    }

    /** Créance restante du client (crédits ouverts). */
    public function solde(): float
    {
        if ($this->id === null) {
            return 0.0;
        }

        return (float) core_scalar(
            'SELECT COALESCE(SUM(remaining), 0) FROM credits WHERE client_id = ? AND status = ?',
            [$this->id, 'open']
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return parent::toArray() + [
            'credit_limit' => $this->creditLimit,
            'is_passenger' => $this->passenger,
        ];
    }
}

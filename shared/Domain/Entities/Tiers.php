<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

/**
 * Fabrique de tiers : associe un type de tiers à sa classe et à sa table.
 *
 * Chaque population a désormais sa propre table ; le type est explicite
 * (`client`, `beneficiaire`, `fournisseur`, `personne`) et non plus déduit
 * d'une colonne discriminante.
 */
final class Tiers
{
    public const TYPES = ['client', 'beneficiaire', 'fournisseur', 'personne'];

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row, string $type = 'client'): Personne
    {
        $class = self::classFor($type);

        return $class::fromRow($row);
    }

    /** @return class-string<Personne> */
    public static function classFor(string $type): string
    {
        return match (strtolower($type)) {
            'fournisseur'  => Fournisseur::class,
            'personne'     => Responsable::class,
            'beneficiaire' => Beneficiaire::class,
            default        => Client::class,
        };
    }
}

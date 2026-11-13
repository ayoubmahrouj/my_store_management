<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

/**
 * Fabrique de tiers : transforme une ligne de la table `clients` en la bonne
 * sous-classe de {@see Personne} selon le discriminant `type`.
 *
 * NB : à réserver aux lignes de la table `clients` (où `type` vaut
 * CLIENT / FOURNISSEUR / PERSONNE). Pour les vues historiques, instancier
 * directement la classe voulue (`Beneficiaire::fromRow()`, …).
 */
final class Tiers
{
    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): Personne
    {
        return match (self::discriminant($row)) {
            Fournisseur::type() => Fournisseur::fromRow($row),
            Responsable::type() => Responsable::fromRow($row),
            default             => Client::fromRow($row),
        };
    }

    /** @return class-string<Personne> */
    public static function classFor(string $type): string
    {
        return match (strtoupper($type)) {
            Fournisseur::type()   => Fournisseur::class,
            Responsable::type()   => Responsable::class,
            Beneficiaire::type()  => Beneficiaire::class,
            default               => Client::class,
        };
    }

    /** @param array<string, mixed> $row */
    private static function discriminant(array $row): string
    {
        $type = strtoupper((string) ($row['type'] ?? Client::type()));

        return in_array($type, [Client::type(), Fournisseur::type(), Responsable::type()], true)
            ? $type
            : Client::type();
    }
}

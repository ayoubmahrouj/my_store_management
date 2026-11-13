<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

/**
 * Fournisseur — tiers auprès duquel on s'approvisionne.
 *
 * Correspond aux lignes `clients` dont `type = 'FOURNISSEUR'`.
 * La vue historique `suppliers` pointe désormais sur la même table.
 */
final class Fournisseur extends Personne
{
    public static function type(): string
    {
        return 'FOURNISSEUR';
    }

    public static function fromRow(array $row): static
    {
        return new static(...self::commonArgs($row));
    }
}

<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

/**
 * Fournisseur — tiers auprès duquel on s'approvisionne.
 *
 * Stocké dans sa propre table `suppliers` (module Stock).
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

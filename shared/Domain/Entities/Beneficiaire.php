<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

/**
 * Bénéficiaire — tiers au profit duquel un chèque / effet est émis (module Chèques).
 *
 * Stocké dans sa propre table `beneficiaire`, indépendante des clients.
 * La colonne `type` y désigne la forme juridique (`SOCIETE` / `PERSONNE`).
 */
final class Beneficiaire extends Personne
{
    public static function type(): string
    {
        return 'BENEFICIAIRE';
    }

    public static function fromRow(array $row): static
    {
        return new static(...self::commonArgs($row));
    }
}

<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

/**
 * Bénéficiaire — tiers au profit duquel un chèque / effet est émis (module Chèques).
 *
 * C'est la lecture « Chèques » du même tiers que {@see Client} : même table
 * `clients`, même `type = 'CLIENT'`. La vue historique `beneficiaire` expose
 * `type` au sens de la forme juridique (`SOCIETE` / `PERSONNE`), ce qui est
 * résolu automatiquement par {@see Personne::fromRow()}.
 */
final class Beneficiaire extends Personne
{
    public static function type(): string
    {
        return 'CLIENT';
    }

    public static function fromRow(array $row): static
    {
        return new static(...self::commonArgs($row));
    }
}

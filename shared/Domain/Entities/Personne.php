<?php
declare(strict_types=1);

namespace Gos\Domain\Entities;

use ArrayAccess;
use Gos\Domain\Entities\Concerns\ReadOnlyArray;
use JsonSerializable;

/**
 * Personne (tiers) — classe mère de tous les intervenants.
 *
 * Chaque population est stockée dans sa propre table :
 *
 *   - {@see Client}       → `clients`      (crédits / ventes)
 *   - {@see Beneficiaire} → `beneficiaire` (chèques / effets)
 *   - {@see Responsable}  → `personne`     (personnel interne : rôle)
 *   - {@see Fournisseur}  → `suppliers`    (module Stock)
 *
 * Aucune table n'est partagée entre deux populations.
 *
 * @implements ArrayAccess<string, mixed>
 */
abstract class Personne implements ArrayAccess, JsonSerializable
{
    use ReadOnlyArray;

    public const NATURE_PERSONNE = 'PERSONNE';
    public const NATURE_SOCIETE  = 'SOCIETE';

    public function __construct(
        protected ?int $id = null,
        protected string $name = '',
        protected string $nature = self::NATURE_PERSONNE,
        protected ?string $phone = null,
        protected ?string $email = null,
        protected ?string $address = null,
        protected ?string $ice = null,
        protected ?string $notes = null,
        protected bool $active = true,
    ) {
    }

    /** Type logique du tiers (`client`, `beneficiaire`, `fournisseur`, `personne`). */
    abstract public static function type(): string;

    /** Construit l'entité à partir d'une ligne de sa table. */
    abstract public static function fromRow(array $row): static;

    public function id(): int
    {
        return (int) $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function nature(): string
    {
        return $this->nature;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function ice(): ?string
    {
        return $this->ice;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isSociete(): bool
    {
        return $this->nature === self::NATURE_SOCIETE;
    }

    public function isPersonnePhysique(): bool
    {
        return !$this->isSociete();
    }

    public function natureLabel(): string
    {
        return $this->isSociete() ? 'Société' : 'Personne';
    }

    public function displayName(): string
    {
        return $this->name;
    }

    /**
     * Arguments communs à tous les tiers, prêts à être passés au constructeur.
     *
     * Gère indifféremment les colonnes de la table `clients`
     * (`name`, `phone`, `address`…) et celles des vues historiques
     * (`nom`, `telephone`, `adresse`).
     *
     * @return array{id: ?int, name: string, nature: string, phone: ?string, email: ?string, address: ?string, ice: ?string, notes: ?string, active: bool}
     */
    protected static function commonArgs(array $row): array
    {
        return [
            'id'      => isset($row['id']) ? (int) $row['id'] : null,
            'name'    => (string) ($row['name'] ?? $row['nom'] ?? ''),
            'nature'  => self::resolveNature($row),
            'phone'   => self::nullable($row['phone'] ?? $row['telephone'] ?? null),
            'email'   => self::nullable($row['email'] ?? null),
            'address' => self::nullable($row['address'] ?? $row['adresse'] ?? null),
            'ice'     => self::nullable($row['ice'] ?? null),
            'notes'   => self::nullable($row['notes'] ?? null),
            'active'  => (bool) ($row['is_active'] ?? $row['active'] ?? true),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'nom'       => $this->name,
            'type'      => static::type(),
            'nature'    => $this->nature,
            'phone'     => $this->phone,
            'telephone' => $this->phone,
            'email'     => $this->email,
            'address'   => $this->address,
            'adresse'   => $this->address,
            'ice'       => $this->ice,
            'notes'     => $this->notes,
            'is_active' => $this->active,
        ];
    }

    /** Résout la nature (forme juridique) à partir d'une ligne `clients` ou d'une vue. */
    protected static function resolveNature(array $row): string
    {
        if (isset($row['nature']) && $row['nature'] !== '') {
            return (string) $row['nature'];
        }

        $type = strtoupper((string) ($row['type'] ?? ''));

        return in_array($type, [self::NATURE_PERSONNE, self::NATURE_SOCIETE], true)
            ? $type
            : self::NATURE_PERSONNE;
    }

    protected static function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

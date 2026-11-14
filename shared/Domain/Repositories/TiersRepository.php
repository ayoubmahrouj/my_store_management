<?php
declare(strict_types=1);

namespace Gos\Domain\Repositories;

use Gos\Domain\Entities\Beneficiaire;
use Gos\Domain\Entities\Client;
use Gos\Domain\Entities\Fournisseur;
use Gos\Domain\Entities\Personne;
use Gos\Domain\Entities\Responsable;
use Gos\Domain\Entities\Tiers;

/**
 * Dépôt des tiers, une table physique par population :
 *
 *   Client        → `clients`      (crédits / ventes)
 *   Beneficiaire  → `beneficiaire` (chèques / effets)
 *   Responsable   → `personne`     (personnel interne)
 *   Fournisseur   → `suppliers`    (module Stock)
 *
 * Chaque table possède ses propres identifiants : aucun tiers n'est dupliqué
 * d'une population à l'autre.
 */
final class TiersRepository
{
    private const TABLES = [
        'client'       => 'clients',
        'beneficiaire' => 'beneficiaire',
        'fournisseur'  => 'suppliers',
        'personne'     => 'personne',
    ];

    /** @return array<int, Client> */
    public function clients(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $row): Client => Client::fromRow($row),
            core_all($this->sql('client', $activeOnly))
        );
    }

    /** @return array<int, Beneficiaire> */
    public function beneficiaires(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $row): Beneficiaire => Beneficiaire::fromRow($row),
            core_all($this->sql('beneficiaire', $activeOnly))
        );
    }

    /** @return array<int, Fournisseur> */
    public function fournisseurs(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $row): Fournisseur => Fournisseur::fromRow($row),
            core_all($this->sql('fournisseur', $activeOnly))
        );
    }

    /** @return array<int, Responsable> */
    public function responsables(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $row): Responsable => Responsable::fromRow($row),
            core_all($this->sql('personne', $activeOnly))
        );
    }

    public function find(int $id, string $type = 'client'): ?Personne
    {
        $table = $this->table($type);
        $row = core_one("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
        if ($row === null) {
            return null;
        }

        $class = Tiers::classFor($type);

        return $class::fromRow($row);
    }

    public function exists(int $id, ?string $type = null): bool
    {
        if ($type === null) {
            foreach (array_keys(self::TABLES) as $candidate) {
                if ($this->exists($id, $candidate)) {
                    return true;
                }
            }

            return false;
        }

        $table = $this->table($type);

        return core_scalar("SELECT 1 FROM `{$table}` WHERE id = ? LIMIT 1", [$id]) !== false;
    }

    /** Crée ou met à jour un tiers. Retourne son identifiant. */
    public function save(Personne $personne): int
    {
        $type = $this->type($personne);
        $table = self::TABLES[$type];
        $columns = $this->columns($personne);

        if ($personne->id() > 0) {
            $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = :{$c}", array_keys($columns)));
            $columns['id'] = $personne->id();
            core_q("UPDATE `{$table}` SET {$set}, `updated_at` = NOW() WHERE `id` = :id", $columns);

            return $personne->id();
        }

        $columns['created_at'] = date('Y-m-d H:i:s');
        $cols = implode(', ', array_map(static fn (string $c): string => "`{$c}`", array_keys($columns)));
        $ph = implode(', ', array_map(static fn (string $c): string => ":{$c}", array_keys($columns)));
        core_q("INSERT INTO `{$table}` ({$cols}) VALUES ({$ph})", $columns);

        return core_insert_id();
    }

    public function delete(int $id, string $type = 'client'): void
    {
        $table = $this->table($type);
        core_q("DELETE FROM `{$table}` WHERE id = ?", [$id]);
    }

    public function setActive(int $id, bool $active, string $type = 'client'): void
    {
        $column = $type === 'fournisseur' ? 'active' : 'is_active';
        $table = $this->table($type);
        core_q("UPDATE `{$table}` SET `{$column}` = ? WHERE id = ?", [$active ? 1 : 0, $id]);
    }

    /** Solde (crédits ouverts) d'un client. */
    public function balance(int $clientId): float
    {
        return (float) core_scalar(
            'SELECT COALESCE(SUM(remaining), 0) FROM credits WHERE client_id = ? AND status = ?',
            [$clientId, 'open']
        );
    }

    /* ---------------------------------------------------------------------
    | Créations rapides (endpoints « + » des listes déroulantes)
    |---------------------------------------------------------------------*/

    public function quickCreateClient(string $name, ?string $phone = null): Client
    {
        $client = new Client(null, $name, Personne::NATURE_PERSONNE, $phone);
        $client = new Client($this->save($client), $name, Personne::NATURE_PERSONNE, $phone);

        return $client;
    }

    public function quickCreateResponsable(
        string $name,
        string $role = Responsable::ROLE_EMPLOYE,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
    ): Responsable {
        if (!in_array($role, Responsable::ROLES, true)) {
            $role = Responsable::ROLE_EMPLOYE;
        }

        $entity = new Responsable(null, mb_substr($name, 0, 160), Personne::NATURE_PERSONNE, $phone, $email, $address, null, null, true, $role);

        return new Responsable($this->save($entity), $entity->name(), Personne::NATURE_PERSONNE, $phone, $email, $address, null, null, true, $role);
    }

    public function quickCreateBeneficiaire(
        string $name,
        string $nature = Personne::NATURE_PERSONNE,
        ?string $ice = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
    ): Beneficiaire {
        $nature = $nature === Personne::NATURE_SOCIETE ? Personne::NATURE_SOCIETE : Personne::NATURE_PERSONNE;
        $entity = new Beneficiaire(null, mb_substr($name, 0, 160), $nature, $phone, $email, $address, $ice);

        return new Beneficiaire($this->save($entity), $entity->name(), $nature, $phone, $email, $address, $ice);
    }

    /* ---------------------------------------------------------------------*/

    private function type(Personne $personne): string
    {
        return match (true) {
            $personne instanceof Beneficiaire => 'beneficiaire',
            $personne instanceof Fournisseur  => 'fournisseur',
            $personne instanceof Responsable  => 'personne',
            default                           => 'client',
        };
    }

    /** @return array<string, mixed> */
    private function columns(Personne $personne): array
    {
        if ($personne instanceof Beneficiaire) {
            return [
                'nom'       => $personne->name(),
                'type'      => $personne->nature(),
                'ice'       => $personne->ice(),
                'telephone' => $personne->phone(),
                'email'     => $personne->email(),
                'adresse'   => $personne->address(),
            ];
        }

        if ($personne instanceof Responsable) {
            return [
                'nom'       => $personne->name(),
                'role'      => $personne->role() ?? Responsable::ROLE_EMPLOYE,
                'telephone' => $personne->phone(),
                'email'     => $personne->email(),
                'adresse'   => $personne->address(),
            ];
        }

        if ($personne instanceof Fournisseur) {
            return [
                'name'    => $personne->name(),
                'ice'     => $personne->ice(),
                'phone'   => $personne->phone(),
                'email'   => $personne->email(),
                'address' => $personne->address(),
                'active'  => $personne->isActive() ? 1 : 0,
            ];
        }

        return [
            'name'         => $personne->name(),
            'nature'       => $personne->nature(),
            'phone'        => $personne->phone(),
            'email'        => $personne->email(),
            'address'      => $personne->address(),
            'ice'          => $personne->ice(),
            'notes'        => $personne->notes(),
            'is_active'    => $personne->isActive() ? 1 : 0,
            'credit_limit' => $personne instanceof Client ? $personne->creditLimit() : 0,
            'is_passenger' => $personne instanceof Client && $personne->isPassenger() ? 1 : 0,
        ];
    }

    private function table(string $type): string
    {
        $type = strtolower($type);
        if (!isset(self::TABLES[$type])) {
            throw new \InvalidArgumentException("Type de tiers inconnu : {$type}");
        }

        return self::TABLES[$type];
    }

    private function sql(string $type, bool $activeOnly): string
    {
        $table = $this->table($type);
        $sql = "SELECT * FROM `{$table}`";
        if ($activeOnly && $type === 'fournisseur') {
            $sql .= ' WHERE active = 1';
        } elseif ($activeOnly && $type === 'client') {
            $sql .= ' WHERE is_active = 1';
        }

        return $sql . ' ORDER BY ' . ($type === 'beneficiaire' || $type === 'personne' ? 'nom' : 'name') . ' ASC';
    }
}

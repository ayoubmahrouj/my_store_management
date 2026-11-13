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
 * Dépôt unique des tiers (table `clients`).
 *
 * Centralise les lectures / écritures des clients, fournisseurs, responsables
 * et bénéficiaires afin que les modules Crédits, Chèques et Suivi Stock
 * partagent exactement le même accès aux données.
 */
final class TiersRepository
{
    /** @return array<int, Client> */
    public function clients(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $row): Client => Client::fromRow($row),
            core_all($this->sql('CLIENT', $activeOnly))
        );
    }

    /** @return array<int, Beneficiaire> */
    public function beneficiaires(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $row): Beneficiaire => Beneficiaire::fromRow($row),
            core_all($this->sql('CLIENT', $activeOnly))
        );
    }

    /** @return array<int, Fournisseur> */
    public function fournisseurs(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $row): Fournisseur => Fournisseur::fromRow($row),
            core_all($this->sql('FOURNISSEUR', $activeOnly))
        );
    }

    /** @return array<int, Responsable> */
    public function responsables(bool $activeOnly = false): array
    {
        return array_map(
            static fn (array $row): Responsable => Responsable::fromRow($row),
            core_all($this->sql('PERSONNE', $activeOnly))
        );
    }

    public function find(int $id): ?Personne
    {
        $row = core_one('SELECT * FROM clients WHERE id = ? LIMIT 1', [$id]);

        return $row === null ? null : Tiers::fromRow($row);
    }

    public function exists(int $id, ?string $type = null): bool
    {
        if ($type === null) {
            return core_scalar('SELECT 1 FROM clients WHERE id = ? LIMIT 1', [$id]) !== false;
        }

        return core_scalar(
            'SELECT 1 FROM clients WHERE id = ? AND type = ? LIMIT 1',
            [$id, strtoupper($type)]
        ) !== false;
    }

    /** Crée ou met à jour un tiers. Retourne son identifiant. */
    public function save(Personne $personne): int
    {
        $columns = $this->columns($personne);

        if ($personne->id() > 0) {
            $set = implode(', ', array_map(static fn (string $c): string => "`{$c}` = :{$c}", array_keys($columns)));
            $columns['id'] = $personne->id();
            core_q("UPDATE clients SET {$set}, `updated_at` = NOW() WHERE `id` = :id", $columns);

            return $personne->id();
        }

        $columns['type'] = $personne::type();
        $columns['created_at'] = date('Y-m-d H:i:s');

        $cols = implode(', ', array_map(static fn (string $c): string => "`{$c}`", array_keys($columns)));
        $ph = implode(', ', array_map(static fn (string $c): string => ":{$c}", array_keys($columns)));
        core_q("INSERT INTO clients ({$cols}) VALUES ({$ph})", $columns);

        return core_insert_id();
    }

    public function delete(int $id): void
    {
        core_q('DELETE FROM clients WHERE id = ?', [$id]);
    }

    public function setActive(int $id, bool $active): void
    {
        core_q('UPDATE clients SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
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

        $entity = new Responsable(null, $name, Personne::NATURE_PERSONNE, $phone, $email, $address, null, null, true, $role);

        return new Responsable($this->save($entity), $name, Personne::NATURE_PERSONNE, $phone, $email, $address, null, null, true, $role);
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
        $entity = new Beneficiaire(null, $name, $nature, $phone, $email, $address, $ice);

        return new Beneficiaire($this->save($entity), $name, $nature, $phone, $email, $address, $ice);
    }

    /* ---------------------------------------------------------------------*/

    /** @return array<string, mixed> */
    private function columns(Personne $personne): array
    {
        $columns = [
            'name'      => $personne->name(),
            'nature'    => $personne->nature(),
            'phone'     => $personne->phone(),
            'email'     => $personne->email(),
            'address'   => $personne->address(),
            'ice'       => $personne->ice(),
            'notes'     => $personne->notes(),
            'is_active' => $personne->isActive() ? 1 : 0,
        ];

        if ($personne instanceof Client) {
            $columns['credit_limit'] = $personne->creditLimit();
            $columns['is_passenger'] = $personne->isPassenger() ? 1 : 0;
        }

        if ($personne instanceof Responsable) {
            $columns['role'] = $personne->role();
        }

        return $columns;
    }

    private function sql(string $type, bool $activeOnly): string
    {
        $sql = "SELECT * FROM clients WHERE type = '{$type}'";
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        return $sql . ' ORDER BY name ASC';
    }
}

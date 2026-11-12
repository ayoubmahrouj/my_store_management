<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class User
{
    public function __construct(
        private int $id,
        private string $name,
        private string $login,
        private string $passwordHash,
        private string $roleCode,
        private string $roleLabel,
        private ?int $locationId,
        private ?string $locationName,
        private bool $active,
    ) {
    }

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function login(): string { return $this->login; }
    public function passwordHash(): string { return $this->passwordHash; }
    public function roleCode(): string { return $this->roleCode; }
    public function roleLabel(): string { return $this->roleLabel; }
    public function locationId(): ?int { return $this->locationId; }
    public function locationName(): ?string { return $this->locationName; }
    public function isActive(): bool { return $this->active; }

    public function isAdmin(): bool
    {
        return $this->roleCode === 'admin';
    }

    public function canManage(): bool
    {
        return in_array($this->roleCode, ['admin', 'responsable'], true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'login'    => $this->login,
            'role'     => ['code' => $this->roleCode, 'label' => $this->roleLabel],
            'location' => $this->locationId !== null
                ? ['id' => $this->locationId, 'name' => $this->locationName]
                : null,
            'active'   => $this->active,
        ];
    }
}

<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class Location
{
    public function __construct(
        private int $id,
        private string $code,
        private string $name,
        private string $type,
        private ?string $address,
        private ?string $phone,
        private bool $active,
    ) {
    }

    public function id(): int { return $this->id; }
    public function code(): string { return $this->code; }
    public function name(): string { return $this->name; }
    public function type(): string { return $this->type; }
    public function address(): ?string { return $this->address; }
    public function phone(): ?string { return $this->phone; }
    public function isActive(): bool { return $this->active; }

    public function typeLabel(): string
    {
        return $this->type === 'depot' ? 'Dépôt' : 'Point de vente';
    }

    public function isDepot(): bool
    {
        return $this->type === 'depot';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'code'   => $this->code,
            'name'   => $this->name,
            'type'   => $this->type,
            'address'=> $this->address,
            'phone'  => $this->phone,
            'active' => $this->active,
        ];
    }
}

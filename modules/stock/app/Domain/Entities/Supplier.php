<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class Supplier
{
    public function __construct(
        private int $id,
        private string $name,
        private ?string $ice,
        private ?string $phone,
        private ?string $email,
        private ?string $address,
        private bool $active,
    ) {
    }

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function ice(): ?string { return $this->ice; }
    public function phone(): ?string { return $this->phone; }
    public function email(): ?string { return $this->email; }
    public function address(): ?string { return $this->address; }
    public function isActive(): bool { return $this->active; }
}

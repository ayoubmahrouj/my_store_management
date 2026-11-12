<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class Brand
{
    public function __construct(
        private int $id,
        private string $name,
        private bool $active,
    ) {
    }

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function isActive(): bool { return $this->active; }
}

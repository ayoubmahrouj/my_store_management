<?php
declare(strict_types=1);

namespace App\Domain\Entities;

final class Unit
{
    public function __construct(
        private int $id,
        private string $code,
        private string $label,
    ) {
    }

    public function id(): int { return $this->id; }
    public function code(): string { return $this->code; }
    public function label(): string { return $this->label; }
}

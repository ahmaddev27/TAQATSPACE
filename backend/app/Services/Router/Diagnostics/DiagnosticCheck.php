<?php

declare(strict_types=1);

namespace App\Services\Router\Diagnostics;

use App\Enums\DiagnosticStatus;

/** The result of one integration health check; `name` is a stable key the UI translates. */
final readonly class DiagnosticCheck
{
    public function __construct(
        public string $name,
        public DiagnosticStatus $status,
        public string $detail,
    ) {}

    /** @return array{name: string, status: string, detail: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'status' => $this->status->value, 'detail' => $this->detail];
    }
}

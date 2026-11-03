<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\ApprovalType;
use App\Models\ApprovalRequest;

/**
 * Per-type behaviour of the approvals framework: the framework owns the
 * lifecycle, the handler owns what a request of its type means.
 */
interface ApprovalHandler
{
    public function type(): ApprovalType;

    /**
     * Normalises a submitted payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> The payload to store.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $payload): array;

    /** Performs the change. Called exactly once, inside the approval transaction. */
    public function apply(ApprovalRequest $request): void;

    /**
     * Human-readable view of the change for the decision screen.
     *
     * @return array{current: mixed, proposed: mixed}
     */
    public function describe(ApprovalRequest $request): array;
}

<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Contracts\ApprovalHandler;
use App\Enums\ApprovalType;
use InvalidArgumentException;

/**
 * Maps each {@see ApprovalType} to its handler. Registering a handler for a type
 * that already has one replaces it (used by tests to swap in a fake).
 */
class ApprovalHandlerRegistry
{
    /** @var array<string, ApprovalHandler> */
    private array $handlers = [];

    /**
     * @param  iterable<ApprovalHandler>  $handlers
     */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->register($handler);
        }
    }

    public function register(ApprovalHandler $handler): void
    {
        $this->handlers[$handler->type()->value] = $handler;
    }

    public function for(ApprovalType $type): ApprovalHandler
    {
        return $this->handlers[$type->value]
            ?? throw new InvalidArgumentException("No approval handler registered for [{$type->value}].");
    }
}

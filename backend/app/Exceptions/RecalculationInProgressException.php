<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** A statistics recalculation for the workspace is already queued or running. */
class RecalculationInProgressException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('messages.stats_recalculation_running'));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use RuntimeException;

/**
 * The request is no longer in a state that allows the attempted transition.
 */
class ApprovalStateException extends RuntimeException {}

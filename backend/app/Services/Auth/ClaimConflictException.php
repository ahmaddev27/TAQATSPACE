<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

/**
 * More than one unclaimed subscriber matches the SSO identity, so it cannot be
 * merged automatically. The conflict is already in the audit log for owner review.
 */
class ClaimConflictException extends RuntimeException {}

<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Models\User;

/**
 * Measures how many of the donor-required subscriber fields are filled.
 * Pure calculation: the caller supplies the MikroTik username it resolved.
 */
class ProfileCompletionService
{
    /** @var list<string> Required user columns, in wizard order. */
    public const USER_FIELDS = ['gender', 'education_level', 'specialization', 'employment_status'];

    public const MIKROTIK_FIELD = 'mikrotik_username';

    /**
     * @return array{percent: int, missing: list<string>}
     */
    public function completion(User $member, ?string $mikrotikUsername): array
    {
        $missing = [];

        foreach (self::USER_FIELDS as $field) {
            if ($member->getAttribute($field) === null) {
                $missing[] = $field;
            }
        }

        if ($mikrotikUsername === null || $mikrotikUsername === '') {
            $missing[] = self::MIKROTIK_FIELD;
        }

        $total = count(self::USER_FIELDS) + 1;

        return [
            'percent' => (int) round((($total - count($missing)) / $total) * 100),
            'missing' => $missing,
        ];
    }
}

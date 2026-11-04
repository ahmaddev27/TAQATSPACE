<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\Owner\ProfileCompletionService;
use PHPUnit\Framework\TestCase;

class ProfileCompletionServiceTest extends TestCase
{
    /** @param array<string, mixed> $attributes */
    private function member(array $attributes = []): User
    {
        return (new User)->forceFill($attributes);
    }

    public function test_empty_profile_is_zero_percent_with_every_field_missing(): void
    {
        $result = (new ProfileCompletionService)->completion($this->member(), null);

        $this->assertSame(0, $result['percent']);
        $this->assertSame(
            ['gender', 'education_level', 'specialization', 'employment_status', 'mikrotik_username'],
            $result['missing'],
        );
    }

    public function test_partial_profile_is_scored_per_field(): void
    {
        $member = $this->member(['gender' => 'male', 'employment_status' => 'student']);

        $result = (new ProfileCompletionService)->completion($member, 'ali');

        $this->assertSame(60, $result['percent']);
        $this->assertSame(['education_level', 'specialization'], $result['missing']);
    }

    public function test_blank_username_counts_as_missing(): void
    {
        $result = (new ProfileCompletionService)->completion($this->member(), '');

        $this->assertContains('mikrotik_username', $result['missing']);
    }
}

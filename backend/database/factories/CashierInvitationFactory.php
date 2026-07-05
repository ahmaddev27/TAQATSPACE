<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PosPermission;
use App\Models\CashierInvitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CashierInvitation>
 */
class CashierInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'invited_by' => User::factory()->owner(),
            'email' => fake()->unique()->userName().'@mail.ps',
            'name' => null,
            'token_hash' => hash('sha256', Str::random(48)),
            'permissions' => PosPermission::defaultsForCashier(),
            'expires_at' => now()->addDays(7),
        ];
    }

    /** Build the invitation for a KNOWN raw token so a test can accept it. */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_hash' => hash('sha256', $token),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}

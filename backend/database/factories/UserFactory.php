<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Seeders\GazaData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => GazaData::personName(),
            'email' => fake()->unique()->userName().'@mail.ps',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => GazaData::phone(),
            'role' => UserRole::Freelancer,
            'status' => UserStatus::Active,
            'specialty' => GazaData::specialty(),
            'bio' => null,
            'onboarding_completed_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'specialty' => null,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::WorkspaceOwner,
            'status' => UserStatus::Active,
            'specialty' => null,
            'documents' => [
                'license' => 'owner-docs/seed/license.pdf',
                'id_document' => 'owner-docs/seed/id.pdf',
            ],
        ]);
    }

    public function freelancer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Freelancer,
            'specialty' => GazaData::specialty(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UserStatus::Suspended,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
            'status' => UserStatus::PendingVerification,
        ]);
    }
}

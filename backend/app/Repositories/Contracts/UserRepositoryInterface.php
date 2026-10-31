<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    public function findBySsoSub(string $sub): ?User;

    /**
     * Unclaimed (no `claimed_at`) user with this email, if any.
     */
    public function findUnclaimedByEmail(string $email): ?User;

    /**
     * Up to two unclaimed users with this phone, enough to detect ambiguity.
     *
     * @return Collection<int, User>
     */
    public function findUnclaimedByPhone(string $phone): Collection;

    /**
     * Atomically claim an unclaimed user; false when it was claimed meanwhile.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function claim(User $user, array $attributes): bool;
}

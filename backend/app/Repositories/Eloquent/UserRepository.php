<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findById(string $id): ?User
    {
        return User::query()->find($id);
    }

    public function findBySsoSub(string $sub): ?User
    {
        return User::query()->where('sso_sub', $sub)->first();
    }

    public function findUnclaimedByEmail(string $email): ?User
    {
        return User::query()->whereNull('claimed_at')->where('email', $email)->first();
    }

    public function findUnclaimedByPhone(string $phone): Collection
    {
        return User::query()->whereNull('claimed_at')->where('phone', $phone)->limit(2)->get();
    }

    public function claim(User $user, array $attributes): bool
    {
        return User::query()
            ->whereKey($user->getKey())
            ->whereNull('claimed_at')
            ->update($attributes) === 1;
    }
}

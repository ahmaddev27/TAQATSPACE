<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    public function findBySsoSub(string $sub): ?User;
}

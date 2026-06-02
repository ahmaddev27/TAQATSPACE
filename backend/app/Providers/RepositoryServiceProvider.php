<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface → implementation bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
    ];
}

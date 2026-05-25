<?php

declare(strict_types=1);

namespace AndyDefer\Repository;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/repository.php', 'repository');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/repository.php' => config_path('repository.php'),
        ], 'repository-config');
    }
}

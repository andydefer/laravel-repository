<?php

// src/RepositoryServiceProvider.php

declare(strict_types=1);

namespace AndyDefer\Repository;

use AndyDefer\Repository\Configs\RepositoryConfig;
use AndyDefer\Repository\Contracts\Configs\RepositoryConfigInterface;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/repository.php',
            'repository'
        );

        $this->app->singleton(RepositoryConfig::class, function ($app) {
            return new RepositoryConfig($app['config']);
        });

        $this->app->bind(RepositoryConfigInterface::class, RepositoryConfig::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/repository.php' => config_path('repository.php'),
        ], 'repository-config');
    }
}

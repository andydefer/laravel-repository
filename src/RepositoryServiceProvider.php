<?php

declare(strict_types=1);

namespace AndyDefer\Repository;

use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Repository\Directives\MakeRepositoryDirective;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/repository.php', 'repository');

        $this->app->singleton(MakeRepositoryDirective::class, function ($app) {
            return new MakeRepositoryDirective(
                $app->make(DirectiveInteractionService::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/repository.php' => config_path('repository.php'),
        ], 'repository-config');
    }
}

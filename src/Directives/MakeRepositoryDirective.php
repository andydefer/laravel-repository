<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use Illuminate\Filesystem\Filesystem;

final class MakeRepositoryDirective extends AbstractDirective
{
    private Filesystem $files;

    public function __construct(
        DirectiveInteractionService $interaction,
        ?LaravelBootstrapper $laravelBootstrapper = null,
    ) {
        parent::__construct($interaction, $laravelBootstrapper);
        $this->files = new Filesystem;
    }

    public function getSignature(): string
    {
        return 'make-repository {name : The name of the repository (e.g., Users/UserRepository)} 
                       {--force : Overwrite existing files}';
    }

    public function getDescription(): string
    {
        return 'Create a new Repository class';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('repository-make');
        $aliases->add('create-repository');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $force = $this->hasOption('force');

        if ($name === null) {
            $this->error('Repository name is required.');

            return ExitCode::FAILURE;
        }

        $this->info("Creating repository: {$name}");

        if (! $this->createRepository($name, $force)) {
            return ExitCode::FAILURE;
        }

        $this->info("Repository '{$name}' created successfully!");

        return ExitCode::SUCCESS;
    }

    private function createRepository(string $name, bool $force): bool
    {
        $path = $this->getRepositoryPath($name);
        $namespace = $this->getRepositoryNamespace($name);
        $className = $this->getClassName($name);
        $modelClass = $this->getModelClass($name);
        $recordClass = $this->getRecordClassName($name);
        $recordNamespace = $this->getRecordNamespace($name);
        $filtersClass = $this->getFiltersClassName($name);
        $filtersNamespace = $this->getFiltersNamespace($name);

        if ($this->files->exists($path) && ! $force) {
            $this->error("Repository already exists at: {$path}");

            return false;
        }

        $stub = $this->getStub('repository.stub');
        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ class }}',
                '{{ model_class }}',
                '{{ record_class }}',
                '{{ record_namespace }}',
                '{{ filters_class }}',
                '{{ filters_namespace }}',
            ],
            [
                $namespace,
                $className,
                $modelClass,
                $recordClass,
                $recordNamespace,
                $filtersClass,
                $filtersNamespace,
            ],
            $stub
        );

        $this->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);

        return true;
    }

    private function getRepositoryPath(string $name): string
    {
        $basePath = app_path('Repositories');
        $segments = explode('/', $name);
        $className = array_pop($segments);

        if (! empty($segments)) {
            $basePath .= '/'.implode('/', $segments);
        }

        return "{$basePath}/{$className}.php";
    }

    private function getRepositoryNamespace(string $name): string
    {
        $segments = explode('/', $name);
        array_pop($segments);

        $baseNamespace = 'App\\Repositories';

        if (! empty($segments)) {
            $baseNamespace .= '\\'.implode('\\', $segments);
        }

        return $baseNamespace;
    }

    private function getClassName(string $name): string
    {
        $segments = explode('/', $name);

        return array_pop($segments);
    }

    private function getModelClass(string $name): string
    {
        $className = $this->getClassName($name);
        $modelName = str_replace('Repository', '', $className);

        return $modelName;
    }

    private function getRecordNamespace(string $name): string
    {
        $segments = explode('/', $name);
        array_pop($segments);

        $baseNamespace = 'App\\Records';

        if (! empty($segments)) {
            $baseNamespace .= '\\'.implode('\\', $segments);
        }

        return $baseNamespace;
    }

    private function getFiltersNamespace(string $name): string
    {
        $segments = explode('/', $name);
        array_pop($segments);

        $baseNamespace = 'App\\Records';

        if (! empty($segments)) {
            $baseNamespace .= '\\'.implode('\\', $segments);
        }

        return $baseNamespace;
    }

    private function getRecordClassName(string $name): string
    {
        $className = $this->getClassName($name);
        $modelName = str_replace('Repository', '', $className);

        return $modelName.'Record';
    }

    private function getFiltersClassName(string $name): string
    {
        $className = $this->getClassName($name);
        $modelName = str_replace('Repository', '', $className);

        return $modelName.'FiltersRecord';
    }

    private function getStub(string $name): string
    {
        $stubPath = __DIR__.'/../../stubs/'.$name;

        return $this->files->get($stubPath);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }
}

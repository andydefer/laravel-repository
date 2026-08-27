<?php

// src/Contracts/Configs/RepositoryConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\Repository\Contracts\Configs;

use AndyDefer\Repository\Contracts\EnumerableInterface;

interface RepositoryConfigInterface
{
    /**
     * Get the enum class for a specific table and column.
     *
     * @param  string  $table  The table name
     * @param  string  $column  The column name
     * @return class-string<EnumerableInterface>|null
     */
    public function getEnumCast(string $table, string $column): ?string;

    /**
     * Get all enum casts configuration.
     *
     * @return array<string, array<string, class-string<EnumerableInterface>>>
     */
    public function getEnumCasts(): array;
}

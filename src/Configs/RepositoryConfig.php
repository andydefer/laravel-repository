<?php

// src/Configs/RepositoryConfig.php

declare(strict_types=1);

namespace AndyDefer\Repository\Configs;

use AndyDefer\Repository\Contracts\Configs\RepositoryConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class RepositoryConfig implements RepositoryConfigInterface
{
    private const CONFIG_KEY = 'repository';

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getEnumCast(string $table, string $column): ?string
    {
        $enumCasts = $this->getEnumCasts();

        if (! isset($enumCasts[$table][$column])) {
            return null;
        }

        $enumClass = $enumCasts[$table][$column];

        if (! enum_exists($enumClass)) {
            return null;
        }

        return $enumClass;
    }

    public function getEnumCasts(): array
    {
        return $this->config->get(self::CONFIG_KEY.'.enum_casts', []);
    }
}

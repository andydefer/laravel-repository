<?php

// src/Casts/EnumCast.php

declare(strict_types=1);

namespace AndyDefer\Repository\Casts;

use AndyDefer\Repository\Contracts\Configs\RepositoryConfigInterface;
use AndyDefer\Repository\Contracts\EnumerableInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Eloquent cast for enum types.
 *
 * Converts string/int values from the database to enum instances and vice versa.
 * Uses the repository configuration to determine which enum class to use for each table/column.
 *
 * @implements CastsAttributes<EnumerableInterface, string|int>
 */
final class EnumCast implements CastsAttributes
{
    private RepositoryConfigInterface $config;

    public function __construct()
    {
        $this->config = app(RepositoryConfigInterface::class);
    }

    /**
     * Transform the attribute from the underlying database values.
     *
     * @param  Model  $model
     * @param  string|int|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function get($model, string $key, $value, array $attributes): ?EnumerableInterface
    {
        if ($value === null) {
            return null;
        }

        $table = $model->getTable();
        $enumClass = $this->config->getEnumCast($table, $key);

        if ($enumClass === null || ! enum_exists($enumClass)) {
            return null;
        }

        try {
            return $enumClass::tryFrom($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Transform the attribute to its underlying database values.
     *
     * @param  Model  $model
     * @param  EnumerableInterface|string|int|null  $value
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException
     */
    public function set($model, string $key, $value, array $attributes): string|int|null
    {
        if ($value === null) {
            return null;
        }

        $table = $model->getTable();

        if ($value instanceof EnumerableInterface) {
            return $value->getValue();
        }

        if (is_string($value) || is_int($value)) {
            $enumClass = $this->config->getEnumCast($table, $key);

            if ($enumClass !== null && enum_exists($enumClass)) {
                try {
                    $enum = $enumClass::tryFrom($value);

                    if ($enum instanceof EnumerableInterface) {
                        return $enum->getValue();
                    }

                    if ($enum !== null) {
                        if (property_exists($enum, 'value')) {
                            return $enum->value;
                        }

                        return $enum->name;
                    }
                } catch (\Exception) {
                    // Fall through to exception
                }
            }
        }

        throw new InvalidArgumentException(
            sprintf(
                'Invalid enum value for table "%s", column "%s". Expected instance of %s, or a valid string/int, got %s',
                $table,
                $key,
                EnumerableInterface::class,
                get_debug_type($value)
            )
        );
    }
}

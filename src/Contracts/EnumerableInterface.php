<?php

// src/Contracts/EnumerableInterface.php

declare(strict_types=1);

namespace AndyDefer\Repository\Contracts;

/**
 * Interface for enumerable types.
 *
 * Any enum implementing this interface can be used with the EnumCast.
 * This allows developers to define their own enums with custom methods.
 */
interface EnumerableInterface
{
    /**
     * Get the value of the enum (string or int).
     */
    public function getValue(): string|int;

    /**
     * Get all available cases.
     *
     * @return array<static>
     */
    public static function cases(): array;

    /**
     * Try to get a case by its value.
     */
    public static function tryFrom(string|int $value): ?static;
}

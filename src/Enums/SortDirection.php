<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Enums;

/**
 * Enum for sort direction in repository queries
 */
enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';

    /**
     * Check if the direction is ascending
     */
    public function isAsc(): bool
    {
        return $this === self::ASC;
    }

    /**
     * Check if the direction is descending
     */
    public function isDesc(): bool
    {
        return $this === self::DESC;
    }

    /**
     * Get the opposite direction
     */
    public function opposite(): self
    {
        return $this === self::ASC ? self::DESC : self::ASC;
    }

    /**
     * Get the SQL ORDER BY direction
     */
    public function toSql(): string
    {
        return $this->value;
    }
}

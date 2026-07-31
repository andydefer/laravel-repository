<?php

declare(strict_types=1);

namespace AndyDefer\Repository\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use InvalidArgumentException;

/**
 * Value Object for managing multiple cluster queries.
 *
 * This VO stores a collection of cluster queries where each key is the
 * JSON column name and the value is the cluster query expression.
 *
 * @example
 * $queries = new ClusterQueries([
 *     'metadata' => 'status=active & role=admin',
 *     'information' => 'color=blue'
 * ]);
 *
 * foreach ($queries->all() as $column => $query) {
 *     $repository->whereCluster($column, $query);
 * }
 */
final class ClusterQueries extends AbstractValueObject
{
    /**
     * The cluster queries.
     *
     * @var StrictAssociative<string, string>
     */
    private readonly StrictAssociative $queries;

    /**
     * Create a new ClusterQueries instance.
     *
     * @param  array<string, string>  $queries  Column name → query expression
     *
     * @throws InvalidArgumentException If a query is empty or column is invalid
     */
    public function __construct(array $queries = [])
    {
        foreach ($queries as $column => $query) {
            $this->validateColumn($column);
            $this->validateQuery($query);
        }

        $this->queries = new StrictAssociative($queries);
    }

    /**
     * Get the raw value (the StrictAssociative instance).
     */
    public function getValue(): StrictAssociative
    {
        return $this->queries;
    }

    /**
     * Get all queries as an array.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->queries->toArray();
    }

    /**
     * Check if a column has a query.
     */
    public function has(string $column): bool
    {
        return $this->queries->has($column);
    }

    /**
     * Get a query for a specific column.
     */
    public function get(string $column): ?string
    {
        return $this->queries->get($column);
    }

    /**
     * Check if there are any queries.
     */
    public function isEmpty(): bool
    {
        $array = $this->queries->toArray();

        return empty($array);
    }

    /**
     * Get the number of queries.
     */
    public function count(): int
    {
        return count($this->queries->toArray());
    }

    /**
     * Merge with another ClusterQueries instance.
     *
     * Queries from the other instance will override existing ones.
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->all(), $other->all()));
    }

    /**
     * Validate a column name.
     *
     * @throws InvalidArgumentException
     */
    private function validateColumn(string $column): void
    {
        if (trim($column) === '') {
            throw new InvalidArgumentException('Column name cannot be empty');
        }

        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) {
            throw new InvalidArgumentException(
                sprintf('Invalid column name "%s". Must contain only letters, numbers, underscores and dots.', $column)
            );
        }
    }

    /**
     * Validate a query expression.
     *
     * @throws InvalidArgumentException
     */
    private function validateQuery(string $query): void
    {
        if (trim($query) === '') {
            throw new InvalidArgumentException('Cluster query expression cannot be empty');
        }
    }
}

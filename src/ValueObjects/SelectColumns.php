<?php

declare(strict_types=1);

namespace AndyDefer\Repository\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use InvalidArgumentException;

/**
 * Value object representing columns to select in a database query.
 */
final class SelectColumns extends AbstractValueObject
{
    /**
     * @param  array<int, string>  $columns
     */
    public function __construct(
        private readonly array $columns
    ) {
        if (empty($this->columns)) {
            throw new InvalidArgumentException('Columns cannot be empty');
        }

        foreach ($this->columns as $column) {
            if (! is_string($column) || trim($column) === '') {
                throw new InvalidArgumentException('Each column must be a non-empty string');
            }

            if ($column === '*') {
                continue;
            }

            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
                throw new InvalidArgumentException("Invalid column name: {$column}");
            }
        }
    }

    /**
     * Creates an instance with all columns (*).
     */
    public static function all(): self
    {
        return new self(['*']);
    }

    /**
     * Returns the columns as an array.
     *
     * @return array<int, string>
     */
    public function toArray(): array
    {
        return $this->columns;
    }

    /**
     * Returns the raw value of the value object.
     *
     * @return StringTypedCollection Typed collection of column names
     */
    public function getValue(): StringTypedCollection
    {
        $collection = new StringTypedCollection;

        foreach ($this->columns as $column) {
            $collection->add($column);
        }

        return $collection;
    }

    /**
     * Checks if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->columns);
    }

    /**
     * Checks if all columns are selected.
     */
    public function isAll(): bool
    {
        return $this->columns === ['*'];
    }

    /**
     * Adds one or more columns.
     */
    public function add(string ...$columns): self
    {
        if ($this->isAll()) {
            return $this;
        }

        $newColumns = array_merge($this->columns, $columns);

        return new self(array_unique($newColumns));
    }

    /**
     * Checks if a column is present.
     */
    public function has(string $column): bool
    {
        return in_array($column, $this->columns, true);
    }

    /**
     * Returns the number of columns.
     */
    public function count(): int
    {
        return count($this->columns);
    }

    /**
     * Default value for the HasPropertiesAccess trait.
     */
    protected function getDefaultValue(string $propertyName): mixed
    {
        return match ($propertyName) {
            'columns' => ['*'],
            default => null,
        };
    }
}

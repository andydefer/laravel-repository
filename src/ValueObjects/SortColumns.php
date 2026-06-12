<?php

declare(strict_types=1);

namespace AndyDefer\Repository\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use InvalidArgumentException;

/**
 * Value object representing sort columns in a database query.
 *
 * Format: "column:direction|column:direction"
 * Example: "created_at:desc|id:desc"
 *
 * If direction is omitted, defaults to 'desc'.
 */
final class SortColumns extends AbstractValueObject
{
    private StrictDataObject $sortDefinition;

    /**
     * @param  string  $sortDefinition  Format: "column:direction|column:direction"
     */
    public function __construct(string $sortDefinition)
    {
        if (trim($sortDefinition) === '') {
            throw new InvalidArgumentException('Sort definition cannot be empty');
        }

        $this->sortDefinition = $this->parseSortDefinition($sortDefinition);
    }

    /**
     * Parse the sort definition string into a StrictDataObject.
     *
     * @param  string  $definition  Format: "column:direction|column:direction"
     * @return StrictDataObject Format: ['column1' => 'desc', 'column2' => 'asc']
     */
    private function parseSortDefinition(string $definition): StrictDataObject
    {
        $parts = explode('|', $definition);
        $sortMap = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // Split column and direction
            $subParts = explode(':', $part);
            $column = trim($subParts[0]);

            // Direction: default to 'desc' if not specified
            $direction = isset($subParts[1]) ? strtolower(trim($subParts[1])) : 'desc';

            // Validate direction
            if (! in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException(
                    "Invalid sort direction '{$direction}'. Must be 'asc' or 'desc'"
                );
            }

            // Validate column name
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw new InvalidArgumentException("Invalid column name: {$column}");
            }

            $sortMap[$column] = $direction;
        }

        if (empty($sortMap)) {
            throw new InvalidArgumentException('No valid sort columns provided');
        }

        return new StrictDataObject($sortMap);
    }

    /**
     * Returns the raw value of the value object.
     *
     * @return StrictDataObject Format: ['column1' => 'desc', 'column2' => 'asc']
     */
    public function getValue(): StrictDataObject
    {
        return $this->sortDefinition;
    }

    /**
     * Get all sort columns as an array.
     *
     * @return array<string, string> Format: ['column' => 'direction']
     */
    public function toArray(): array
    {
        return $this->sortDefinition->toArray();
    }

    /**
     * Check if the sort definition is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->toArray()) === 0;
    }

    /**
     * Get the number of sort columns.
     */
    public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * Default value for the HasPropertiesAccess trait.
     */
    protected function getDefaultValue(string $propertyName): mixed
    {
        return match ($propertyName) {
            'sortDefinition' => new StrictDataObject([]),
            default => null,
        };
    }
}

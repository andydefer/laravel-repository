<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\Repository\Enums\SortDirection;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use AndyDefer\Repository\ValueObjects\SelectColumns;

/**
 * @psalm-immutable
 */
final class PaginateRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly ?string $sortBy = null,
        public readonly SortDirection $sortDir = SortDirection::ASC,
        public readonly AbstractRecord $filters = new EmptyRecord,
        public readonly SelectColumns $columns = new SelectColumns(['*']),
        public readonly ?ClusterQueries $clusterQueries = null,
    ) {}
}

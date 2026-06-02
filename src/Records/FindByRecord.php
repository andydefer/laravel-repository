<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\Repository\Enums\SortDirection;
use AndyDefer\Repository\ValueObjects\SelectColumns;

final class FindByRecord extends AbstractRecord
{
    public function __construct(
        public readonly AbstractRecord $filters = new EmptyRecord,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly SortDirection $sortDir = SortDirection::ASC,
        public readonly SelectColumns $columns = new SelectColumns(['*']),
    ) {}
}

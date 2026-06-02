<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;

final class PaginateRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
        public readonly AbstractRecord $filters = new EmptyRecord,
        public readonly array $columns = ['*'],
    ) {}
}

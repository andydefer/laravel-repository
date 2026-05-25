<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Records;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\EmptyRecord;
use AndyDefer\Records\Recordable;

final class PaginateRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
        public readonly Recordable $filters = new EmptyRecord,
        public readonly array $columns = ['*'],
    ) {}
}

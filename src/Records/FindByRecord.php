<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Records;

use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\EmptyRecord;
use AndyDefer\Records\Recordable;

final class FindByRecord extends AbstractRecord
{
    public function __construct(
        public readonly Recordable $filters = new EmptyRecord,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
        public readonly array $columns = ['*'],
    ) {}
}

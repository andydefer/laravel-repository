<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;

final class FindByRecord extends AbstractRecord
{
    public function __construct(
        public readonly AbstractRecord $filters = new EmptyRecord,
        public readonly ?int $limit = null,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
        public readonly array $columns = ['*'],
    ) {}
}

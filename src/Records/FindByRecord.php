<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;

final class FindByRecord extends AbstractRecord
{
    public function __construct(
        public readonly AbstractRecord $filters = new EmptyRecord,
        public readonly ?int $limit = null,
        public readonly ?SortColumns $sortBy = null,
        public readonly SelectColumns $columns = new SelectColumns(['*']),
    ) {}
}

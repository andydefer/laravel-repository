<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TRecord of \AndyDefer\Records\AbstractRecord
 */
final class RepositoryInfoRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $modelClass,
        public readonly string $recordClass,
    ) {}
}

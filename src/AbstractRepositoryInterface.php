<?php

declare(strict_types=1);

namespace AndyDefer\Repository;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\Records\RepositoryInfoRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @template TModel of Model
 * @template TRecord of AbstractRecord
 */
interface AbstractRepositoryInterface
{
    /**
     * @return RepositoryInfoRecord<TModel, TRecord>
     */
    public function info(): RepositoryInfoRecord;

    /**
     * @param  TRecord  $record
     * @return TModel
     */
    public function create(AbstractRecord $record): Model;

    /**
     * @return TModel|null
     */
    public function find(int $id): ?Model;

    /**
     * @return Collection<int, TModel>
     */
    public function findBy(FindByRecord $record): Collection;

    /**
     * @param  TRecord  $record
     * @return TModel
     *
     * @throws \RuntimeException
     */
    public function update(int $id, AbstractRecord $record): Model;

    public function delete(int $id): bool;

    public function count(?AbstractRecord $criteria = null): int;

    public function exists(AbstractRecord $criteria): bool;

    /**
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(PaginateRecord $record): LengthAwarePaginator;

    public function deleteBulk(AbstractRecord $criteria): int;
}

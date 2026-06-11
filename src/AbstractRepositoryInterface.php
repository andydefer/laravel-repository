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
     * Create a new model from raw array data.
     * Use this when you already have an array ready for creation.
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function createRaw(array $data): Model;

    /**
     * @return TModel|null
     */
    public function find(int $id): ?Model;

    /**
     * Find a model by its ID including soft deleted ones.
     *
     * @return TModel|null
     */
    public function findWithTrashed(int $id): ?Model;

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

    /**
     * Update a model with raw array data.
     * Use this when you need to set fields to NULL or use database-specific values.
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function updateRaw(int $id, array $data): Model;

    /**
     * Delete a model by ID (soft delete if SoftDeletes trait is used).
     */
    public function delete(int $id): bool;

    /**
     * Restore a soft-deleted model by ID.
     *
     * @return bool True if restored, false if not found or not soft deleted
     */
    public function restore(int $id): bool;

    /**
     * Force delete a model by ID (hard delete, even if soft deleted).
     *
     * @return bool True if force deleted, false if not found
     */
    public function forceDelete(int $id): bool;

    public function count(?AbstractRecord $criteria = null): int;

    public function exists(AbstractRecord $criteria): bool;

    /**
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(PaginateRecord $record): LengthAwarePaginator;

    public function deleteBulk(AbstractRecord $criteria): int;

    /**
     * Force delete multiple models matching the given criteria (hard delete, even if soft deleted).
     *
     * @return int Number of records force deleted
     */
    public function forceDeleteBulk(AbstractRecord $criteria): int;
}

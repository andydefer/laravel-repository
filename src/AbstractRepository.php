<?php

declare(strict_types=1);

namespace AndyDefer\Repository;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\Repository\Exceptions\ModelNotFoundException;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\Records\RepositoryInfoRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @template TModel of Model
 * @template TRecord of AbstractRecord
 *
 * @implements AbstractRepositoryInterface<TModel, TRecord>
 */
abstract class AbstractRepository implements AbstractRepositoryInterface
{
    protected Model $model;

    protected string $modelClass;

    protected string $recordClass;

    /**
     * @param  class-string<TModel>  $modelClass
     * @param  class-string<TRecord>  $recordClass
     */
    public function __construct(string $modelClass, string $recordClass)
    {
        $this->modelClass = $modelClass;
        $this->recordClass = $recordClass;
        $this->model = new $modelClass;
    }

    /**
     * Get repository information.
     *
     * @return RepositoryInfoRecord<TModel, TRecord>
     */
    public function info(): RepositoryInfoRecord
    {
        return new RepositoryInfoRecord(
            modelClass: $this->modelClass,
            recordClass: $this->recordClass,
        );
    }

    /**
     * Create a new model from a record.
     *
     * @param  TRecord  $record
     * @return TModel
     */
    public function create(AbstractRecord $record): Model
    {
        $data = $record->toArrayWithoutNulls();
        /** @var TModel $model */
        $model = $this->model->newQuery()->create($data);

        return $model;
    }

    /**
     * {@inheritDoc}
     */
    public function createRaw(array $data): Model
    {
        /** @var TModel $model */
        $model = $this->model->newQuery()->create($data);

        return $model;
    }

    /**
     * Find a model by its ID.
     *
     * @return TModel|null
     */
    public function find(int $id): ?Model
    {
        /** @var TModel|null $model */
        $model = $this->model->newQuery()->find($id);

        return $model;
    }

    /**
     * Find a model by its ID including soft deleted ones.
     *
     * @return TModel|null
     */
    public function findWithTrashed(int $id): ?Model
    {
        $query = $this->model->newQuery();

        if ($this->usesSoftDeletes()) {
            $query = $query->withTrashed();
        }

        /** @var TModel|null $model */
        $model = $query->find($id);

        return $model;
    }

    /**
     * Find models matching the given criteria.
     *
     * @return Collection<int, TModel>
     */
    public function findBy(FindByRecord $record): Collection
    {
        $query = $this->buildQuery($record->filters);

        $columns = $record->columns->toArray();
        $query->select($columns);

        if ($record->sortBy !== null) {
            $query->orderBy($record->sortBy, $record->sortDir->toSql());
        }

        if ($record->limit !== null) {
            $query->limit($record->limit);
        }

        /** @var Collection<int, TModel> $result */
        $result = $query->get();

        return $result;
    }

    /**
     * Update a model by ID with record data.
     *
     * @param  TRecord  $record
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, AbstractRecord $record): Model
    {
        /** @var TModel|null $model */
        $model = $this->model->newQuery()->find($id);

        if ($model === null) {
            throw ModelNotFoundException::create($this->modelClass, $id);
        }

        $data = array_filter(
            $record->toArrayWithoutNulls(),
            fn ($value) => $value !== null
        );

        if (! empty($data)) {
            $model->update($data);
            $model->refresh();
        }

        return $model;
    }

    /**
     * Update a model with raw array data.
     * Use this when you need to set fields to NULL or use database-specific values.
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function updateRaw(int $id, array $data): Model
    {
        /** @var TModel|null $model */
        $model = $this->model->newQuery()->find($id);

        if ($model === null) {
            throw ModelNotFoundException::create($this->modelClass, $id);
        }

        if (! empty($data)) {
            $model->update($data);
            $model->refresh();
        }

        return $model;
    }

    /**
     * Delete a model by ID (soft delete if SoftDeletes trait is used).
     *
     * @return bool True if deleted, false if not found
     */
    public function delete(int $id): bool
    {
        /** @var TModel|null $model */
        $model = $this->model->newQuery()->find($id);

        if ($model === null) {
            return false;
        }

        return (bool) $model->delete();
    }

    /**
     * Restore a soft-deleted model by ID.
     *
     * @return bool True if restored, false if not found or not soft deleted
     */
    public function restore(int $id): bool
    {
        $model = $this->findWithTrashed($id);

        if ($model === null) {
            return false;
        }

        if (! $this->usesSoftDeletes() || ! $model->trashed()) {
            return false;
        }

        return (bool) $model->restore();
    }

    /**
     * Force delete a model by ID (hard delete, even if soft deleted).
     *
     * @return bool True if force deleted, false if not found
     */
    public function forceDelete(int $id): bool
    {
        $model = $this->findWithTrashed($id);

        if ($model === null) {
            return false;
        }

        return (bool) $model->forceDelete();
    }

    /**
     * Force delete multiple models matching the given criteria (hard delete, even if soft deleted).
     *
     * @return int Number of records force deleted
     */
    public function forceDeleteBulk(AbstractRecord $criteria): int
    {
        $query = $this->buildQuery($criteria);

        if ($this->usesSoftDeletes()) {
            return $query->forceDelete();
        }

        return $query->delete();
    }

    /**
     * Count models matching the given criteria.
     */
    public function count(?AbstractRecord $criteria = null): int
    {
        if ($criteria === null) {
            return $this->model->newQuery()->count();
        }

        return $this->buildQuery($criteria)->count();
    }

    /**
     * Check if any model matches the given criteria.
     */
    public function exists(AbstractRecord $criteria): bool
    {
        return $this->buildQuery($criteria)->exists();
    }

    /**
     * Paginate results matching the given criteria.
     *
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(PaginateRecord $record): LengthAwarePaginator
    {
        $query = $this->buildQuery($record->filters);

        $columns = $record->columns->toArray();

        if ($record->sortBy !== null) {
            $query->orderBy($record->sortBy, $record->sortDir->toSql());
        }

        /** @var LengthAwarePaginator<TModel> $result */
        $result = $query->paginate(
            perPage: $record->perPage,
            columns: $columns,
            pageName: 'page',
            page: $record->page
        );

        return $result;
    }

    /**
     * Delete multiple models matching the given criteria.
     *
     * @return int Number of deleted records
     */
    public function deleteBulk(AbstractRecord $criteria): int
    {
        return $this->buildQuery($criteria)->delete();
    }

    /**
     * Build the query with filters from a Record.
     *
     * @return Builder<TModel>
     */
    protected function buildQuery(AbstractRecord $filters): Builder
    {
        $query = $this->model->newQuery();

        if ($filters instanceof EmptyRecord) {
            return $query;
        }

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply filters to the query.
     * Override this method in concrete repositories.
     *
     * @param  Builder<TModel>  $query
     */
    abstract protected function applyFilters(Builder $query, AbstractRecord $filters): void;

    /**
     * Check if the model uses the SoftDeletes trait.
     */
    private function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass));
    }
}

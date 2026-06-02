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
     * @return TModel|null
     */
    public function find(int $id): ?Model
    {
        /** @var TModel|null $model */
        $model = $this->model->newQuery()->find($id);

        return $model;
    }

    /**
     * @return Collection<int, TModel>
     */
    public function findBy(FindByRecord $record): Collection
    {
        $query = $this->buildQuery($record->filters);

        if ($record->sortBy !== null) {
            $query->orderBy($record->sortBy, $record->sortDir);
        }

        if ($record->limit !== null) {
            $query->limit($record->limit);
        }

        /** @var Collection<int, TModel> $result */
        $result = $query->get($record->columns);

        return $result;
    }

    /**
     * @param  TRecord  $record
     * @return TModel
     *
     * @throws \RuntimeException
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

    public function delete(int $id): bool
    {
        /** @var TModel|null $model */
        $model = $this->model->newQuery()->find($id);

        if ($model === null) {
            return false;
        }

        return (bool) $model->delete();
    }

    public function count(?AbstractRecord $criteria = null): int
    {
        if ($criteria === null) {
            return $this->model->newQuery()->count();
        }

        return $this->buildQuery($criteria)->count();
    }

    public function exists(AbstractRecord $criteria): bool
    {
        return $this->buildQuery($criteria)->exists();
    }

    /**
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(PaginateRecord $record): LengthAwarePaginator
    {
        $query = $this->buildQuery($record->filters);

        if ($record->sortBy !== null) {
            $query->orderBy($record->sortBy, $record->sortDir);
        }

        /** @var LengthAwarePaginator<TModel> $result */
        $result = $query->paginate(
            perPage: $record->perPage,
            columns: $record->columns,
            pageName: 'page',
            page: $record->page
        );

        return $result;
    }

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
}

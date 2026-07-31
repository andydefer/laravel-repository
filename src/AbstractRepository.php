<?php

declare(strict_types=1);

namespace AndyDefer\Repository;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\Repository\Exceptions\ModelNotFoundException;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\Records\RepositoryInfoRecord;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Abstract repository providing CRUD operations with type-safe records.
 *
 * This repository serves as a foundation for database interactions using
 * immutable records for data transfer and filtering. It handles automatic
 * mapping between records and Eloquent models while providing consistent
 * CRUD operations across different entity types.
 *
 * @template TModel of Model
 * @template TRecord of AbstractRecord
 *
 * @implements AbstractRepositoryInterface<TModel, TRecord>
 */
abstract class AbstractRepository implements AbstractRepositoryInterface
{
    /**
     * The Eloquent model instance.
     *
     * @var TModel
     */
    protected Model $model;

    /**
     * The Eloquent model class name.
     *
     * @var class-string<TModel>
     */
    protected string $modelClass;

    /**
     * The record class name used for data transfer.
     *
     * @var class-string<TRecord>
     */
    protected string $recordClass;

    /**
     * Applied cluster filters for JSON column queries.
     *
     * @var array<array{column: string, query: string}>
     */
    protected array $clusterFilters = [];

    /**
     * Create a new repository instance.
     *
     * @param  class-string<TModel>  $modelClass  The Eloquent model class
     * @param  class-string<TRecord>  $recordClass  The record class for data transfer
     */
    public function __construct(string $modelClass, string $recordClass)
    {
        $this->modelClass = $modelClass;
        $this->recordClass = $recordClass;
        $this->model = new $modelClass;
    }

    /**
     * Apply a cluster filter to the repository query.
     *
     * Cluster filters allow complex JSON column queries using a specialized
     * query language. Supports conditions, aggregations, and nested paths.
     *
     * @param  string  $column  The JSON column containing cluster data
     * @param  string  $query  The cluster query expression
     *
     * @example
     * // Simple equality condition
     * $repository->whereCluster('metadata', 'status=active');
     *
     * // Combined conditions
     * $repository->whereCluster('metadata', 'status=active & age>25');
     *
     * // Array path conditions
     * $repository->whereCluster('metadata', 'addresses[city=Kinshasa]');
     *
     * // Aggregate functions
     * $repository->whereCluster('metadata', 'COUNT(addresses) > 2');
     */
    public function whereCluster(string $column, string $query): self
    {
        $this->clusterFilters[] = [
            'column' => $column,
            'query' => $query,
        ];

        return $this;
    }

    /**
     * Apply multiple cluster filters at once.
     *
     * @param  array<string, string>  $queries  Column name → query expression
     *
     * @example
     * $repository->whereClusters([
     *     'metadata' => 'status=active & role=admin',
     *     'information' => 'color=blue'
     * ]);
     */
    public function whereClusters(array $queries): self
    {
        foreach ($queries as $column => $query) {
            $this->whereCluster($column, $query);
        }

        return $this;
    }

    /**
     * Apply cluster filters from a ClusterQueries VO.
     *
     * @param  ClusterQueries  $queries  The cluster queries to apply
     */
    public function whereClusterQueries(ClusterQueries $queries): self
    {
        foreach ($queries->all() as $column => $query) {
            $this->whereCluster($column, $query);
        }

        return $this;
    }

    /**
     * Clear all applied cluster filters.
     */
    public function clearClusterFilters(): self
    {
        $this->clusterFilters = [];

        return $this;
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
     * @param  TRecord  $record  The record containing the data
     * @return TModel The created model instance
     */
    public function create(AbstractRecord $record): Model
    {
        $data = $record->toArrayWithoutNulls();
        /** @var TModel $model */
        $model = $this->model->newQuery()->create($data);

        return $model;
    }

    /**
     * Create a new model from raw array data.
     *
     * Use this method when you need to create a model with data that doesn't
     * conform to a record structure, or when you need more control over
     * the creation process.
     *
     * @param  array<string, mixed>  $data  The raw data for creation
     * @return TModel The created model instance
     */
    public function createRaw(array $data): Model
    {
        /** @var TModel $model */
        $model = $this->model->newQuery()->create($data);

        return $model;
    }

    /**
     * Find a model by its primary key.
     *
     * @param  int|string  $id  The model identifier
     * @return TModel|null The found model or null if not found
     */
    public function find(int|string $id): ?Model
    {
        $query = $this->buildFilteredQuery();
        /** @var TModel|null $model */
        $model = $query->find($id);

        return $model;
    }

    /**
     * Find a model by its primary key including soft-deleted ones.
     *
     * @param  int|string  $id  The model identifier
     * @return TModel|null The found model or null if not found
     */
    public function findWithTrashed(int|string $id): ?Model
    {
        $query = $this->buildFilteredQuery();

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
     * @param  FindByRecord  $record  The search criteria including filters,
     *                                sorting, and pagination limits
     * @return Collection<int, TModel> Collection of found models
     */
    public function findBy(FindByRecord $record): Collection
    {
        $query = $this->buildQuery($record->filters);

        // Apply cluster queries from the FindByRecord
        $query = $this->applyClusterQueries($query, $record->clusterQueries);

        $this->applySelectColumns($query, $record);
        $this->applySorting($query, $record);
        $this->applyLimit($query, $record);

        /** @var Collection<int, TModel> $result */
        $result = $query->get();

        return $result;
    }

    /**
     * Update a model by ID using a record.
     *
     * @param  int|string  $id  The model identifier
     * @param  TRecord  $record  The record containing the update data
     * @return TModel The updated model instance
     *
     * @throws ModelNotFoundException When the model is not found
     */
    public function update(int|string $id, AbstractRecord $record): Model
    {
        $model = $this->findOrFail($id);
        $data = $this->extractNonEmptyRecordData($record);

        if (! empty($data)) {
            $model->update($data);
            $model->refresh();
        }

        return $model;
    }

    /**
     * Update a model by ID using raw array data.
     *
     * Use this method when you need to set fields to NULL or use
     * database-specific values that aren't supported by records.
     *
     * @param  int|string  $id  The model identifier
     * @param  array<string, mixed>  $data  The raw update data
     * @return TModel The updated model instance
     *
     * @throws ModelNotFoundException When the model is not found
     */
    public function updateRaw(int|string $id, array $data): Model
    {
        $model = $this->findOrFail($id);

        if (! empty($data)) {
            $model->update($data);
            $model->refresh();
        }

        return $model;
    }

    /**
     * Delete a model by ID.
     *
     * If the model uses SoftDeletes, this will perform a soft delete.
     *
     * @param  int|string  $id  The model identifier
     * @return bool True if deleted successfully, false if not found
     */
    public function delete(int|string $id): bool
    {
        $model = $this->find($id);

        if ($model === null) {
            return false;
        }

        return (bool) $model->delete();
    }

    /**
     * Restore a soft-deleted model by ID.
     *
     * @param  int|string  $id  The model identifier
     * @return bool True if restored, false if not found or not soft-deleted
     */
    public function restore(int|string $id): bool
    {
        $model = $this->findWithTrashed($id);

        if ($model === null) {
            return false;
        }

        if (! $this->isTrashed($model)) {
            return false;
        }

        return (bool) $model->restore();
    }

    /**
     * Permanently delete a model by ID.
     *
     * This will remove the model from the database regardless of whether
     * it uses SoftDeletes.
     *
     * @param  int|string  $id  The model identifier
     * @return bool True if force deleted, false if not found
     */
    public function forceDelete(int|string $id): bool
    {
        $model = $this->findWithTrashed($id);

        if ($model === null) {
            return false;
        }

        return (bool) $model->forceDelete();
    }

    /**
     * Permanently delete multiple models matching the given criteria.
     *
     * @param  AbstractRecord  $criteria  The criteria to match
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
     *
     * @param  AbstractRecord|null  $criteria  Optional criteria to filter
     * @return int The number of matching models
     */
    public function count(?AbstractRecord $criteria = null): int
    {
        if ($criteria === null) {
            return $this->buildFilteredQuery()->count();
        }

        return $this->buildQuery($criteria)->count();
    }

    /**
     * Check if any model matches the given criteria.
     *
     * @param  AbstractRecord  $criteria  The criteria to check
     * @return bool True if at least one matching model exists
     */
    public function exists(AbstractRecord $criteria): bool
    {
        return $this->buildQuery($criteria)->exists();
    }

    /**
     * Paginate results matching the given criteria.
     *
     * @param  PaginateRecord  $record  The pagination configuration
     * @return LengthAwarePaginator<TModel> The paginated results
     */
    public function paginate(PaginateRecord $record): LengthAwarePaginator
    {
        $query = $this->buildQuery($record->filters);

        // Apply cluster queries from the PaginateRecord
        $query = $this->applyClusterQueries($query, $record->clusterQueries);

        if ($record->sortBy !== null) {
            $query->orderBy($record->sortBy, $record->sortDir->toSql());
        }

        /** @var LengthAwarePaginator<TModel> $result */
        $result = $query->paginate(
            perPage: $record->perPage,
            columns: $record->columns->toArray(),
            pageName: 'page',
            page: $record->page
        );

        return $result;
    }

    /**
     * Delete multiple models matching the given criteria.
     *
     * @param  AbstractRecord  $criteria  The criteria to match
     * @return int Number of deleted records
     */
    public function deleteBulk(AbstractRecord $criteria): int
    {
        return $this->buildQuery($criteria)->delete();
    }

    /**
     * Build a query with filters from a record and applied cluster filters.
     *
     * @param  AbstractRecord  $filters  The filter criteria
     * @return Builder<TModel> The built query builder
     */
    protected function buildQuery(AbstractRecord $filters): Builder
    {
        $query = $this->buildFilteredQuery();

        if ($filters instanceof EmptyRecord) {
            return $query;
        }

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Build a query with all applied cluster filters.
     *
     * @return Builder<TModel> The query builder with cluster filters applied
     */
    protected function buildFilteredQuery(): Builder
    {
        $query = $this->model->newQuery();

        foreach ($this->clusterFilters as $filter) {
            $query->whereCluster($filter['column'], $filter['query']);
        }

        return $query;
    }

    /**
     * Apply filters to the query.
     *
     * Override this method in concrete repositories to apply custom
     * filter logic for specific record fields.
     *
     * @param  Builder<TModel>  $query  The query builder
     * @param  AbstractRecord  $filters  The filter criteria
     */
    abstract protected function applyFilters(Builder $query, AbstractRecord $filters): void;

    /**
     * Apply cluster queries from a VO to a query builder.
     *
     * @param  Builder<TModel>  $query  The query builder
     * @param  ClusterQueries|null  $clusterQueries  The cluster queries to apply
     * @return Builder<TModel>
     */
    protected function applyClusterQueries(Builder $query, ?ClusterQueries $clusterQueries): Builder
    {
        if ($clusterQueries === null) {
            return $query;
        }

        foreach ($clusterQueries->all() as $column => $queryExpression) {
            $query->whereCluster($column, $queryExpression);
        }

        return $query;
    }

    /**
     * Find a model by ID or throw an exception.
     *
     * @param  int|string  $id  The model identifier
     * @return TModel The found model
     *
     * @throws ModelNotFoundException When the model is not found
     */
    protected function findOrFail(int|string $id): Model
    {
        $model = $this->find($id);

        if ($model === null) {
            throw ModelNotFoundException::create($this->modelClass, $id);
        }

        return $model;
    }

    /**
     * Check if the model uses the SoftDeletes trait.
     *
     * @return bool True if SoftDeletes is used
     */
    protected function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->modelClass));
    }

    /**
     * Check if a model is trashed (soft-deleted).
     *
     * @param  Model  $model  The model to check
     * @return bool True if the model is trashed
     */
    protected function isTrashed(Model $model): bool
    {
        if (! $this->usesSoftDeletes()) {
            return false;
        }

        /** @var Model&SoftDeletes $model */
        return $model->trashed();
    }

    /**
     * Extract non-empty data from a record.
     *
     * @param  AbstractRecord  $record  The record to extract from
     * @return array<string, mixed> The extracted data without null values
     */
    protected function extractNonEmptyRecordData(AbstractRecord $record): array
    {
        return array_filter(
            $record->toArrayWithoutNulls(),
            fn ($value): bool => $value !== null
        );
    }

    /**
     * Apply selected columns to the query.
     *
     * @param  Builder<TModel>  $query  The query builder
     * @param  FindByRecord  $record  The search record
     */
    protected function applySelectColumns(Builder $query, FindByRecord $record): void
    {
        $query->select($record->columns->toArray());
    }

    /**
     * Apply sorting to the query.
     *
     * @param  Builder<TModel>  $query  The query builder
     * @param  FindByRecord  $record  The search record
     */
    protected function applySorting(Builder $query, FindByRecord $record): void
    {
        if ($record->sortBy === null || $record->sortBy->isEmpty()) {
            return;
        }

        foreach ($record->sortBy->toArray() as $column => $direction) {
            $query->orderBy($column, $direction);
        }
    }

    /**
     * Apply limit to the query.
     *
     * @param  Builder<TModel>  $query  The query builder
     * @param  FindByRecord  $record  The search record
     */
    protected function applyLimit(Builder $query, FindByRecord $record): void
    {
        if ($record->limit !== null) {
            $query->limit($record->limit);
        }
    }

    protected function safeDecodeJson(string $json): ?array
    {
        $data = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    protected function detectDriver(): DatabaseDriver
    {
        $driverName = DB::connection()->getDriverName();

        return match ($driverName) {
            'mysql' => DatabaseDriver::MYSQL,
            'pgsql' => DatabaseDriver::PGSQL,
            'sqlite' => DatabaseDriver::SQLITE,
            default => throw new \RuntimeException(
                sprintf('Unsupported database driver: "%s". Supported drivers: mysql, pgsql, sqlite', $driverName)
            ),
        };
    }
}

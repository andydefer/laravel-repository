<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Repositories;

use AndyDefer\Records\Recordable;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Tests\Fixtures\Models\TestUser;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserFiltersRecord;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserRecord;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends AbstractRepository<TestUser, TestUserRecord>
 */
final class TestUserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(TestUser::class, TestUserRecord::class);
    }

    protected function applyFilters(Builder $query, Recordable $filters): void
    {
        if (! $filters instanceof TestUserFiltersRecord) {
            return;
        }

        if ($filters->name !== null) {
            $query->where('name', 'like', '%'.$filters->name.'%');
        }

        if ($filters->email !== null) {
            $query->where('email', 'like', '%'.$filters->email.'%');
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        if ($filters->role !== null) {
            $query->where('role', $filters->role);
        }

        if ($filters->grade !== null) {
            $query->where('grade', $filters->grade);
        }
    }
}

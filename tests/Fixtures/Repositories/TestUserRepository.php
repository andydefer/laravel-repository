<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Tests\Fixtures\Models\TestUser;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserFiltersRecord;
use AndyDefer\Repository\Tests\Fixtures\Records\TestUserRecord;
use Illuminate\Database\Eloquent\Builder;

final class TestUserRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(TestUser::class, TestUserRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
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

        // ✅ Filtre cluster_query
        if ($filters->cluster_query !== null) {
            $query->whereCluster('metadata', $filters->cluster_query);
        }
    }
}

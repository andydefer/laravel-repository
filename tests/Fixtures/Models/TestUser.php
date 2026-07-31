<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Models;

use AndyDefer\LaravelCluster\Casts\ClusterCast;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\Repository\Tests\Fixtures\Enums\TestUserStatus;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'role',
        'grade',
        'metadata',
        'preferences',
    ];

    protected function casts(): array
    {
        return [
            'status' => TestUserStatus::class,
            'role' => TestUserRole::class,
            'grade' => TestUserGrade::class,
            'metadata' => ClusterCast::class,
            'preferences' => ClusterCast::class,
        ];
    }
}

<?php

// tests/Fixtures/Models/TestProduct.php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Models;

use AndyDefer\LaravelCluster\Casts\ClusterCast;
use AndyDefer\Repository\Casts\EnumCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestProduct extends Model
{
    use SoftDeletes;

    protected $table = 'test_products';

    protected $fillable = [
        'name',
        'price',
        'stock',
        'status',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'float',
        'stock' => 'integer',
        'status' => EnumCast::class,
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
        'metadata' => ClusterCast::class,
    ];
}

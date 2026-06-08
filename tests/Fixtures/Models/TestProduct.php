<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Models;

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
        'is_active',
    ];

    protected $casts = [
        'price' => 'float',
        'stock' => 'integer',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];
}

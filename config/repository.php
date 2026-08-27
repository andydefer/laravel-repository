<?php

// config/repository.php

return [
    /*
    |--------------------------------------------------------------------------
    | Enum Casts
    |--------------------------------------------------------------------------
    |
    | Define enum casts for specific tables and columns.
    | Each entry maps a table name and column to an enum class.
    |
    | The enum class must implement EnumerableInterface.
    |
    | Example:
    | 'enum_casts' => [
    |     'likes' => [
    |         'type' => AndyDefer\LaravelLikes\Enums\LikeType::class,
    |     ],
    |     'signalements' => [
    |         'type' => App\Enums\SignalementType::class,
    |         'status' => App\Enums\SignalementStatus::class,
    |     ],
    | ],
    |
    */
    'enum_casts' => [],
];

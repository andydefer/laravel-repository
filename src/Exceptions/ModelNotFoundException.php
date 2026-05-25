<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Exceptions;

use RuntimeException;

final class ModelNotFoundException extends RuntimeException
{
    public static function create(string $modelClass, int $id): self
    {
        return new self(
            sprintf('%s with id %d not found', $modelClass, $id)
        );
    }
}

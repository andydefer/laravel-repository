<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Fixtures\Enums;

enum TestUserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case GUEST = 'guest';
}

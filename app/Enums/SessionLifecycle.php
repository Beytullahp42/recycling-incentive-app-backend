<?php

namespace App\Enums;

enum SessionLifecycle: string
{
    case ACTIVE = 'active';
    case CLOSED = 'closed';
}

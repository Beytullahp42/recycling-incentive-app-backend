<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case ACCEPTED = 'accepted';
    case FLAGGED = 'flagged';
    case REJECTED = 'rejected';
}

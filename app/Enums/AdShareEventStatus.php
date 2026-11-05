<?php

namespace App\Enums;

enum AdShareEventStatus: string
{
    case PendingValidation = 'pending_validation';
    case Validated = 'validated';
    case Rejected = 'rejected';
}

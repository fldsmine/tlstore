<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Reserved = 'reserved';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}

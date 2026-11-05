<?php

namespace App\Enums;

enum UserRole: string
{
    case Reviewer = 'reviewer';
    case Owner = 'owner';
    // Kept for legacy user rows; staff authentication is isolated in the admins table.
    case Admin = 'admin';
}

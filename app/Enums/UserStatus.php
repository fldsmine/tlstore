<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Pending = 'pending';
    case Banned = 'banned';
}

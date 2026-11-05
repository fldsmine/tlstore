<?php

namespace App\Enums;

enum LedgerEntryStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Reserved = 'reserved';
    case Reversed = 'reversed';
}

<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case UnderReview = 'under_review';
    case Active = 'active';
    case Paused = 'paused';
    case Exhausted = 'exhausted';
    case Ended = 'ended';
    case Rejected = 'rejected';
    case Archived = 'archived';
}

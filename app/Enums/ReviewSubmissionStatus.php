<?php

namespace App\Enums;

enum ReviewSubmissionStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsInformation = 'needs_information';
}

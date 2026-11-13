<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

enum CandidateState: string
{
    case Experimental = 'experimental';
    case Candidate = 'candidate';
    case UnderReview = 'under_review';
    case ConfirmedByPolicy = 'confirmed_by_policy';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}

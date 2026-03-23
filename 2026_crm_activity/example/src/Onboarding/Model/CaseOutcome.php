<?php

declare(strict_types=1);

namespace App\Onboarding\Model;

enum CaseOutcome: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Abandoned = 'abandoned';
    case Expired = 'expired';
    case RequiresManualReview = 'requires_manual_review';
}

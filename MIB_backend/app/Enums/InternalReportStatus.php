<?php

namespace App\Enums;

enum InternalReportStatus: string
{
    case Submitted = 'Submitted';
    case UnderReview = 'UnderReview';
    case NeedsMoreInformation = 'NeedsMoreInformation';
    case Valid = 'Valid';
    case Invalid = 'Invalid';
    case Closed = 'Closed';
}

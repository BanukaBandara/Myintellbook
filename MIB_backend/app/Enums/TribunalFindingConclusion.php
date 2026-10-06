<?php

namespace App\Enums;

enum TribunalFindingConclusion: string
{
    case Established = 'established';
    case NotEstablished = 'not_established';
    case PartiallyEstablished = 'partially_established';
    case NotApplicable = 'not_applicable';
}

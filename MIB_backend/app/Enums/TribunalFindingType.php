<?php

namespace App\Enums;

enum TribunalFindingType: string
{
    case Fact = 'fact';
    case Issue = 'issue';
    case Credibility = 'credibility';
    case Evidence = 'evidence';
    case Procedural = 'procedural';
}

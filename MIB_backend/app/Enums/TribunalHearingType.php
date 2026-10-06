<?php

namespace App\Enums;

enum TribunalHearingType: string
{
    case Formal = 'formal';
    case Preliminary = 'preliminary';
    case Continuation = 'continuation';
}

<?php

namespace App\Enums;

enum TribunalHearingLocationType: string
{
    case Online = 'online';
    case Physical = 'physical';
    case Hybrid = 'hybrid';
}

<?php

namespace App\Enums;

enum TribunalDeliberationStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
}

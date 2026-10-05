<?php

namespace App\Enums;

enum TribunalQualificationStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case Exempted = 'exempted';

    public function isSatisfied(): bool
    {
        return in_array($this, [self::Passed, self::Exempted], true);
    }
}

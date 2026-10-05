<?php

namespace App\Enums;

enum TribunalPartyRole: string
{
    case Complainant = 'complainant';
    case Respondent = 'respondent';
}

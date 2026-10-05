<?php

namespace App\Enums;

enum TribunalSettlementProposalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Countered = 'countered';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';
}

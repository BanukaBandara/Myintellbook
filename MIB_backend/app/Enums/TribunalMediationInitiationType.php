<?php

namespace App\Enums;

enum TribunalMediationInitiationType: string
{
    case PartyRequest = 'party_request';
    case AdjudicatorOffer = 'adjudicator_offer';
}

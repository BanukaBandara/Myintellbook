<?php

namespace App\Enums;

enum TribunalConversationType: string
{
    case ComplainantRepresentative = 'complainant_representative';
    case RespondentRepresentative = 'respondent_representative';

    public function label(): string
    {
        return match ($this) {
            self::ComplainantRepresentative => 'Complainant & Representative',
            self::RespondentRepresentative => 'Respondent & Representative',
        };
    }
}

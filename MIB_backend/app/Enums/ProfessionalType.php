<?php

namespace App\Enums;

enum ProfessionalType: string
{
    case AttorneyAtLaw = 'attorney_at_law';
    case Judge = 'judge';
    case LegalOfficer = 'legal_officer';
    case Mediator = 'mediator';
    case OtherLegalProfessional = 'other_legal_professional';

    /**
     * Allowed profession types for tribunal adjudicators.
     */
    public static function adjudicatorEligibleTypes(): array
    {
        return [
            self::AttorneyAtLaw->value,
            self::Judge->value,
            self::LegalOfficer->value,
        ];
    }

    /**
     * Determines whether this profession type is eligible to serve as a Tribunal Adjudicator.
     */
    public function isAdjudicatorEligible(): bool
    {
        return in_array($this, [
            self::AttorneyAtLaw,
            self::Judge,
            self::LegalOfficer,
        ], true);
    }

    /**
     * Determines whether this profession type can act as a legal representative.
     */
    public function canRepresent(): bool
    {
        return $this === self::AttorneyAtLaw;
    }

    public function label(): string
    {
        return match ($this) {
            self::AttorneyAtLaw => 'Attorney-at-Law',
            self::Judge => 'Judge / Judicial Officer',
            self::LegalOfficer => 'Legal Officer',
            self::Mediator => 'Certified Mediator',
            self::OtherLegalProfessional => 'Legal Professional',
        };
    }
}

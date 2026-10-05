<?php

namespace App\Enums;

enum TribunalEvidenceType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Link = 'link';
    case Statement = 'statement';
    case Other = 'other';
}

<?php

namespace App\Enums;

enum TribunalResponsePosition: string
{
    case Accept = 'accept';
    case Deny = 'deny';
    case PartiallyAccept = 'partially_accept';
}

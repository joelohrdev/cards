<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Sold = 'sold';
    case Ended = 'ended';
    case Failed = 'failed';
}

<?php

namespace App\Enums;

enum MediaAssetStatus: string
{
    case ACTIVE = 'active';
    case PENDING_DELETION = 'pending_deletion';
    case PURGING = 'purging';
    case PURGED = 'purged';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

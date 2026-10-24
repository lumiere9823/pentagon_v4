<?php

namespace App\Enums;

enum PageReleaseStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUPERSEDED = 'superseded';
    case FAILED = 'failed';
    case PRUNED = 'pruned';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

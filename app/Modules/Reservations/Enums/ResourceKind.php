<?php

namespace App\Modules\Reservations\Enums;

use App\Enums\Concerns\HasLabels;

enum ResourceKind: string
{
    use HasLabels;

    const LABELS = 'resource_kind';

    case Room = 'room';
    case Chair = 'chair';
    case Court = 'court';
    case Table = 'table';
    case Chalet = 'chalet';
    case Other = 'other';

    protected static function labelFile(): string
    {
        return 'reservations';
    }
}

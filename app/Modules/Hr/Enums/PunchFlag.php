<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum PunchFlag: string
{
    use HasLabels;

    const LABELS = 'punch_flag';

    /** Inside a geofence of another branch of the same company, with no placement covering it. */
    case OutsideHomeBranch = 'outside_home_branch';

    /** Inside a geofence of a branch of another company of the owner. */
    case OtherCompany = 'other_company';

    /** The device reported a simulated location. */
    case MockedLocation = 'mocked_location';

    protected static function labelFile(): string
    {
        return 'hr';
    }
}

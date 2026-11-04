<?php

namespace App\Enums;

enum Role: string
{
    /** Owner: full access, manages admins. */
    case SystemAdmin = 'system_admin';

    /** Team member: access limited by the permission checklist. */
    case Admin = 'admin';

    /** Partner or customer: sees only their own client record. */
    case Client = 'client';
}

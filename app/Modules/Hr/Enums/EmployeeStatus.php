<?php

namespace App\Modules\Hr\Enums;

use App\Enums\Concerns\HasLabels;

enum EmployeeStatus: string
{
    use HasLabels;

    const LABELS = 'employee_status';

    /** Being set up; uses no seat. */
    case Draft = 'draft';

    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';

    /** Left the company; uses no seat. */
    case Terminated = 'terminated';

    /**
     * Statuses that count against the HR seat cap.
     *
     * @return list<self>
     */
    public static function seated(): array
    {
        return [self::Active, self::OnLeave, self::Suspended];
    }

    public function usesSeat(): bool
    {
        return in_array($this, self::seated(), true);
    }

    protected static function labelFile(): string
    {
        return 'hr';
    }
}

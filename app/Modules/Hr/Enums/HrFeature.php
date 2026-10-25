<?php

namespace App\Modules\Hr\Enums;

use App\Modules\Platform\Enums\Contracts\DeclaresFeatures;
use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;

/**
 * What the HR module lets a role do. Keys are stored in the database; never rename a case
 * value or an ability once released.
 */
enum HrFeature: string implements Feature
{
    use DeclaresFeatures;

    case Employees = 'employees';
    case Contracts = 'contracts';
    case Documents = 'documents';
    case Movements = 'movements';
    case Attendance = 'attendance';
    case Pay = 'pay';          // bonuses, deductions, advances
    case Leave = 'leave';
    case Payroll = 'payroll';
    case Catalog = 'catalog';   // sections, jobs, allowance types, document types
    case Reports = 'reports';
    case Shifts = 'shifts';     // rosters, open shifts, holidays
    case Tasks = 'tasks';       // tasks given to employees
    case Breaks = 'breaks';     // the live break board: grant, approve, end
    case Offboarding = 'offboarding'; // resignations, terminations and the exit checklist
    case Violations = 'violations';   // the disciplinary record
    case Recruitment = 'recruitment'; // openings, candidates, hiring
    case Reviews = 'reviews';         // performance review cycles

    public static function group(): string
    {
        return 'hr';
    }

    public static function labelFile(): string
    {
        return 'hr';
    }

    public function actions(): array
    {
        return match ($this) {
            self::Employees => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete, FeatureAction::Export],
            self::Contracts, self::Documents => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete],
            self::Movements => [FeatureAction::View, FeatureAction::Create, FeatureAction::Approve, FeatureAction::Delete],
            self::Attendance => [FeatureAction::View, FeatureAction::Create, FeatureAction::Export],
            self::Pay => [FeatureAction::View, FeatureAction::Create, FeatureAction::Approve, FeatureAction::Delete],
            self::Leave => [FeatureAction::View, FeatureAction::Create, FeatureAction::Approve, FeatureAction::Delete],
            self::Payroll => [FeatureAction::View, FeatureAction::Create, FeatureAction::Approve, FeatureAction::Export],
            self::Catalog => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete],
            self::Reports => [FeatureAction::View, FeatureAction::Export],
            self::Shifts, self::Tasks => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete],
            self::Breaks => [FeatureAction::View, FeatureAction::Approve],
            self::Offboarding, self::Violations, self::Reviews => [FeatureAction::View, FeatureAction::Create, FeatureAction::Approve],
            self::Recruitment => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Approve],
        };
    }

    public function abilities(): array
    {
        return match ($this) {
            self::Employees => ['view_salaries', 'view_iban', 'view_national_id', 'link_login', 'approve_changes'],
            self::Contracts => ['view_salaries'],
            self::Attendance => ['resolve_flags', 'reset_device'],
            self::Payroll => ['mark_paid', 'view_eosb'],
            default => [],
        };
    }
}

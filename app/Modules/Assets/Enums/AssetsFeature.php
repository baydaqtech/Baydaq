<?php

namespace App\Modules\Assets\Enums;

use App\Modules\Platform\Enums\Contracts\DeclaresFeatures;
use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;

enum AssetsFeature: string implements Feature
{
    use DeclaresFeatures;

    case Register = 'register';       // assets, categories, custody, disposal
    case Maintenance = 'maintenance'; // repair and service jobs
    case Audits = 'audits';           // stock-takes
    case Inventory = 'inventory';     // units, items, warehouses, stock receipts and counts
    case Transfers = 'transfers';     // warehouse to warehouse
    case Handovers = 'handovers';     // between a warehouse and people, or between people
    case Incidents = 'incidents';     // broken, lost, stolen… and how they were settled

    public static function group(): string
    {
        return 'assets';
    }

    public static function labelFile(): string
    {
        return 'assets';
    }

    public function actions(): array
    {
        return match ($this) {
            self::Register => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Export],
            self::Maintenance => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update],
            self::Audits => [FeatureAction::View, FeatureAction::Create],
            self::Inventory => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Export],
            self::Transfers, self::Handovers => [FeatureAction::View, FeatureAction::Create],
            self::Incidents => [FeatureAction::View, FeatureAction::Create],
        };
    }

    public function abilities(): array
    {
        return match ($this) {
            self::Register => ['assign', 'dispose', 'view_costs'],
            self::Audits => ['close'],
            self::Transfers => ['send', 'receive'],
            self::Handovers => ['accept_any'],
            self::Incidents => ['resolve', 'charge_employee'],
            default => [],
        };
    }
}

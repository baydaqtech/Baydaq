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
        };
    }

    public function abilities(): array
    {
        return match ($this) {
            self::Register => ['assign', 'dispose', 'view_costs'],
            self::Audits => ['close'],
            default => [],
        };
    }
}

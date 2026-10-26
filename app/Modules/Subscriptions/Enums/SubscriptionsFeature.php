<?php

namespace App\Modules\Subscriptions\Enums;

use App\Modules\Platform\Enums\Contracts\DeclaresFeatures;
use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;

enum SubscriptionsFeature: string implements Feature
{
    use DeclaresFeatures;

    case Plans = 'plans';       // plans, prices, branches
    case Members = 'members';   // members and their memberships
    case CheckIns = 'check_ins'; // the door

    public static function group(): string
    {
        return 'subscriptions';
    }

    public static function labelFile(): string
    {
        return 'subscriptions';
    }

    public function actions(): array
    {
        return match ($this) {
            self::Plans => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete],
            self::Members => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Export],
            self::CheckIns => [FeatureAction::View],
        };
    }

    public function abilities(): array
    {
        return match ($this) {
            self::Members => ['sell', 'freeze', 'cancel'],
            self::CheckIns => ['check_in'],
            default => [],
        };
    }
}

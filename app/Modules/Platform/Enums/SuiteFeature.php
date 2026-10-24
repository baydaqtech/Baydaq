<?php

namespace App\Modules\Platform\Enums;

use App\Modules\Platform\Enums\Contracts\DeclaresFeatures;
use App\Modules\Platform\Enums\Contracts\Feature;

/**
 * Kernel features that every tenant has regardless of modules: the tree, access and the audit log.
 */
enum SuiteFeature: string implements Feature
{
    use DeclaresFeatures;

    case Structure = 'structure';
    case Access = 'access';
    case Audit = 'audit';

    public static function group(): string
    {
        return 'suite';
    }

    public static function labelFile(): string
    {
        return 'suite';
    }

    public function actions(): array
    {
        return match ($this) {
            self::Structure, self::Access => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete],
            self::Audit => [FeatureAction::View],
        };
    }

    public function abilities(): array
    {
        return [];
    }
}

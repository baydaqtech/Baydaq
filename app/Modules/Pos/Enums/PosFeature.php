<?php

namespace App\Modules\Pos\Enums;

use App\Modules\Platform\Enums\Contracts\DeclaresFeatures;
use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;

enum PosFeature: string implements Feature
{
    use DeclaresFeatures;

    case Catalog = 'catalog';     // categories, products, modifiers, brands' menus
    case Registers = 'registers'; // registers, cashiers, shifts
    case Orders = 'orders';       // selling, kitchen, reports

    public static function group(): string
    {
        return 'pos';
    }

    public static function labelFile(): string
    {
        return 'pos';
    }

    public function actions(): array
    {
        return match ($this) {
            self::Catalog => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete],
            self::Registers => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update],
            self::Orders => [FeatureAction::View, FeatureAction::Create, FeatureAction::Export],
        };
    }

    public function abilities(): array
    {
        return match ($this) {
            self::Orders => ['sell', 'void', 'kitchen', 'discount'],
            self::Registers => ['open_shift', 'close_shift'],
            default => [],
        };
    }
}

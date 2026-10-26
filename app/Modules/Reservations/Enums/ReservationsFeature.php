<?php

namespace App\Modules\Reservations\Enums;

use App\Modules\Platform\Enums\Contracts\DeclaresFeatures;
use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;

enum ReservationsFeature: string implements Feature
{
    use DeclaresFeatures;

    case Catalog = 'catalog';   // services, resources, opening hours, online booking
    case Bookings = 'bookings'; // the calendar and its bookings

    public static function group(): string
    {
        return 'reservations';
    }

    public static function labelFile(): string
    {
        return 'reservations';
    }

    public function actions(): array
    {
        return match ($this) {
            self::Catalog => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete],
            self::Bookings => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Export],
        };
    }

    public function abilities(): array
    {
        return match ($this) {
            self::Bookings => ['checkout', 'cancel'],
            default => [],
        };
    }
}

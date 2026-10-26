<?php

namespace App\Modules\Platform\Enums;

use App\Enums\Concerns\HasLabels;

/**
 * A sellable module of the business suite. Each one is a folder under app/Modules
 * and a line on the client's subscription.
 */
enum Module: string
{
    use HasLabels;

    const LABELS = 'module';

    case Hr = 'hr';
    case Pos = 'pos';
    case Invoicing = 'invoicing';
    case Assets = 'assets';
    case Reservations = 'reservations';
    case Subscriptions = 'subscriptions';
    case Menus = 'menus';

    public function billingUnit(): BillingUnit
    {
        return match ($this) {
            self::Hr => BillingUnit::Seat,
            self::Pos => BillingUnit::Register,
            self::Invoicing, self::Assets => BillingUnit::Company,
            self::Reservations, self::Subscriptions, self::Menus => BillingUnit::Branch,
        };
    }

    protected static function labelFile(): string
    {
        return 'suite';
    }
}

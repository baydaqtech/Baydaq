<?php

namespace App\Modules\Finance\Enums;

use App\Modules\Platform\Enums\Contracts\DeclaresFeatures;
use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;

/**
 * What the finance area lets a role do. Keys are stored in the database; never rename a case.
 */
enum FinanceFeature: string implements Feature
{
    use DeclaresFeatures;

    case Ledger = 'ledger';         // chart of accounts, journal, manual entries
    case Reports = 'reports';       // trial balance, profit and loss, VAT return
    case Purchasing = 'purchasing'; // suppliers and purchase orders
    case Payables = 'payables';     // supplier bills and their payments

    public static function group(): string
    {
        return 'finance';
    }

    public static function labelFile(): string
    {
        return 'finance';
    }

    public function actions(): array
    {
        return match ($this) {
            self::Ledger => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update],
            self::Reports => [FeatureAction::View, FeatureAction::Export],
            self::Purchasing, self::Payables => [FeatureAction::View, FeatureAction::Create, FeatureAction::Approve],
        };
    }

    public function abilities(): array
    {
        return match ($this) {
            self::Payables => ['pay'],
            default => [],
        };
    }
}

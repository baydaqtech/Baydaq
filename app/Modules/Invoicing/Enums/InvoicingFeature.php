<?php

namespace App\Modules\Invoicing\Enums;

use App\Modules\Platform\Enums\Contracts\DeclaresFeatures;
use App\Modules\Platform\Enums\Contracts\Feature;
use App\Modules\Platform\Enums\FeatureAction;

enum InvoicingFeature: string implements Feature
{
    use DeclaresFeatures;

    case Customers = 'customers';
    case Invoices = 'invoices';
    case Einvoicing = 'einvoicing'; // units, certificates, ZATCA onboarding

    public static function group(): string
    {
        return 'invoicing';
    }

    public static function labelFile(): string
    {
        return 'invoicing';
    }

    public function actions(): array
    {
        return match ($this) {
            self::Customers => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Delete],
            self::Invoices => [FeatureAction::View, FeatureAction::Create, FeatureAction::Update, FeatureAction::Approve, FeatureAction::Export],
            self::Einvoicing => [FeatureAction::View, FeatureAction::Update],
        };
    }

    public function abilities(): array
    {
        return match ($this) {
            self::Invoices => ['issue', 'cancel'],
            self::Einvoicing => ['onboard'],
            default => [],
        };
    }
}

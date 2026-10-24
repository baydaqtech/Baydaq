<?php

namespace App\Modules\Invoicing\Enums;

use App\Enums\Concerns\HasLabels;

enum ZatcaEnvironment: string
{
    use HasLabels;

    const LABELS = 'zatca_environment';

    case Sandbox = 'sandbox';
    case Simulation = 'simulation';
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
            self::Simulation => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
            self::Production => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
        };
    }

    protected static function labelFile(): string
    {
        return 'invoicing';
    }
}

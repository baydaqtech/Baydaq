<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Supported site languages and their text direction. Arabic is the main
    | language; English is reachable with ?lang=en and is remembered after.
    |
    */

    'locales' => [
        'ar' => 'rtl',
        'en' => 'ltr',
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | All money is in Saudi riyals and invoices carry 15% VAT.
    |
    */

    'currency' => 'SAR',

    'vat_rate' => '0.15',

];

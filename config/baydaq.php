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

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Disk for product and project logos: "public" locally, an object storage
    | bucket on Laravel Cloud.
    |
    */

    'logo_disk' => env('LOGO_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | HR module
    |--------------------------------------------------------------------------
    |
    | Employee files live on a private disk ("hr" locally, "hr-s3" on Cloud). The
    | blind-index key hashes national ids so they can be searched while stored
    | encrypted; keep it separate from APP_KEY and never rotate it casually.
    |
    */

    'hr' => [
        'disk' => env('HR_DISK', 'hr'),
        'blind_index_key' => env('HR_BLIND_INDEX_KEY', env('APP_KEY')),

        // GOSI rates (share of basic + GOSI-counted allowances, capped). Saudi employees: pension +
        // unemployment (SANED) split between employee and employer, plus occupational hazards paid
        // by the employer; non-Saudi: occupational hazards only. Review every July.
        'gosi' => [
            'saudi_employee' => '0.0975',
            'saudi_employer' => '0.1175',
            'non_saudi_employer' => '0.02',
            'wage_cap' => '45000',
            'nationality' => 'SA',
        ],

        // Labor Law caps and rules used by deductions, leave and end-of-service.
        'labor' => [
            'max_deduction_days_per_violation' => 5,
            'max_deduction_days_per_month' => 5,
            'annual_leave_days' => 21,
            'annual_leave_days_after_five_years' => 30,
            'month_days' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | First system admin
    |--------------------------------------------------------------------------
    |
    | The owner account created by `php artisan db:seed`. Nothing is created
    | when these are empty. The password must be changed at first sign-in.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets and inventory
    |--------------------------------------------------------------------------
    |
    | Handover and incident photos live on a private disk ("assets" locally,
    | "assets-s3" on Cloud) and are shown through signed links that expire.
    |
    */

    'assets' => [
        'disk' => env('ASSETS_DISK', 'assets'),
        'photo_max_kb' => 8192,
    ],

    /*
    |--------------------------------------------------------------------------
    | Messaging (WhatsApp)
    |--------------------------------------------------------------------------
    |
    | "log" only records messages (local, tests, demos); "whatsapp" sends them
    | through Baydaq's own WhatsApp service.
    |
    */

    'messaging' => [
        'driver' => env('MESSAGING_DRIVER', 'log'),
        'whatsapp' => [
            'url' => env('BAYDAQ_WHATSAPP_URL'),
            'token' => env('BAYDAQ_WHATSAPP_TOKEN'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Online payments
    |--------------------------------------------------------------------------
    |
    | "fake" serves a local test payment page; "moyasar" creates Moyasar invoices
    | (mada, Apple Pay, STC Pay, cards) and receives their webhook.
    |
    */

    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'fake'),
        'moyasar' => [
            'url' => env('MOYASAR_URL', 'https://api.moyasar.com/v1'),
            'secret_key' => env('MOYASAR_SECRET_KEY'),
            'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI (document capture, HR assistant)
    |--------------------------------------------------------------------------
    |
    | Claude through the Anthropic API. Without ANTHROPIC_API_KEY the AI features
    | stay hidden and everything else works as before.
    |
    */

    'ai' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('BAYDAQ_AI_MODEL', 'claude-opus-5-5'),
    ],
];

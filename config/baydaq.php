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

    // Government portals for the hub (suite.gov). None offers a public API to businesses, so
    // Baydaq prepares the files and numbers and links straight to the portal.
    'gov' => [
        'portals' => [
            'qiwa' => ['url' => 'https://qiwa.sa', 'area' => 'hr', 'number' => 'qiwa_id'],
            'gosi' => ['url' => 'https://www.gosi.gov.sa', 'area' => 'hr', 'number' => 'gosi_number'],
            'mudad' => ['url' => 'https://mudad.com.sa', 'area' => 'hr', 'number' => 'qiwa_id'],
            'muqeem' => ['url' => 'https://muqeem.sa', 'area' => 'hr', 'number' => 'unified_number'],
            'absher' => ['url' => 'https://www.absher.sa', 'area' => 'hr', 'number' => 'unified_number'],
            'hrsd' => ['url' => 'https://hrsd.gov.sa', 'area' => 'hr', 'number' => 'qiwa_id'],
            'zatca' => ['url' => 'https://zatca.gov.sa', 'area' => 'finance', 'number' => 'vat_number'],
            'fatoora' => ['url' => 'https://fatoora.zatca.gov.sa', 'area' => 'finance', 'number' => 'vat_number'],
            'mc' => ['url' => 'https://mc.gov.sa', 'area' => 'company', 'number' => 'cr_number'],
            'business' => ['url' => 'https://business.sa', 'area' => 'company', 'number' => 'unified_number'],
            'balady' => ['url' => 'https://balady.gov.sa', 'area' => 'company', 'number' => 'cr_number'],
            'address' => ['url' => 'https://splonline.com.sa', 'area' => 'company', 'number' => null],
        ],
    ],

    'hr' => [
        'disk' => env('HR_DISK', 'hr'),
        'blind_index_key' => env('HR_BLIND_INDEX_KEY', env('APP_KEY')),

        // GOSI rates (share of basic + GOSI-counted allowances, capped). Saudi employees: pension +
        // unemployment (SANED) split between employee and employer, plus occupational hazards paid
        // by the employer; non-Saudi: occupational hazards only. Under the 2024 Social Insurance Law,
        // Saudis first registered on or after `new_system_from` pay a pension rate that rises each
        // July (each side pays the rate shown); those registered before keep the legacy rate.
        // Review every July.
        'gosi' => [
            'nationality' => 'SA',
            'wage_cap' => '45000',
            'wage_floor' => '1500',
            'new_system_from' => '2024-07-03',
            'pension_legacy' => '0.09',
            'pension_new' => [
                '2024-07-03' => '0.09',
                '2025-07-01' => '0.095',
                '2026-07-01' => '0.10',
                '2027-07-01' => '0.105',
                '2028-07-01' => '0.11',
            ],
            'saned' => '0.0075',
            'hazards' => '0.02',
        ],

        // Labor Law caps and rules used by deductions, leave and end-of-service.
        'labor' => [
            'max_deduction_days_per_violation' => 5,
            'max_deduction_days_per_month' => 5,
            'annual_leave_days' => 21,
            'annual_leave_days_after_five_years' => 30,
            'month_days' => 30,
            // Leaving under the 2025 amendments: a resignation the employer does not answer is
            // accepted after `resignation_deemed_days`; the employer may postpone it up to
            // `resignation_max_defer_days`; the employee may withdraw within `resignation_withdraw_days`.
            'resignation_deemed_days' => 30,
            'resignation_max_defer_days' => 60,
            'resignation_withdraw_days' => 7,
            'notice_days_employee' => 30,
            'notice_days_employer' => 60,
            // Repeats of the same violation are counted within this many days (work regulations).
            'violation_repeat_window_days' => 180,
            'late_grace_minutes' => 15,
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

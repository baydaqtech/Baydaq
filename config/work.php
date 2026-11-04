<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Our work
    |--------------------------------------------------------------------------
    |
    | The projects on the landing page, in the order shown there. A project with
    | its own website ('url') opens it; the others open their page on this site
    | (/work/{slug}), built from these screenshots (under public/images/work/{slug}) and
    | the copy in lang/{locale}/work.php. Captions follow the order of the files.
    |
    */

    'projects' => [
        'opsmaster' => [
            'url' => 'https://opsmaster.tech',
        ],
        'nuzul' => [
            'web' => ['web-01-home.jpg', 'web-02-search.jpg', 'web-03-property.jpg'],
            'mobile' => ['mobile-01-guest-home.jpg', 'mobile-02-host-properties.jpg'],
        ],
        'nuzul-umrah' => [
            'web' => ['os-web-01-landing.jpg', 'os-web-02-today.jpg', 'os-web-03-trip.jpg', 'os-web-04-leads.jpg'],
            'mobile' => ['os-mobile-01-home.jpg', 'os-mobile-02-trip.jpg', 'os-mobile-03-days.jpg', 'os-mobile-04-staff.jpg'],
        ],
        'healthy-meals' => [
            'web' => ['hf-web-01-dashboard.jpg', 'hf-web-02-kitchen.jpg', 'hf-web-03-orders.jpg'],
            'mobile' => ['hf-mobile-01-home.jpg', 'hf-mobile-02-picker.jpg'],
        ],
        'pchome' => [
            'url' => 'https://pchome-dev-k0sgqb.laravel.cloud',
        ],
    ],

];

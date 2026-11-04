<?php

return [
    'ui' => [
        'all' => 'All our work',
        'position' => 'Project :index of :total',
        'previous' => 'Previous project',
        'next' => 'Next project',
        'overview' => 'About the project',
        'architecture' => 'Architecture',
        'screens' => 'Screens',
        'web' => 'On the web',
        'mobile' => 'On the phone',
        'specs' => 'What is inside',
        'numbers' => 'In numbers',
        'open_image' => 'Open “:name” full size',
        'open_full' => 'Open full size',
        'show_screen' => 'Show “:name”',
        'previous_screen' => 'Previous screen',
        'next_screen' => 'Next screen',
        'highlight' => 'Worth knowing',
        'cta_title' => 'Have an idea like this?',
        'cta_text' => 'Tell us about it, and we will turn it into a working product.',
    ],

    'projects' => [
        'opsmaster' => [
            'name' => 'OpsMaster',
        ],

        'nuzul' => [
            'name' => 'Nuzul',
            'tagline' => 'Property booking marketplace',
            'tags' => ['Web + mobile', 'Booking marketplace', 'Arabic first'],
            'summary' => 'Guests book chalets, rest houses and apartments in Makkah; hosts run their properties from the same platform.',
            'overview' => 'A full two-sided booking marketplace, in the style of Airbnb and Gathern, for short-term rentals in Saudi Arabia. Guests and hosts use one web app, and a native iOS app built with Flutter shares the same API, with a full switch between guest and host mode.',
            'architecture' => [
                ['name' => 'Web app', 'stack' => 'Laravel · React'],
                ['name' => 'iOS app', 'stack' => 'Flutter'],
                ['name' => 'API', 'stack' => 'Laravel · JSON'],
                ['name' => 'Database', 'stack' => 'MySQL'],
                ['name' => 'Messaging', 'stack' => 'Guest · host · support'],
            ],
            'web' => ['Home page with the search', 'Search results with filters', 'Property page with the booking box'],
            'mobile' => ['Guest: browse and book', 'Host: manage properties'],
            'specs' => [
                ['name' => 'Web app', 'text' => 'Laravel, Inertia.js, React 19 with TypeScript, Tailwind v4'],
                ['name' => 'Mobile app', 'text' => 'Native Flutter for iOS, on the same API as the web'],
                ['name' => 'Booking engine', 'text' => 'Dates held safely when two guests book at once, overnight and evening stays, reviews after the stay'],
                ['name' => 'Messaging', 'text' => 'Live guest and host chat, with its own support channel'],
                ['name' => 'Language', 'text' => 'Arabic right to left first, plus English, with its own design system'],
            ],
            'numbers' => [
                ['value' => '175+', 'label' => 'automated tests'],
                ['value' => '2', 'label' => 'apps on one API'],
                ['value' => '2', 'label' => 'languages: Arabic and English'],
            ],
            'highlight' => 'Two guests booking the same nights at the same moment: the property is locked first and its dates checked again under the lock, so only one booking goes through.',
        ],

        'nuzul-umrah' => [
            'name' => 'Nuzul Umrah',
            'tagline' => 'Operations platform for Umrah companies',
            'tags' => ['Software as a service', 'Web + mobile', 'Arabic first'],
            'summary' => 'Runs an Umrah company end to end: one dashboard for the company, and apps for its pilgrims and staff.',
            'overview' => 'A monthly-subscription platform that runs an Umrah company from start to finish. Staff manage packages, departures and seats, bookings and payments, leads, trips and room assignments, tasks and documents from one Arabic-first dashboard. Each pilgrim gets one companion app for the whole journey: the programme day by day, the miqat, their room and group, announcements and support. The platform is only the technology: pilgrims pay the company\'s own account, and customer money never passes through it.',
            'architecture' => [
                ['name' => 'Company dashboard', 'stack' => 'React · Inertia'],
                ['name' => 'Pilgrim app', 'stack' => 'Flutter'],
                ['name' => 'Staff app', 'stack' => 'Flutter'],
                ['name' => 'Core', 'stack' => 'Laravel 13 · each company walled off'],
                ['name' => 'Database', 'stack' => 'MySQL'],
                ['name' => 'Live updates', 'stack' => 'Reverb · Sanctum'],
            ],
            'web' => ['Landing page', 'Today: departures, returns, overdue tasks and payments', 'A trip: pilgrims, rooms, programme and tasks', 'Leads in five stages'],
            'mobile' => ['Pilgrim: home with the trip countdown', 'Pilgrim: my trip, booking, room and miqat', 'Pilgrim: the programme day by day', 'Staff: the company\'s trips in the field'],
            'specs' => [
                ['name' => 'Company dashboard', 'text' => 'Laravel 13, Inertia.js, React 19 with TypeScript, Tailwind v4'],
                ['name' => 'Mobile apps', 'text' => 'Flutter: the pilgrim and staff apps on one shared core'],
                ['name' => 'Many companies', 'text' => 'Each company\'s data walled off row by row, with an isolation test every change must pass'],
                ['name' => 'Bookings', 'text' => 'Seats locked against double booking, the price kept as it was booked, actions safe to repeat'],
                ['name' => 'Money', 'text' => 'Kept in whole halalas; pilgrims pay the company directly'],
                ['name' => 'Live and mobile', 'text' => 'Instant notifications over Reverb, the mobile API over Sanctum'],
            ],
            'numbers' => [
                ['value' => '865', 'label' => 'automated tests'],
                ['value' => '58', 'label' => 'mobile app tests'],
                ['value' => '3', 'label' => 'apps on one backend'],
            ],
            'highlight' => 'Two staff booking the last seats at the same moment cannot oversell: the departure is locked and read again before its seats are counted.',
        ],

        'healthy-meals' => [
            'name' => 'Healthy Meals',
            'tagline' => 'Meal subscription platform',
            'tags' => ['Web + mobile', 'White label', 'Arabic first'],
            'summary' => 'Meal subscriptions for a healthy-food restaurant, with a nightly auto-fill, kitchen prep reports and delivery lists.',
            'overview' => 'A complete meal-subscription system for a healthy-food restaurant. Customers pick a plan, from one to four meals a day in 100 to 200 g portions for 26 or 30 days with a daily snack, and choose tomorrow\'s meals in the app before the nightly cutoff. The kitchen cooks from one combined prep report, and drivers deliver from a printable list. It is built white label: the same code already runs a second restaurant under its own brand.',
            'architecture' => [
                ['name' => 'Admin app', 'stack' => 'React · Inertia'],
                ['name' => 'Customer app', 'stack' => 'Expo · React Native'],
                ['name' => 'Subscriptions', 'stack' => 'Laravel'],
                ['name' => 'Nightly auto-fill', 'stack' => 'Scheduled job'],
                ['name' => 'Kitchen and delivery', 'stack' => 'Printable sheets'],
            ],
            'web' => ['Dashboard: active subscriptions and those waiting to start', 'Kitchen prep report, grouped by portion size', 'Order queue with statuses and totals'],
            'mobile' => ['Customer: plan progress and tomorrow\'s meals', 'Customer: picking the day\'s meals, with calories and macros'],
            'specs' => [
                ['name' => 'Admin app', 'text' => 'Laravel, Inertia.js, React 19 with TypeScript, Tailwind v4'],
                ['name' => 'Mobile app', 'text' => 'Expo and React Native with TypeScript, Arabic right to left'],
                ['name' => 'Subscriptions', 'text' => 'Pending, active, expired, with renewals'],
                ['name' => 'Auto-fill', 'text' => 'At the nightly cutoff the last choice repeats, or else the chef\'s pick, never twice'],
                ['name' => 'Kitchen', 'text' => 'One combined prep report, and a delivery list with Google Maps links'],
                ['name' => 'Notifications', 'text' => 'Reminders to choose meals and to renew'],
            ],
            'numbers' => [
                ['value' => '62', 'label' => 'automated tests'],
                ['value' => '75', 'label' => 'dishes on the menu'],
                ['value' => '24', 'label' => 'plan combinations'],
            ],
            'highlight' => null,
        ],

        'pchome' => [
            'name' => 'PC Home',
        ],
    ],
];

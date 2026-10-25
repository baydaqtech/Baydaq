<?php

namespace App\Modules\Platform\Access;

/**
 * Everything a package can switch on or limit. Keys are stored in packages and plans, so a key
 * is never renamed. Labels live in lang/{ar,en}/suite.php under "catalog".
 */
final class FeatureCatalog
{
    /** Countable things a package caps. null in a package means unlimited. */
    public const LIMITS = ['organizations', 'companies', 'branches', 'employees', 'users', 'registers'];

    /**
     * Web features by area. A feature of a module only matters when the module is in the plan.
     *
     * @var array<string, list<string>>
     */
    public const FEATURES = [
        'suite' => ['suite.board', 'suite.customers', 'suite.theme', 'suite.ai', 'suite.gov', 'suite.privacy'],
        'hr' => ['hr.tasks', 'hr.breaks', 'hr.shifts', 'hr.advances', 'hr.letters', 'hr.compliance', 'hr.remote', 'hr.recruitment', 'hr.performance', 'hr.expenses', 'hr.violations', 'hr.offboarding'],
        'finance' => ['finance.accounting', 'finance.purchasing'],
    ];

    /** Screens of the employee app that a package can show or hide. */
    public const MOBILE = [
        'mobile.attendance', 'mobile.selfie', 'mobile.leave', 'mobile.tasks', 'mobile.breaks', 'mobile.shifts',
        'mobile.advances', 'mobile.payslips', 'mobile.letters', 'mobile.expenses', 'mobile.remote', 'mobile.assets_audit', 'mobile.resignation', 'mobile.reviews',
    ];

    /** @return list<string> */
    public static function features(): array
    {
        return array_merge(...array_values(self::FEATURES));
    }

    /**
     * Option lists for the admin package form, labelled in the current language.
     *
     * @return array{limits: list<array{value: string, label: string}>, features: array<string, list<array{value: string, label: string}>>, mobile: list<array{value: string, label: string}>}
     */
    public static function options(): array
    {
        $label = fn (string $key): array => ['value' => $key, 'label' => __('suite.catalog.'.str_replace('.', '_', $key))];

        return [
            'limits' => array_map(fn (string $key): array => ['value' => $key, 'label' => __("suite.catalog.limit_{$key}")], self::LIMITS),
            'features' => array_map(fn (array $keys): array => array_map($label, $keys), self::FEATURES),
            'mobile' => array_map($label, self::MOBILE),
        ];
    }
}

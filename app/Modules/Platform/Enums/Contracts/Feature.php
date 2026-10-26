<?php

namespace App\Modules\Platform\Enums\Contracts;

use App\Modules\Platform\Enums\FeatureAction;

/**
 * Implemented by each module's feature enum. A feature declares its standard actions and
 * its extra abilities; the registry turns them into permission keys such as
 * "hr.employees.update" and "hr.employees.view_salaries". Keys are stored in the database,
 * so a case's value and its abilities are never renamed once released.
 */
interface Feature
{
    /** The key prefix: "suite" for the kernel, otherwise the module's value. */
    public static function group(): string;

    /** The lang file (and group) that labels this enum's features and abilities. */
    public static function labelFile(): string;

    /** @return list<FeatureAction> */
    public function actions(): array;

    /** @return list<string> snake_case ability names, e.g. view_salaries */
    public function abilities(): array;

    /** The permission key for an action or ability: "{group}.{feature}.{name}". */
    public function key(FeatureAction|string $action): string;
}

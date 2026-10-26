<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Enums\Module;

/**
 * What the current tenant has bought. Modules ask here before letting a seat, register or
 * branch be used, or before showing a feature; they never read the plan or subscription tables.
 */
interface Entitlements
{
    public function isActive(Module $module): bool;

    /** The quantity cap (seats, registers...), or null when the module is not subscribed. */
    public function quantity(Module $module): ?int;

    /**
     * Lock the subscription row and return its cap, so two activations can't both pass the
     * same last seat. Call inside a transaction.
     */
    public function lockQuantity(Module $module): ?int;

    /**
     * A limit of the plan (organizations, companies, branches, employees, users, registers).
     * Null means unlimited, also for accounts set up before packages existed.
     */
    public function limit(string $key): ?int;

    /** Whether a web feature (e.g. hr.tasks) is in the plan. Accounts without a plan have every feature. */
    public function hasFeature(string $key): bool;

    /** @return list<string> the web features on (all of them for accounts without a plan) */
    public function features(): array;

    /** @return list<string> the employee-app screens on (all of them for accounts without a plan) */
    public function mobileFeatures(): array;
}

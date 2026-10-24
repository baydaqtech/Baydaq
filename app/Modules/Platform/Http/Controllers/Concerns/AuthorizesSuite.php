<?php

namespace App\Modules\Platform\Http\Controllers\Concerns;

use App\Modules\Platform\Access\AccessMap;
use App\Modules\Platform\Access\AccessResolver;
use App\Modules\Platform\Access\SuiteScope;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;

/**
 * Record-level checks for suite controllers: the route middleware proved the user holds the
 * key somewhere; these prove it covers the record in hand. Suite checks never go through the
 * Gate, so the staff bypass never reaches tenant data.
 */
trait AuthorizesSuite
{
    protected function access(): AccessMap
    {
        return app(AccessResolver::class)->for(request()->user());
    }

    protected function scope(): SuiteScope
    {
        return app(SuiteScope::class);
    }

    /** The key must reach the whole account (organizations are account-wide records). */
    protected function authorizeAccount(string $key): void
    {
        abort_unless($this->access()->branches($key)->all, 403);
    }

    protected function authorizeCompany(string $key, Company $company): void
    {
        abort_unless($this->access()->branches($key)->allowsWholeCompany($company->id), 403);
    }

    protected function authorizeBranch(string $key, Branch $branch): void
    {
        abort_unless($this->access()->allowsBranch($key, $branch->id, $branch->company_id), 403);
    }
}

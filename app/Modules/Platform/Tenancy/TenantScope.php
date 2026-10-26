<?php

namespace App\Modules\Platform\Tenancy;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query to the current tenant's rows. With no tenant resolved the query
 * matches nothing (fail closed). Staff code that works across tenants uses forClient().
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('client_id'), $tenantId);
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('forClient', function (Builder $builder, Client|int $client): Builder {
            return $builder->withoutGlobalScope($this)
                ->where($builder->getModel()->qualifyColumn('client_id'), $client instanceof Client ? $client->id : $client);
        });
    }
}

<?php

namespace App\Modules\Platform\Models\Concerns;

use App\Models\Client;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Platform\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every tenant table: rows are read within the current tenant only and new rows
 * get the tenant's client_id. Use Model::forClient($client) to query as Baydaq staff.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> forClient(Client|int $client)
 */
trait BelongsToClient
{
    public static function bootBelongsToClient(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('client_id'))) {
                $model->setAttribute('client_id', app(CurrentTenant::class)->client()->id);
            }
        });
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

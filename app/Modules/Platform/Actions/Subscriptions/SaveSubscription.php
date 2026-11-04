<?php

namespace App\Modules\Platform\Actions\Subscriptions;

use App\Models\Client;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

class SaveSubscription
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * Baydaq staff record what a client bought. Runs as the client so the tenant scope applies.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Client $client, Subscription $subscription, array $data): Subscription
    {
        return $this->tenant->run($client, fn (): Subscription => DB::transaction(function () use ($client, $subscription, $data): Subscription {
            $subscription->fill($data);
            $subscription->forceFill(['client_id' => $client->id])->save();

            return $subscription;
        }));
    }
}

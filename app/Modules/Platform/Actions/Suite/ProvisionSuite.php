<?php

namespace App\Modules\Platform\Actions\Suite;

use App\Models\Client;
use App\Models\User;
use App\Modules\Platform\Actions\Access\AssignAccess;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

class ProvisionSuite
{
    public function __construct(
        private CurrentTenant $tenant,
        private AssignAccess $assignAccess,
        private SaveOrganization $saveOrganization,
        private SaveCompany $saveCompany,
    ) {}

    /**
     * Turn on the business suite for a client: create the locked owner role, make the given
     * users owners, and (unless the client already has a tree) start them with one organization
     * and one company named after the client. Safe to run again.
     *
     * @param  iterable<User>  $owners
     */
    public function handle(Client $client, iterable $owners): void
    {
        $this->tenant->run($client, function () use ($client, $owners): void {
            DB::transaction(function () use ($client, $owners): void {
                $role = AccessRole::query()->firstWhere('key', AccessRole::OWNER_KEY);

                if ($role === null) {
                    $role = new AccessRole([
                        'name_ar' => __('suite.access.owner_role', [], 'ar'),
                        'name_en' => __('suite.access.owner_role', [], 'en'),
                    ]);
                    // client_id is set explicitly: seeders run without model events, so the tenant hook may not fire.
                    $role->forceFill(['client_id' => $client->id, 'key' => AccessRole::OWNER_KEY, 'is_locked' => true])->save();
                    $role->permissions()->create(['permission' => AccessRole::WILDCARD]);
                }

                foreach ($owners as $owner) {
                    $this->assignAccess->handle($owner, $role, ScopeLevel::Client, null, actor: null, guard: false);
                }

                if ($client->organizations()->doesntExist()) {
                    $organization = $this->saveOrganization->handle(
                        $client->organizations()->make(),
                        ['name_ar' => $client->name_ar, 'name_en' => $client->name_en],
                    );
                    $this->saveCompany->handle(
                        $organization->companies()->make(),
                        ['name_ar' => $client->name_ar, 'name_en' => $client->name_en],
                    );
                }
            });
        });
    }
}

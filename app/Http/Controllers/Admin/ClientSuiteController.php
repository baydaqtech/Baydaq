<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Modules\Platform\Actions\Suite\ProvisionSuite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Turns the business suite on for a client: its client accounts become owners.
 */
class ClientSuiteController extends Controller
{
    use RendersAdminPages;

    public function provision(Client $client, ProvisionSuite $provisionSuite): RedirectResponse
    {
        $owners = $client->users()->role(Role::Client->value)->get();

        if ($owners->isEmpty()) {
            throw ValidationException::withMessages(['suite' => __('admin.clients.suite.needs_account')]);
        }

        $provisionSuite->handle($client, $owners);
        $this->toast(__('admin.clients.suite.provisioned'));

        return back();
    }
}

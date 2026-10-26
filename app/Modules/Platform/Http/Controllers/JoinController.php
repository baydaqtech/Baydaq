<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Ports\Branding;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public page behind a branch's sign-up QR code: a client registers with their phone, without
 * an account, while the account's package carries client registration.
 */
class JoinController extends Controller
{
    public function __construct(private CurrentTenant $tenant) {}

    public function show(string $token): Response
    {
        $branch = $this->branch($token);

        return $this->tenant->runFor($branch->client_id, fn (): Response => Inertia::render('Suite/Join', [
            'token' => $token,
            'branch' => ['name' => $branch->localized('name'), 'company' => $branch->company->localized('name')],
            'theme' => app(Branding::class)->theme(),
            'copy' => ['suite' => ['join' => trans('suite.join'), 'customers' => ['genders' => trans('suite.customers.genders')]]],
        ]));
    }

    public function store(Request $request, string $token, Customers $customers): RedirectResponse
    {
        $branch = $this->branch($token);
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female'],
            'marketing_consent' => ['boolean'],
            'accept' => ['accepted'],
        ]);

        return $this->tenant->runFor($branch->client_id, function () use ($branch, $data, $customers): RedirectResponse {
            $customer = $customers->identify($data['name'], $data['phone'], $data['email'] ?? null);

            if ($customer === null) {
                return back()->withErrors(['phone' => __('suite.customers.phone_invalid')]);
            }

            $record = Customer::query()->whereKey($customer->id)->firstOrFail();
            // A public visitor only fills gaps; what the staff recorded stays.
            $record->fill(array_filter(Arr::only($data, ['birth_date', 'gender']), fn ($value, string $field): bool => $value !== null && $record->getAttribute($field) === null, ARRAY_FILTER_USE_BOTH))->save();
            $customers->register($customer->id, $branch->id, 'self', null, (bool) ($data['marketing_consent'] ?? false));
            Inertia::flash('joined', true);

            return back();
        });
    }

    /**
     * The branch behind the token, only while its account's package carries client registration.
     */
    private function branch(string $token): Branch
    {
        $branch = Branch::withoutGlobalScopes()->with(['company' => fn ($query) => $query->withoutGlobalScopes()])->where('join_token', $token)->firstOrFail();
        abort_unless($this->tenant->runFor($branch->client_id, fn (): bool => app(Entitlements::class)->hasFeature('suite.customers')), 404);

        return $branch;
    }
}

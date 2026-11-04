<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\PhoneNumber;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Ports\Branding;
use App\Modules\Platform\Ports\Customers;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\Messaging;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public page behind a branch's sign-up QR code: a client registers with their phone, without
 * an account, while the account's package carries client registration. Asking for offers needs
 * a code sent to that phone first (PDPL: consent from the phone's owner, not whoever typed it).
 */
class JoinController extends Controller
{
    private const PENDING = 'join.pending.';

    private const CODE = 'join.code.';

    private const CODE_MINUTES = 10;

    public function __construct(private CurrentTenant $tenant) {}

    public function show(string $token): Response
    {
        $branch = $this->branch($token);

        $pending = session(self::PENDING.$token);

        return $this->tenant->runFor($branch->client_id, fn (): Response => Inertia::render('Suite/Join', [
            'token' => $token,
            'verifying' => $pending ? self::masked($pending['phone']) : null,
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

        if (! ($data['marketing_consent'] ?? false)) {
            return $this->join($branch, $data, $customers);
        }

        // Offers go to this phone, so its owner proves it first: a code by WhatsApp.
        $phone = PhoneNumber::normalize($data['phone']);

        if (strlen($phone) < 9) {
            return back()->withErrors(['phone' => __('suite.customers.phone_invalid')]);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put(self::CODE.$token.':'.$phone, ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(self::CODE_MINUTES));
        session([self::PENDING.$token => [...$data, 'phone' => $phone]]);
        $this->tenant->runFor($branch->client_id, fn () => app(Messaging::class)->send($phone, 'join_code', ['company' => $branch->company->localized('name'), 'code' => $code, 'minutes' => self::CODE_MINUTES], app()->getLocale(), "platform.join:{$branch->id}"));

        return back();
    }

    /**
     * The code from WhatsApp: right, and the client is registered with their consent; five wrong
     * tries or an expired code start over. "Without offers" registers them without consent.
     */
    public function verify(Request $request, string $token, Customers $customers): RedirectResponse
    {
        $branch = $this->branch($token);
        $pending = session(self::PENDING.$token) ?? abort(404);

        if ($request->boolean('skip')) {
            session()->forget(self::PENDING.$token);

            return $this->join($branch, [...$pending, 'marketing_consent' => false], $customers);
        }

        $code = (string) $request->validate(['code' => ['required', 'digits:6']])['code'];
        $key = self::CODE.$token.':'.$pending['phone'];
        $sent = Cache::get($key);

        if ($sent === null || $sent['tries'] >= 5) {
            Cache::forget($key);
            session()->forget(self::PENDING.$token);

            return back()->withErrors(['phone' => __('suite.join.code_expired')]);
        }

        if (! Hash::check($code, $sent['hash'])) {
            Cache::put($key, [...$sent, 'tries' => $sent['tries'] + 1], now()->addMinutes(self::CODE_MINUTES));

            return back()->withErrors(['code' => __('suite.join.code_wrong')]);
        }

        Cache::forget($key);
        session()->forget(self::PENDING.$token);

        return $this->join($branch, $pending, $customers);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function join(Branch $branch, array $data, Customers $customers): RedirectResponse
    {
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

    /** "966501234567" → "••••••••4567", enough to recognise one's own number. */
    private static function masked(string $phone): string
    {
        return str_repeat('•', max(0, strlen($phone) - 4)).substr($phone, -4);
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

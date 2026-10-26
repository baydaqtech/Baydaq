<?php

namespace App\Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Actions\SaveCustomer;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Customers are owner-wide (shared by every company), like the HR catalogs.
 */
class CustomerController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function index(Request $request): Response
    {
        $filters = $request->only(['search']);

        $customers = Customer::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereAny(['name_ar', 'name_en', 'vat_number', 'phone'], 'like', "%{$search}%"))
            ->orderBy('name_en')->paginate(20)->withQueryString();

        return $this->suitePage('Invoicing/Customers/Index', ['customers' => $customers->through(fn (Customer $customer) => $this->customerArray($customer)), 'filters' => $filters], ['invoicing']);
    }

    public function store(Request $request, SaveCustomer $save): RedirectResponse
    {
        $this->authorizeAccount('invoicing.customers.create');
        $customer = (new Customer)->forceFill(['client_id' => $this->scope()->organization?->client_id ?? app(CurrentTenant::class)->client()->id]);
        $save->handle($customer, $this->validated($request));
        $this->toast(__('invoicing.customers.saved'));

        return back();
    }

    public function update(Request $request, Customer $customer, SaveCustomer $save): RedirectResponse
    {
        $this->authorizeAccount('invoicing.customers.update');
        $save->handle($customer, $this->validated($request));
        $this->toast(__('invoicing.customers.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'digits:15'], 'cr_number' => ['nullable', 'digits_between:4,20'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'],
            'street' => ['nullable', 'string', 'max:255'], 'building_number' => ['nullable', 'digits:4'], 'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'digits:5'], 'country' => ['nullable', 'string', 'size:2'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerArray(Customer $customer): array
    {
        return [...$customer->attributesToArray(), 'name' => $customer->localized('name')];
    }
}

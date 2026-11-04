<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Customers\EraseCustomer;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Customer;
use App\Modules\Platform\Models\PrivacyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The PDPL toolkit: the register of people's requests about their data with the 30-day clock,
 * a full copy of a customer's data, and erasure.
 */
class PrivacyController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function index(Request $request): Response
    {
        $this->authorizeAccount(SuiteFeature::Customers->key('update'));
        $status = (string) $request->query('status', 'open');

        return $this->suitePage('Suite/Privacy', [
            'requests' => PrivacyRequest::query()->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->orderBy('status')->orderBy('due_on')->limit(200)->get()->map(fn (PrivacyRequest $privacyRequest): array => [
                    ...$privacyRequest->only(['id', 'kind', 'subject', 'customer_id', 'name', 'contact', 'status', 'details', 'outcome']),
                    'received_on' => $privacyRequest->received_on->toDateString(), 'due_on' => $privacyRequest->due_on->toDateString(),
                    'overdue' => $privacyRequest->status === 'open' && $privacyRequest->due_on->lt(today()),
                ])->all(),
            'counts' => [
                'open' => PrivacyRequest::query()->where('status', 'open')->count(),
                'overdue' => PrivacyRequest::query()->where('status', 'open')->whereDate('due_on', '<', today())->count(),
                'consented' => Customer::query()->whereHas('registrations', fn ($query) => $query->whereNotNull('marketing_consent_at'))->count(),
                'customers' => Customer::query()->whereNull('erased_at')->count(),
            ],
            'filters' => ['status' => $status],
            'kinds' => PrivacyRequest::KINDS,
            'subjects' => PrivacyRequest::SUBJECTS,
            'tips' => trans('suite.privacy.tips'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Customers->key('update'));
        $data = $request->validate([
            'kind' => ['required', Rule::in(PrivacyRequest::KINDS)],
            'subject' => ['required', Rule::in(PrivacyRequest::SUBJECTS)],
            'customer_id' => ['nullable', 'integer', Rule::exists(Customer::class, 'id')->where('client_id', $request->user()->client_id)],
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:120'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $privacyRequest = new PrivacyRequest($data);
        $privacyRequest->due_on = $privacyRequest->received_on->copy()->addDays(PrivacyRequest::ANSWER_DAYS);
        $privacyRequest->save();
        $this->toast(__('suite.privacy.saved'));

        return back();
    }

    public function update(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Customers->key('update'));
        $data = $request->validate(['status' => ['required', 'in:open,done,refused'], 'outcome' => ['nullable', 'required_if:status,refused', 'string', 'max:2000']]);

        $privacyRequest->forceFill([...$data, 'handled_by' => $request->user()->id, 'closed_at' => $data['status'] === 'open' ? null : now()])->save();
        $this->toast(__('suite.privacy.saved'));

        return back();
    }

    /** Everything held about one customer, as a JSON file for an access request. */
    public function export(Customer $customer): StreamedResponse
    {
        $this->authorizeAccount(SuiteFeature::Customers->key('view'));

        return response()->streamDownload(
            fn () => print (json_encode(EraseCustomer::export($customer), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            "customer-{$customer->id}.json",
            ['Content-Type' => 'application/json'],
        );
    }

    public function erase(Request $request, Customer $customer, EraseCustomer $erase): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Customers->key('update'));
        abort_if($customer->erased_at !== null, 422);
        $request->validate(['confirm' => ['required', 'accepted']]);

        $erase->handle($customer, $request->user());
        $this->toast(__('suite.privacy.erased'));

        return back();
    }
}

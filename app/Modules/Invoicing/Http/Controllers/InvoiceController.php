<?php

namespace App\Modules\Invoicing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoicing\Actions\IssueInvoice;
use App\Modules\Invoicing\Actions\RenderInvoicePdf;
use App\Modules\Invoicing\Actions\SaveInvoice;
use App\Modules\Invoicing\Actions\SendPaymentLink;
use App\Modules\Invoicing\Enums\InvoiceKind;
use App\Modules\Invoicing\Enums\InvoiceStatus;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\InvoicingFeature;
use App\Modules\Invoicing\Enums\UnitStatus;
use App\Modules\Invoicing\Http\Controllers\Concerns\ScopesInvoicing;
use App\Modules\Invoicing\Http\Requests\SaveInvoiceRequest;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\InvoiceLine;
use App\Modules\Invoicing\Models\Submission;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use RendersSuitePages, ScopesInvoicing;

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'type', 'intercompany']);
        $companies = $this->companiesFor(InvoicingFeature::Invoices, FeatureAction::View);

        $invoices = Invoice::query()->with('customer')->whereIn('company_id', $companies->pluck('id'))
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query->where('number', 'like', "%{$search}%")->orWhereHas('customer', fn ($query) => $query->whereAny(['name_ar', 'name_en'], 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['intercompany'] ?? null, fn ($query, string $value) => $query->where('intercompany', $value === 'only'))
            ->latest('issue_date')->latest('id')->paginate(20)->withQueryString();

        $names = $companies->mapWithKeys(fn (CompanyRef $company): array => [$company->id => $company->name]);

        return $this->suitePage('Invoicing/Invoices/Index', [
            'invoices' => $invoices->through(fn (Invoice $invoice) => $this->summary($invoice, $names)),
            'filters' => $filters,
            'statuses' => InvoiceStatus::options(),
            'types' => InvoiceType::options(),
            'group' => $this->groupTotals($companies->pluck('id')->all(), $names),
        ], ['invoicing']);
    }

    /**
     * This year's issued sales per company, split into outside customers and the group's own
     * companies (I2). The group total leaves intercompany sales out, since they cancel out.
     *
     * @param  list<int>  $companyIds
     * @return array{companies: list<array{company: ?string, external: string, intercompany: string}>, group_external: string, intercompany: string}
     */
    private function groupTotals(array $companyIds, $names): array
    {
        $rows = Invoice::query()->whereIn('company_id', $companyIds)->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Rejected])
            ->whereYear('issue_date', today()->year)->where('kind', 'invoice')
            ->selectRaw('company_id, intercompany, sum(total) as total')->groupBy('company_id', 'intercompany')->get();

        $companies = $rows->groupBy('company_id')->map(fn ($group, $companyId): array => [
            'company' => $names[$companyId] ?? null,
            'external' => number_format((float) $group->where('intercompany', false)->sum('total'), 2, '.', ''),
            'intercompany' => number_format((float) $group->where('intercompany', true)->sum('total'), 2, '.', ''),
        ])->values()->all();

        return [
            'companies' => $companies,
            'group_external' => number_format((float) $rows->where('intercompany', false)->sum('total'), 2, '.', ''),
            'intercompany' => number_format((float) $rows->where('intercompany', true)->sum('total'), 2, '.', ''),
        ];
    }

    public function create(): Response
    {
        return $this->suitePage('Invoicing/Invoices/Form', ['invoice' => null, ...$this->formOptions()], ['invoicing']);
    }

    public function store(SaveInvoiceRequest $request, SaveInvoice $save): RedirectResponse
    {
        $this->authorizeCompanyFor(InvoicingFeature::Invoices, FeatureAction::Create, (int) $request->validated('company_id'));
        $invoice = $save->handle(new Invoice, (int) $request->validated('company_id'), $request->safe()->except(['company_id', 'lines']), $request->validated('lines'));
        $this->toast(__('invoicing.invoices.saved'));

        return to_route('invoicing.invoices.show', $invoice);
    }

    public function show(Invoice $invoice): Response
    {
        $this->authorizeCompanyFor(InvoicingFeature::Invoices, FeatureAction::View, $invoice->company_id);
        $invoice->load(['lines', 'customer', 'related', 'submissions', 'unit']);
        $company = app(OrgDirectory::class)->company($invoice->company_id);

        return $this->suitePage('Invoicing/Invoices/Show', [
            'invoice' => [
                ...$this->summary($invoice, collect([$invoice->company_id => $company?->name])),
                'uuid' => $invoice->uuid, 'due_date' => $invoice->due_date?->toDateString(), 'supply_date' => $invoice->supply_date?->toDateString(),
                'payment_means' => $invoice->payment_means, 'note' => $invoice->note, 'reason' => $invoice->reason,
                'related' => $invoice->related?->number, 'discount' => (string) $invoice->discount, 'rounding' => (string) $invoice->rounding, 'payable' => $invoice->payableAmount(), 'qr' => $invoice->qr, 'hash' => $invoice->hash, 'counter' => $invoice->counter,
                'unit' => $invoice->unit?->name, 'issued_at' => $invoice->issued_at?->toIso8601String(), 'paid_at' => $invoice->paid_at?->toIso8601String(),
                'customer_phone' => $invoice->customer?->phone, 'intercompany' => $invoice->intercompany,
                'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => ['id' => $line->id, 'description' => $line->description, 'quantity' => (string) $line->quantity, 'unit_price' => (string) $line->unit_price, 'discount' => (string) $line->discount, 'vat_rate' => (string) $line->vat_rate, 'net' => (string) $line->net, 'vat' => (string) $line->vat, 'total' => (string) $line->total])->all(),
                'submissions' => $invoice->submissions->map(fn (Submission $submission): array => ['id' => $submission->id, 'mode' => $submission->mode, 'environment' => $submission->environment, 'status' => $submission->status, 'counter' => $submission->counter, 'submitted_at' => $submission->submitted_at->toIso8601String(), 'response' => $submission->response])->all(),
            ],
            'units' => EinvoiceUnit::query()->where('company_id', $invoice->company_id)->whereIn('status', [UnitStatus::Compliance, UnitStatus::Production])->get()->map(fn (EinvoiceUnit $unit): array => ['value' => $unit->id, 'label' => "{$unit->name} · ".$unit->environment->label()])->all(),
            'can' => [
                'edit' => $invoice->isDraft() && $this->scope()->branchesFor(InvoicingFeature::Invoices->key('update'), $this->access())->allowsWholeCompany($invoice->company_id),
                'issue' => in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Rejected], true) && $this->access()->allowsBranch(InvoicingFeature::Invoices->key('issue'), 0, $invoice->company_id),
                'cancel' => $invoice->isDraft() && $this->access()->allowsBranch(InvoicingFeature::Invoices->key('cancel'), 0, $invoice->company_id),
                'export' => $invoice->submissions->isNotEmpty(),
                'collect' => $invoice->paid_at === null && in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Cleared, InvoiceStatus::Reported], true)
                    && $this->access()->allowsBranch(InvoicingFeature::Invoices->key('collect'), 0, $invoice->company_id),
            ],
        ], ['invoicing']);
    }

    /** WhatsApp the customer a link to pay this invoice online (I3). */
    public function paymentLink(Request $request, Invoice $invoice, SendPaymentLink $send): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(InvoicingFeature::Invoices->key('collect'), 0, $invoice->company_id), 403);
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30']]);

        $url = $send->handle($invoice->load('customer'), $data['phone'] ?? null);
        Inertia::flash('payment_url', $url);
        $this->toast(__('invoicing.payment_link_sent'));

        return back();
    }

    public function edit(Invoice $invoice): Response
    {
        $this->authorizeCompanyFor(InvoicingFeature::Invoices, FeatureAction::Update, $invoice->company_id);
        abort_unless($invoice->isDraft(), 403);
        $invoice->load('lines');

        return $this->suitePage('Invoicing/Invoices/Form', [
            'invoice' => [
                'id' => $invoice->id, 'company_id' => $invoice->company_id, 'customer_id' => $invoice->customer_id, 'type' => $invoice->type->value, 'kind' => $invoice->kind->value,
                'related_invoice_id' => $invoice->related_invoice_id, 'issue_date' => $invoice->issue_date->toDateString(), 'due_date' => $invoice->due_date?->toDateString(),
                'supply_date' => $invoice->supply_date?->toDateString(), 'payment_means' => $invoice->payment_means, 'note' => $invoice->note, 'reason' => $invoice->reason,
                'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => ['description' => $line->description, 'quantity' => (string) $line->quantity, 'unit_price' => (string) $line->unit_price, 'discount' => (string) $line->discount, 'vat_rate' => (string) $line->vat_rate])->all(),
            ],
            ...$this->formOptions(),
        ], ['invoicing']);
    }

    public function update(SaveInvoiceRequest $request, Invoice $invoice, SaveInvoice $save): RedirectResponse
    {
        $this->authorizeCompanyFor(InvoicingFeature::Invoices, FeatureAction::Update, $invoice->company_id);
        $save->handle($invoice, $invoice->company_id, $request->safe()->except(['company_id', 'lines']), $request->validated('lines'));
        $this->toast(__('invoicing.invoices.saved'));

        return to_route('invoicing.invoices.show', $invoice);
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(InvoicingFeature::Invoices->key('issue'), 0, $invoice->company_id), 403);
        $data = $request->validate(['unit_id' => ['required', 'integer']]);

        $invoice = $issue->handle($invoice, EinvoiceUnit::query()->findOrFail($data['unit_id']), $request->user());
        $this->toast($invoice->status === InvoiceStatus::Rejected ? __('invoicing.invoices.rejected') : __('invoicing.invoices.issued', ['number' => $invoice->number]), $invoice->status === InvoiceStatus::Rejected ? 'error' : 'success');

        return back();
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->isDraft() && $this->access()->allowsBranch(InvoicingFeature::Invoices->key('cancel'), 0, $invoice->company_id), 403);
        $invoice->forceFill(['status' => InvoiceStatus::Cancelled->value])->save();
        $this->toast(__('invoicing.invoices.cancelled'));

        return back();
    }

    public function xml(Invoice $invoice): HttpResponse
    {
        $this->authorizeCompanyFor(InvoicingFeature::Invoices, FeatureAction::Export, $invoice->company_id);
        $submission = $invoice->submissions()->where('status', 'like', 'accepted%')->first() ?? $invoice->submissions()->firstOrFail();

        return response($submission->xml, 200, ['Content-Type' => 'application/xml', 'Content-Disposition' => 'attachment; filename="'.($invoice->number ?? $invoice->uuid).'.xml"']);
    }

    public function pdf(Invoice $invoice, RenderInvoicePdf $render): HttpResponse
    {
        $this->authorizeCompanyFor(InvoicingFeature::Invoices, FeatureAction::View, $invoice->company_id);

        return response($render->handle($invoice), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.($invoice->number ?? $invoice->uuid).'.pdf"']);
    }

    /**
     * @param  Collection<int, string|null>  $companyNames
     * @return array<string, mixed>
     */
    private function summary(Invoice $invoice, $companyNames): array
    {
        return [
            'id' => $invoice->id, 'number' => $invoice->number, 'company_id' => $invoice->company_id, 'company' => $companyNames->get($invoice->company_id),
            'customer' => $invoice->customer?->localized('name'), 'type' => $invoice->type->value, 'kind' => $invoice->kind->value, 'status' => $invoice->status->value,
            'issue_date' => $invoice->issue_date->toDateString(), 'subtotal' => (string) $invoice->subtotal, 'vat' => (string) $invoice->vat, 'total' => (string) $invoice->total,
            'intercompany' => $invoice->intercompany, 'paid' => $invoice->paid_at !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $companies = $this->companiesFor(InvoicingFeature::Invoices, FeatureAction::Create);

        return [
            'companies' => $companies->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name, 'ready' => (bool) $company->vatNumber])->all(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name_en')->get()->map(fn (Customer $customer): array => ['value' => $customer->id, 'label' => $customer->localized('name'), 'vat' => $customer->isVatRegistered() && $customer->city !== null])->all(),
            'issued' => Invoice::query()->whereIn('company_id', $companies->pluck('id'))->whereIn('status', [InvoiceStatus::Cleared, InvoiceStatus::Reported])->latest('issue_date')->limit(200)->get(['id', 'number', 'company_id'])->map(fn (Invoice $invoice): array => ['value' => $invoice->id, 'label' => $invoice->number, 'company_id' => $invoice->company_id])->all(),
            'types' => InvoiceType::options(), 'kinds' => InvoiceKind::options(),
            'payment_means' => collect(trans('invoicing.invoices.payment'))->map(fn ($label, $code) => ['value' => (string) $code, 'label' => $label])->values()->all(),
        ];
    }
}

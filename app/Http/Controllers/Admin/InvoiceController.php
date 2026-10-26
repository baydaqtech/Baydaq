<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Invoices\ChangeInvoiceStatus;
use App\Actions\Invoices\SaveInvoice;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveInvoiceRequest;
use App\Http\Resources\Admin\InvoiceResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class InvoiceController extends Controller
{
    use RendersAdminPages;

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'client_id', 'status']);

        $invoices = Invoice::query()
            ->with(['client', 'project'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('number', 'like', "%{$search}%"))
            ->when($filters['client_id'] ?? null, fn (Builder $query, string $clientId) => $query->where('client_id', $clientId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('issue_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return $this->adminPage('Admin/Invoices/Index', [
            'invoices' => InvoiceResource::collection($invoices),
            'filters' => $filters,
            'options' => ['clients' => $this->clientOptions(), 'statuses' => InvoiceStatus::options()],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Invoice::class);

        return $this->adminPage('Admin/Invoices/Form', [
            'invoice' => null,
            'defaults' => [
                'client_id' => $request->integer('client_id') ?: null,
                'project_id' => $request->integer('project_id') ?: null,
                'issue_date' => today()->toDateString(),
                'due_date' => today()->addDays(14)->toDateString(),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function store(SaveInvoiceRequest $request, SaveInvoice $saveInvoice): RedirectResponse
    {
        $invoice = $saveInvoice->handle(new Invoice, $request->safe()->except('items'), $request->validated('items'));
        $this->toast(__('admin.invoices.created'));

        return to_route('admin.invoices.show', $invoice);
    }

    public function show(Invoice $invoice): Response
    {
        return $this->adminPage('Admin/Invoices/Show', [
            'invoice' => new InvoiceResource($invoice->load(['client', 'project', 'items'])),
            'canManage' => request()->user()->can('changeStatus', $invoice),
        ]);
    }

    public function edit(Invoice $invoice): Response
    {
        Gate::authorize('update', $invoice);

        return $this->adminPage('Admin/Invoices/Form', [
            'invoice' => new InvoiceResource($invoice->load(['client', 'project', 'items'])),
            'defaults' => null,
            'options' => $this->formOptions(),
        ]);
    }

    public function update(SaveInvoiceRequest $request, Invoice $invoice, SaveInvoice $saveInvoice): RedirectResponse
    {
        $saveInvoice->handle($invoice, $request->safe()->except('items'), $request->validated('items'));
        $this->toast(__('admin.common.saved'));

        return to_route('admin.invoices.show', $invoice);
    }

    public function send(Invoice $invoice, ChangeInvoiceStatus $changeInvoiceStatus): RedirectResponse
    {
        return $this->move($invoice, InvoiceStatus::Sent, 'admin.invoices.sent', $changeInvoiceStatus);
    }

    public function pay(Invoice $invoice, ChangeInvoiceStatus $changeInvoiceStatus): RedirectResponse
    {
        return $this->move($invoice, InvoiceStatus::Paid, 'admin.invoices.paid', $changeInvoiceStatus);
    }

    public function void(Invoice $invoice, ChangeInvoiceStatus $changeInvoiceStatus): RedirectResponse
    {
        return $this->move($invoice, InvoiceStatus::Void, 'admin.invoices.voided', $changeInvoiceStatus);
    }

    private function move(Invoice $invoice, InvoiceStatus $to, string $message, ChangeInvoiceStatus $changeInvoiceStatus): RedirectResponse
    {
        Gate::authorize('changeStatus', $invoice);

        $changeInvoiceStatus->handle($invoice, $to);
        $this->toast(__($message));

        return back();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function clientOptions(): array
    {
        return Client::query()->orderBy('name_en')->get()
            ->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->localized('name')])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'clients' => $this->clientOptions(),
            'projects' => Project::query()->orderBy('name_en')->get()
                ->map(fn (Project $project): array => ['value' => $project->id, 'label' => $project->localized('name'), 'client_id' => $project->client_id])
                ->all(),
            'vatRate' => config('baydaq.vat_rate'),
        ];
    }
}

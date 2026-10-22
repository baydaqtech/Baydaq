<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientStatus;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveClientRequest;
use App\Http\Resources\Admin\ClientResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\ProjectResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ClientController extends Controller
{
    use RendersAdminPages;

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);

        $clients = Client::query()
            ->withCount(['projects', 'users'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->whereAny(['name_ar', 'name_en', 'contact_name', 'contact_email'], 'like', "%{$search}%"),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return $this->adminPage('Admin/Clients/Index', [
            'clients' => ClientResource::collection($clients),
            'filters' => $filters,
            'statuses' => ClientStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return $this->adminPage('Admin/Clients/Form', ['client' => null, 'statuses' => ClientStatus::options()]);
    }

    public function store(SaveClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());
        $this->toast(__('admin.clients.created'));

        return to_route('admin.clients.show', $client);
    }

    public function show(Client $client): Response
    {
        $client->load([
            'users' => fn ($query) => $query->orderBy('name'),
            'projects' => fn ($query) => $query->latest(),
            'invoices' => fn ($query) => $query->latest('issue_date')->limit(10),
        ]);

        return $this->adminPage('Admin/Clients/Show', [
            'client' => new ClientResource($client),
            'users' => UserResource::collection($client->users),
            'projects' => ProjectResource::collection($client->projects),
            'invoices' => InvoiceResource::collection($client->invoices),
        ]);
    }

    public function edit(Client $client): Response
    {
        return $this->adminPage('Admin/Clients/Form', [
            'client' => new ClientResource($client),
            'statuses' => ClientStatus::options(),
        ]);
    }

    public function update(SaveClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());
        $this->toast(__('admin.common.saved'));

        return to_route('admin.clients.show', $client);
    }
}

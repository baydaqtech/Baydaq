<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Requests\ApproveRequest;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RequestResource;
use App\Models\ClientRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Partnership and service requests from the public forms.
 */
class RequestController extends Controller
{
    use RendersAdminPages;

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'type', 'status']);

        $requests = ClientRequest::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->whereAny(['name', 'company', 'email'], 'like', "%{$search}%"),
            ))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return $this->adminPage('Admin/Requests/Index', [
            'requests' => RequestResource::collection($requests),
            'filters' => $filters,
            'options' => ['types' => RequestType::options(), 'statuses' => RequestStatus::options()],
        ]);
    }

    public function show(ClientRequest $clientRequest): Response
    {
        return $this->adminPage('Admin/Requests/Show', [
            'request' => new RequestResource($clientRequest->load(['handler', 'client'])),
            'statuses' => RequestStatus::options(),
        ]);
    }

    /**
     * Update the working status (new / reviewing) and the team's notes.
     */
    public function update(Request $request, ClientRequest $clientRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([RequestStatus::New->value, RequestStatus::Reviewing->value])],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $clientRequest->forceFill([...$validated, 'handled_by' => $request->user()->id])->save();
        $this->toast(__('admin.common.saved'));

        return back();
    }

    public function approve(Request $request, ClientRequest $clientRequest, ApproveRequest $approveRequest): RedirectResponse
    {
        abort_if($clientRequest->status === RequestStatus::Approved, 409);

        $invited = $approveRequest->handle($clientRequest, $request->user());
        $this->toast(__($invited ? 'admin.requests.approved' : 'admin.requests.approved_existing'));

        return back();
    }

    public function reject(Request $request, ClientRequest $clientRequest): RedirectResponse
    {
        abort_if($clientRequest->status === RequestStatus::Approved, 409);

        $clientRequest->forceFill(['status' => RequestStatus::Rejected, 'handled_by' => $request->user()->id])->save();
        $this->toast(__('admin.requests.rejected'));

        return back();
    }
}

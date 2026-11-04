<?php

namespace App\Http\Controllers;

use App\Actions\Requests\SubmitRequest;
use App\Enums\RequestType;
use App\Http\Controllers\Concerns\RendersSitePages;
use App\Http\Requests\StoreClientRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class RequestController extends Controller
{
    use RendersSitePages;

    /**
     * The partnership or service request form.
     */
    public function create(RequestType $type): Response
    {
        return $this->sitePage('RequestForm', [
            'type' => $type->value,
            'services' => $type === RequestType::Service
                ? Service::query()->listed()->get()->map(fn (Service $service): array => [
                    'key' => $service->key,
                    'name' => $service->localized('name'),
                ])
                : [],
        ], ['requests']);
    }

    public function store(StoreClientRequest $request, RequestType $type, SubmitRequest $submitRequest): RedirectResponse
    {
        // Bots get the same thank-you; nothing is saved or sent.
        if (! $request->isSpam()) {
            $submitRequest->handle($type, [
                ...$request->safe()->except('website'),
                'locale' => app()->getLocale(),
                'ip' => $request->ip(),
            ]);
        }

        return back()->with('status', __('requests.sent'));
    }
}

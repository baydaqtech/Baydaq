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
     * The project or service request form, with where it sends to and the address of the other form.
     */
    public function create(RequestType $requestType): Response
    {
        $other = $requestType === RequestType::Service ? RequestType::Partnership : RequestType::Service;

        return $this->sitePage('RequestForm', [
            'type' => $requestType->value,
            'action' => "/request/{$requestType->slug()}",
            'switchTo' => "/request/{$other->slug()}",
            'services' => $requestType === RequestType::Service
                ? Service::query()->listed()->get()->map(fn (Service $service): array => [
                    'key' => $service->key,
                    'name' => $service->localized('name'),
                ])
                : [],
        ], ['requests']);
    }

    public function store(StoreClientRequest $request, RequestType $requestType, SubmitRequest $submitRequest): RedirectResponse
    {
        // Bots get the same thank-you; nothing is saved or sent.
        if (! $request->isSpam()) {
            $submitRequest->handle($requestType, [
                ...$request->safe()->except('website'),
                'locale' => app()->getLocale(),
                'ip' => $request->ip(),
            ]);
        }

        return back()->with('status', __('requests.sent'));
    }
}

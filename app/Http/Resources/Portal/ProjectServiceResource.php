<?php

namespace App\Http\Resources\Portal;

use App\Models\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service the client's project uses. The key itself stays with Baydaq's backends; the client
 * sees whether the service is connected and what it used.
 *
 * @mixin ProjectService
 */
class ProjectServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->service->localized('name'),
            'description' => $this->service->localized('description'),
            'icon' => $this->service->icon,
            'docs_url' => $this->service->docs_url,
            'unit' => $this->service->unit,
            'status' => $this->status->value,
            'plan' => $this->plan,
            'price' => $this->price,
            'monthly_cap' => $this->monthly_cap,
            'pricing_model' => $this->pricing_model->value,
            'started_at' => $this->started_at?->toDateString(),
            'connected' => $this->hasApiKey(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
        ];
    }
}

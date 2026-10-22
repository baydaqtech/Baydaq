<?php

namespace App\Http\Resources\Portal;

use App\Models\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service the client's project uses. API keys arrive with the service APIs (phase 3)
 * and are never part of a page.
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
            'pricing_model' => $this->pricing_model->value,
            'started_at' => $this->started_at?->toDateString(),
        ];
    }
}

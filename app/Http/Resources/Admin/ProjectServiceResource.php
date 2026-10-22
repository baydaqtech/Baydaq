<?php

namespace App\Http\Resources\Admin;

use App\Models\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A service turned on for a project. The API key never leaves the server here.
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
            'service' => [
                'id' => $this->service->id,
                'key' => $this->service->key,
                'name' => $this->service->localized('name'),
                'icon' => $this->service->icon,
                'docs_url' => $this->service->docs_url,
                'unit' => $this->service->unit,
            ],
            'status' => $this->status->value,
            'plan' => $this->plan,
            'price' => $this->price,
            'pricing_model' => $this->pricing_model->value,
            'started_at' => $this->started_at?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}

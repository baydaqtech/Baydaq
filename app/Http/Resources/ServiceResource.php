<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A platform service card on the public site, in the visitor's language.
 *
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->localized('name'),
            'description' => $this->localized('description'),
            'icon' => $this->icon,
            'docs_url' => $this->docs_url,
            'default_price' => $this->default_price,
            'pricing_model' => $this->pricing_model->value,
            'unit' => $this->unit,
        ];
    }
}

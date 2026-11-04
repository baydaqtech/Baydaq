<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A product card on the public site, in the visitor's language.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->localized('name'),
            'description' => $this->localized('description'),
            'logo_url' => $this->logo ? Storage::disk(config('baydaq.logo_disk'))->url($this->logo) : null,
            'url' => $this->url,
        ];
    }
}

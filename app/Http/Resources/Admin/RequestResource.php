<?php

namespace App\Http\Resources\Admin;

use App\Models\ClientRequest;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientRequest
 */
class RequestResource extends JsonResource
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
            'type' => $this->type->value,
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
            'services' => $this->services
                ? Service::query()->whereIn('key', $this->services)->get()->map->localized('name')->values()
                : [],
            'status' => $this->status->value,
            'admin_notes' => $this->admin_notes,
            'locale' => $this->locale,
            'handler' => $this->whenLoaded('handler', fn () => $this->handler?->name),
            'client' => $this->whenLoaded('client', fn () => $this->client ? ['id' => $this->client->id, 'name' => $this->client->localized('name')] : null),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

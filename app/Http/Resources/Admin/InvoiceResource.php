<?php

namespace App\Http\Resources\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $outstanding = in_array($this->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'name' => $this->client->localized('name')]),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project ? ['id' => $this->project->id, 'slug' => $this->project->slug, 'name' => $this->project->localized('name')] : null),
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status->value,
            'subtotal' => $this->subtotal,
            'vat_rate' => $this->vat_rate,
            'vat' => $this->vat,
            'total' => $this->total,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'einvoice_number' => $this->einvoice_number,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map->only(['id', 'description', 'quantity', 'unit_price', 'total'])),
            'can' => [
                'edit' => $this->status === InvoiceStatus::Draft,
                'send' => $this->status === InvoiceStatus::Draft,
                'pay' => $outstanding,
                'void' => $this->status === InvoiceStatus::Draft || $outstanding,
            ],
        ];
    }
}

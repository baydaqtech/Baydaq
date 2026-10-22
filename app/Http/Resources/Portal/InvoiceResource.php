<?php

namespace App\Http\Resources\Portal;

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
        return [
            'id' => $this->id,
            'number' => $this->number,
            'project' => $this->whenLoaded('project', fn () => $this->project?->localized('name')),
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status->value,
            'total' => $this->total,
            'currency' => $this->currency,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'pdf_url' => route('portal.invoices.pdf', $this->resource),
        ];
    }
}

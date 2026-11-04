<?php

namespace App\Modules\Hr\Http\Resources;

use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Hr\Models\EmployeeDocumentFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeDocument
 */
class EmployeeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type_id' => $this->document_type_id,
            'type' => $this->whenLoaded('type', fn () => $this->type->localized('name')),
            'number' => $this->number,
            'issued_on' => $this->issued_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'days_left' => $this->expires_on ? (int) today()->diffInDays($this->expires_on, false) : null,
            'data' => $this->data ?? [],
            'notes' => $this->notes,
            'files' => $this->whenLoaded('files', fn () => $this->files->map(fn (EmployeeDocumentFile $file): array => [
                'id' => $file->id,
                'name' => $file->name,
                'size' => $file->size,
                'mime' => $file->mime,
            ])->all()),
        ];
    }
}

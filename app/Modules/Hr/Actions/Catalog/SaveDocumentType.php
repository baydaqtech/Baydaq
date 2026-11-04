<?php

namespace App\Modules\Hr\Actions\Catalog;

use App\Modules\Hr\Models\DocumentType;
use Illuminate\Support\Str;

class SaveDocumentType
{
    /**
     * Save a document type with its field schema. Field keys are derived from the English
     * label once and then kept, so stored documents keep matching their fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(DocumentType $type, array $data): DocumentType
    {
        $fields = [];

        foreach ($data['fields'] ?? [] as $field) {
            $fields[] = [
                'key' => $field['key'] ?: Str::slug($field['label_en'], '_'),
                'label_ar' => $field['label_ar'],
                'label_en' => $field['label_en'],
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_values(array_filter(array_map('trim', $field['options'] ?? []))),
            ];
        }

        $type->fill([
            ...$data,
            'fields' => $fields,
            'alert_days' => collect($data['alert_days'] ?? [])->map(fn ($days): int => (int) $days)->filter()->unique()->sortDesc()->values()->all(),
        ])->save();

        return $type;
    }
}

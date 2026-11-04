<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Enums\DocumentFieldType;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules are built from the document type's own field schema, so a type the owner invents is
 * validated like a built-in one.
 */
class SaveEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->documentType();

        $rules = [
            'document_type_id' => ['required', Rule::exists('hr__document_types', 'id')->where('client_id', app(CurrentTenant::class)->id())],
            'number' => [$type?->has_number ? 'required' : 'nullable', 'string', 'max:60'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => [$type?->has_expiry ? 'required' : 'nullable', 'date', 'after_or_equal:issued_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'data' => ['nullable', 'array'],
        ];

        foreach ($type?->fields ?? [] as $field) {
            $rules["data.{$field['key']}"] = [
                $field['required'] ? 'required' : 'nullable',
                ...match (DocumentFieldType::from($field['type'])) {
                    DocumentFieldType::Text => ['string', 'max:255'],
                    DocumentFieldType::Number => ['numeric'],
                    DocumentFieldType::Date => ['date'],
                    DocumentFieldType::Select => [Rule::in($field['options'] ?? [])],
                    DocumentFieldType::Boolean => ['boolean'],
                },
            ];
        }

        return $rules;
    }

    public function documentType(): ?DocumentType
    {
        $document = $this->route('document');

        return $document ? $document->type : DocumentType::query()->find($this->integer('document_type_id'));
    }
}

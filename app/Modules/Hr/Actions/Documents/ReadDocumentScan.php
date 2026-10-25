<?php

namespace App\Modules\Hr\Actions\Documents;

use App\Modules\Hr\Enums\DocumentFieldType;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Platform\Ports\Intelligence;
use Illuminate\Http\UploadedFile;
use stdClass;

/**
 * AI document capture (H7): read a photo or PDF of an iqama, passport or insurance card into the
 * document's number, dates and the type's own fields, for HR to check before saving. Nothing is
 * stored here.
 */
class ReadDocumentScan
{
    public function __construct(private Intelligence $intelligence) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return array{number: ?string, issued_on: ?string, expires_on: ?string, data: array<string, mixed>}
     */
    public function handle(DocumentType $type, array $files): array
    {
        $fields = collect($type->fields ?? [])->filter(fn (array $field): bool => ! empty($field['key']));
        $date = ['type' => ['string', 'null'], 'description' => 'Gregorian date as YYYY-MM-DD. Convert Hijri dates to Gregorian. null if not shown.'];

        $schema = [
            'type' => 'object',
            'properties' => [
                'number' => ['type' => ['string', 'null'], 'description' => 'The document number, digits and letters only. null if not shown.'],
                'issued_on' => $date,
                'expires_on' => $date,
                'data' => [
                    'type' => 'object',
                    'properties' => $fields->mapWithKeys(fn (array $field): array => [$field['key'] => $this->fieldSchema($field)])->all() ?: new stdClass,
                    'required' => $fields->pluck('key')->values()->all(),
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['number', 'issued_on', 'expires_on', 'data'],
            'additionalProperties' => false,
        ];

        $labels = $fields->map(fn (array $field): string => "- {$field['key']}: {$field['label_en']} / {$field['label_ar']}")->implode("\n");
        $instructions = "These images are one {$type->name_en} ({$type->name_ar}) of an employee in Saudi Arabia. "
            .'Read the document number, the issue date and the expiry date'.($labels !== '' ? ", and these fields:\n{$labels}\n" : '. ')
            .'Copy values exactly as printed; for a field that is not on the document, return null. Do not guess.';

        $read = $this->intelligence->extract($instructions, array_map(fn (UploadedFile $file): array => [
            'media_type' => $file->getMimeType() === 'image/jpg' ? 'image/jpeg' : (string) $file->getMimeType(),
            'data' => base64_encode((string) file_get_contents($file->getRealPath())),
        ], $files), $schema);

        return [
            'number' => $type->has_number ? $this->clean($read['number'] ?? null) : null,
            'issued_on' => $this->date($read['issued_on'] ?? null),
            'expires_on' => $type->has_expiry ? $this->date($read['expires_on'] ?? null) : null,
            'data' => $fields->mapWithKeys(fn (array $field): array => [$field['key'] => $read['data'][$field['key']] ?? null])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function fieldSchema(array $field): array
    {
        return match (DocumentFieldType::tryFrom((string) ($field['type'] ?? 'text'))) {
            DocumentFieldType::Number => ['type' => ['number', 'null']],
            DocumentFieldType::Boolean => ['type' => ['boolean', 'null']],
            DocumentFieldType::Date => ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'],
            DocumentFieldType::Select => ['type' => ['string', 'null'], 'enum' => [...array_values($field['options'] ?? []), null]],
            default => ['type' => ['string', 'null']],
        };
    }

    private function clean(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }
}

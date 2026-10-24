<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Hr\Database\Factories\DocumentTypeFactory;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of employee document the owner defines (iqama, passport, insurance, degree...) with
 * its own extra fields and alert thresholds. New kinds need no code.
 *
 * @property list<array{key: string, label_ar: string, label_en: string, type: string, required: bool, options?: list<string>}>|null $fields
 * @property list<int>|null $alert_days
 */
#[Table('hr__document_types')]
#[Fillable(['name_ar', 'name_en', 'has_number', 'has_expiry', 'alert_days', 'fields', 'is_active', 'sort_order'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'has_number' => true,
        'has_expiry' => true,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_number' => 'boolean',
            'has_expiry' => 'boolean',
            'alert_days' => 'array',
            'fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): DocumentTypeFactory
    {
        return DocumentTypeFactory::new();
    }
}

<?php

namespace App\Modules\Hr\Database\Factories;

use App\Modules\Hr\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'إقامة',
            'name_en' => 'Iqama',
            'has_number' => true,
            'has_expiry' => true,
            'alert_days' => [90, 30, 7],
            'fields' => [
                ['key' => 'profession', 'label_ar' => 'المهنة', 'label_en' => 'Profession', 'type' => 'text', 'required' => true, 'options' => []],
                ['key' => 'sponsor', 'label_ar' => 'الكفيل', 'label_en' => 'Sponsor', 'type' => 'text', 'required' => false, 'options' => []],
            ],
        ];
    }
}

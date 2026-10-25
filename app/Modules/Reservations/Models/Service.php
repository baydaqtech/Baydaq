<?php

namespace App\Modules\Reservations\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Something a customer books: a haircut, a court hour, a chalet night. Priced including VAT.
 */
#[Table('res__services')]
#[Fillable(['company_id', 'name_ar', 'name_en', 'description_ar', 'description_en', 'duration_minutes', 'buffer_minutes', 'price', 'vat_rate', 'deposit', 'needs_staff', 'is_stay', 'check_in_time', 'check_out_time', 'is_online', 'is_active', 'color', 'sort_order'])]
class Service extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true, 'is_online' => true, 'needs_staff' => false, 'buffer_minutes' => 0, 'deposit' => 0, 'vat_rate' => 15];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer', 'buffer_minutes' => 'integer', 'price' => 'decimal:2', 'vat_rate' => 'decimal:2', 'deposit' => 'decimal:2',
            'needs_staff' => 'boolean', 'is_stay' => 'boolean', 'is_online' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<\App\Modules\Reservations\Models\Resource, $this>
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'res__service_resource', 'service_id', 'resource_id');
    }

    /**
     * HR employee ids who can perform this service.
     *
     * @return list<int>
     */
    public function staffIds(): array
    {
        return $this->getConnection()->table('res__service_staff')->where('service_id', $this->id)->orderBy('employee_id')->pluck('employee_id')->map(fn ($id): int => (int) $id)->all();
    }
}

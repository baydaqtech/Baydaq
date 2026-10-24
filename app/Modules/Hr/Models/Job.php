<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Hr\Database\Factories\JobFactory;
use App\Modules\Hr\Models\Concerns\HasNormalizedNames;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A job name (CEO, HR Officer, Cashier...), shared and unique per owner. It may be linked to
 * several sections and blocked for some companies.
 */
#[Table('hr__jobs')]
#[Fillable(['name_ar', 'name_en', 'is_active', 'sort_order'])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory, HasNormalizedNames;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsToMany<Section, $this>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'hr__job_section');
    }

    /**
     * @return list<int>
     */
    public function blockedCompanyIds(): array
    {
        return $this->getConnection()->table('hr__job_company_blocks')->where('job_id', $this->id)->pluck('company_id')->map(fn ($id): int => (int) $id)->all();
    }

    protected static function newFactory(): JobFactory
    {
        return JobFactory::new();
    }
}

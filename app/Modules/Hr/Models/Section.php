<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Hr\Database\Factories\SectionFactory;
use App\Modules\Hr\Models\Concerns\HasNormalizedNames;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A job category (Accounting, IT, Management...), shared by every company of the owner.
 */
#[Table('hr__sections')]
#[Fillable(['name_ar', 'name_en', 'is_active', 'sort_order'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
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
     * @return BelongsToMany<Job, $this>
     */
    public function jobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'hr__job_section');
    }

    /**
     * Companies where this section may not be used for new placements.
     *
     * @return list<int>
     */
    public function blockedCompanyIds(): array
    {
        return $this->getConnection()->table('hr__section_company_blocks')->where('section_id', $this->id)->pluck('company_id')->map(fn ($id): int => (int) $id)->all();
    }

    protected static function newFactory(): SectionFactory
    {
        return SectionFactory::new();
    }
}

<?php

namespace App\Models;

use App\Enums\PricingModel;
use App\Enums\ProjectServiceStatus;
use Database\Factories\ProjectServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalog service turned on for one project, with its own plan and price.
 */
#[Fillable(['service_id', 'status', 'plan', 'price', 'monthly_cap', 'pricing_model', 'started_at', 'notes'])]
#[Hidden(['api_key'])]
class ProjectService extends Model
{
    /** @use HasFactory<ProjectServiceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectServiceStatus::class,
            'pricing_model' => PricingModel::class,
            'price' => 'decimal:2',
            'monthly_cap' => 'decimal:2',
            'api_key' => 'encrypted',
            'started_at' => 'date',
            'api_key_issued_at' => 'datetime',
            'last_used_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<ServiceUsage, $this>
     */
    public function usage(): HasMany
    {
        return $this->hasMany(ServiceUsage::class);
    }

    public function hasApiKey(): bool
    {
        return $this->api_key_issued_at !== null;
    }
}

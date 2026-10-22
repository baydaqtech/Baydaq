<?php

namespace App\Models;

use App\Enums\ProjectRelationship;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Concerns\HasBilingualFields;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something Baydaq builds or runs for a client: either "powered by Baydaq"
 * (a partnership or a Baydaq product) or the client's own project using Baydaq services.
 */
#[Fillable([
    'client_id', 'name_ar', 'name_en', 'slug', 'logo', 'description_ar', 'description_en',
    'type', 'relationship', 'partnership_share', 'status', 'progress',
    'started_at', 'expected_launch_at', 'launched_at', 'drive_url', 'repo_url', 'app_url',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planning',
        'progress' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'relationship' => ProjectRelationship::class,
            'status' => ProjectStatus::class,
            'partnership_share' => 'decimal:2',
            'progress' => 'integer',
            'started_at' => 'date',
            'expected_launch_at' => 'date',
            'launched_at' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isPoweredByBaydaq(): bool
    {
        return $this->type === ProjectType::PoweredByBaydaq;
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(ProjectResource::class);
    }

    /**
     * @return HasMany<ProjectFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    /**
     * @return HasMany<ProjectService, $this>
     */
    public function projectServices(): HasMany
    {
        return $this->hasMany(ProjectService::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}

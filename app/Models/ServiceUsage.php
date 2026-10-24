<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of usage of a project's technical service. Written by RecordUsage only.
 */
#[Table('service_usage')]
class ServiceUsage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['usage_date' => 'date', 'quantity' => 'decimal:2', 'cost' => 'decimal:2', 'meta' => 'array'];
    }

    /**
     * @return BelongsTo<ProjectService, $this>
     */
    public function projectService(): BelongsTo
    {
        return $this->belongsTo(ProjectService::class);
    }
}

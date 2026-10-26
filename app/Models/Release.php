<?php

namespace App\Models;

use App\Enums\ReleaseChannel;
use App\Enums\ReleasePlatform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A build or store version of a project: TestFlight, Play internal testing, staging or live.
 */
#[Fillable(['version', 'platform', 'channel', 'url', 'notes', 'released_at'])]
class Release extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => ReleasePlatform::class,
            'channel' => ReleaseChannel::class,
            'released_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

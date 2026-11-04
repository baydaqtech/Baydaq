<?php

namespace App\Modules\Platform\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Database\Factories\BranchFactory;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A place a company works from. Every company has exactly one head office branch,
 * so nothing is ever branch-less. Scope always resolves down to branches.
 */
#[Table('platform__branches')]
#[Fillable(['company_id', 'code', 'name_ar', 'name_en', 'city_ar', 'city_en', 'status'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_head_office' => false,
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_head_office' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The token behind the branch's client sign-up QR code, made the first time it is asked for.
     */
    public function joinToken(): string
    {
        if ($this->join_token === null) {
            $this->forceFill(['join_token' => Str::random(32)])->save();
        }

        return $this->join_token;
    }

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }
}

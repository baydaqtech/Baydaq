<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A scope (segment plus branch filter) a user pinned or visited lately.
 */
#[Table('platform__scope_shortcuts')]
#[Fillable(['name', 'pinned'])]
class ScopeShortcut extends Model
{
    use BelongsToClient;

    /** Recent, unpinned scopes kept per user. */
    public const RECENT = 6;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pinned' => 'boolean', 'last_used_at' => 'datetime'];
    }

    /**
     * Remember that the user looked at this scope, keeping only the latest few unpinned ones.
     *
     * @param  list<int>  $branches
     */
    public static function visit(int $userId, string $segment, array $branches): void
    {
        static::for($userId, $segment, $branches)->forceFill(['last_used_at' => now()])->save();

        $stale = static::query()->where('user_id', $userId)->where('pinned', false)->orderByDesc('last_used_at')->skip(self::RECENT)->take(100)->pluck('id');

        if ($stale->isNotEmpty()) {
            static::query()->whereKey($stale)->delete();
        }
    }

    /**
     * The user's shortcut for this scope, or a new unsaved one.
     *
     * @param  list<int>  $branches
     */
    public static function for(int $userId, string $segment, array $branches): self
    {
        $key = ['user_id' => $userId, 'segment' => $segment, 'branches' => implode(',', $branches)];

        return static::query()->where($key)->first() ?? (new static)->forceFill($key);
    }

    /** @return list<int> */
    public function branchIds(): array
    {
        return $this->branches === '' ? [] : array_map('intval', explode(',', $this->branches));
    }
}

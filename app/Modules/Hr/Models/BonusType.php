<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of bonus (performance, Eid, project...), shared by the owner.
 */
#[Table('hr__bonus_types')]
#[Fillable(['name_ar', 'name_en', 'is_active', 'sort_order'])]
class BonusType extends Model
{
    use BelongsToClient, HasBilingualFields;

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
        return ['is_active' => 'boolean'];
    }

    /** The account's own bonus type for something the system pays (overtime, recognition), made on first use. */
    public static function system(string $key, string $nameAr, string $nameEn, int $clientId): self
    {
        $type = self::query()->where('system_key', $key)->first();

        if ($type === null) {
            $type = new self(['name_ar' => $nameAr, 'name_en' => $nameEn, 'is_active' => true, 'sort_order' => 99]);
            $type->forceFill(['client_id' => $clientId, 'system_key' => $key])->save();
        }

        return $type;
    }
}

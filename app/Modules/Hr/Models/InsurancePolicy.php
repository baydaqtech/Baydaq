<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company's medical insurance policy with one insurer for a period (CCHI).
 */
#[Table('hr__insurance_policies')]
#[Fillable(['insurer', 'policy_number', 'starts_on', 'ends_on', 'notes'])]
class InsurancePolicy extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    /**
     * @return HasMany<InsuranceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(InsuranceMember::class, 'policy_id');
    }
}

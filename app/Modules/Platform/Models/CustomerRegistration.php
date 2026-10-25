<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer registered as a client of an organization, a company or a branch.
 */
#[Table('platform__customer_registrations')]
class CustomerRegistration extends Model
{
    use BelongsToClient;

    public const SCOPES = ['organization', 'company', 'branch'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['marketing_consent_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

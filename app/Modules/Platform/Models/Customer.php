<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Data\PhoneNumber;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person who buys, books or subscribes anywhere in the account, known by phone number.
 * The wallet balance changes only through WalletCustomers.
 */
#[Table('platform__customers')]
#[Fillable(['name', 'email', 'birth_date', 'gender', 'notes'])]
class Customer extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['wallet_balance' => 'decimal:2', 'points' => 'integer', 'lifetime_points' => 'integer', 'birth_date' => 'date', 'erased_at' => 'datetime'];
    }

    /**
     * @return HasMany<WalletEntry, $this>
     */
    public function walletEntries(): HasMany
    {
        return $this->hasMany(WalletEntry::class)->latest('id');
    }

    /**
     * @return HasMany<PointEntry, $this>
     */
    public function pointEntries(): HasMany
    {
        return $this->hasMany(PointEntry::class)->latest('id');
    }

    /**
     * @return HasMany<CustomerRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(CustomerRegistration::class);
    }

    /** Phones in one form (see PhoneNumber), so the same person is found however it was typed. */
    public static function normalizePhone(string $phone): string
    {
        return PhoneNumber::normalize($phone);
    }
}

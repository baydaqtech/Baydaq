<?php

namespace App\Modules\Platform\Models;

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
#[Fillable(['name', 'email', 'notes'])]
class Customer extends Model
{
    use BelongsToClient;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['wallet_balance' => 'decimal:2'];
    }

    /**
     * @return HasMany<WalletEntry, $this>
     */
    public function walletEntries(): HasMany
    {
        return $this->hasMany(WalletEntry::class)->latest('id');
    }

    /**
     * Saudi numbers in one form, 9665XXXXXXXX, so 05…, +9665… and 9665… are the same person.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return match (true) {
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '05') && strlen($digits) === 10 => '966'.substr($digits, 1),
            str_starts_with($digits, '5') && strlen($digits) === 9 => '966'.$digits,
            default => $digits,
        };
    }
}

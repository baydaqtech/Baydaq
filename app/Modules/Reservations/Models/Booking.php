<?php

namespace App\Modules\Reservations\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Reservations\Enums\BookingChannel;
use App\Modules\Reservations\Enums\BookingStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's slot: a service at a branch, on a resource and/or with a staff member.
 */
#[Table('res__bookings')]
#[Fillable(['customer_name', 'customer_phone', 'customer_email', 'party_size', 'note'])]
class Booking extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'confirmed', 'channel' => 'staff', 'party_size' => 1, 'deposit' => 0, 'paid_amount' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class, 'channel' => BookingChannel::class,
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'blocks_until' => 'datetime', 'deposit_paid_at' => 'datetime',
            'price' => 'decimal:2', 'deposit' => 'decimal:2', 'paid_amount' => 'decimal:2', 'party_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<\App\Modules\Reservations\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /** Bookings that hold their slot and overlap [from, until). */
    #[Scope]
    protected function holding(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->whereIn('status', BookingStatus::blocking())->where('starts_at', '<', $until)->where('blocks_until', '>', $from);
    }
}

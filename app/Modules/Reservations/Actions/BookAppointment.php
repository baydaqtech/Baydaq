<?php

namespace App\Modules\Reservations\Actions;

use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Reservations\Enums\BookingChannel;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Models\BranchSettings;
use App\Modules\Reservations\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookAppointment
{
    public function __construct(private FindSlots $slots, private CurrentTenant $tenant, private Audit $audit, private OrgDirectory $directory) {}

    /**
     * Book a service at a branch. Bookings at one branch are serialised (the branch's settings row
     * is locked) and the slot is re-checked inside the lock, so two people cannot take the last
     * chair. A chosen resource or staff member must be free; otherwise the first free one is used.
     * Online bookings that need a deposit wait as "pending" until it is received.
     *
     * @param  array{customer_name: string, customer_phone: string, customer_email?: ?string, party_size?: int, note?: ?string, resource_id?: ?int, employee_id?: ?int}  $data
     */
    public function handle(Service $service, int $branchId, string $startsAt, array $data, BookingChannel $channel, ?Authenticatable $actor = null): Booking
    {
        return DB::transaction(function () use ($service, $branchId, $startsAt, $data, $channel, $actor): Booking {
            $clientId = $this->tenant->client()->id;
            BranchSettings::forBranch($branchId, $clientId);
            BranchSettings::query()->whereKey($branchId)->lockForUpdate()->first();

            $timezone = $this->directory->company($service->company_id)?->timezone ?? 'Asia/Riyadh';
            $start = CarbonImmutable::parse($startsAt)->setTimezone($timezone);
            $partySize = max(1, (int) ($data['party_size'] ?? 1));
            $local = collect($this->slots->handle($service, $branchId, $start->toDateString(), $channel === BookingChannel::Online, $partySize))
                ->first(fn (array $slot): bool => CarbonImmutable::parse($slot['starts_at'])->eq($start));

            if ($local === null) {
                throw ValidationException::withMessages(['starts_at' => __('reservations.errors.slot_taken')]);
            }

            $resourceId = $this->pick($data['resource_id'] ?? null, $local['resource_ids'], 'resource_id');
            $employeeId = $this->pick($data['employee_id'] ?? null, $local['employee_ids'], 'employee_id');
            $deposit = $channel === BookingChannel::Online ? (string) $service->deposit : '0';

            $booking = (new Booking(collect($data)->only(['customer_name', 'customer_phone', 'customer_email', 'note'])->all()))->forceFill([
                'client_id' => $clientId,
                'company_id' => $service->company_id,
                'branch_id' => $branchId,
                'service_id' => $service->id,
                'resource_id' => $resourceId,
                'employee_id' => $employeeId,
                'reference' => $this->reference(),
                'starts_at' => $start->utc(),
                'ends_at' => $start->addMinutes($service->duration_minutes)->utc(),
                'blocks_until' => $start->addMinutes($service->duration_minutes + $service->buffer_minutes)->utc(),
                'status' => ((float) $deposit > 0 ? BookingStatus::Pending : BookingStatus::Confirmed)->value,
                'channel' => $channel->value,
                'party_size' => $partySize,
                'price' => (string) $service->price,
                'deposit' => $deposit,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);
            $booking->save();

            $this->audit->record($booking, 'booked', ['service' => $service->id, 'starts_at' => $booking->starts_at->toIso8601String(), 'channel' => $channel->value], $actor);

            return $booking;
        });
    }

    /**
     * @param  list<int>  $free
     */
    private function pick(?int $wanted, array $free, string $field): ?int
    {
        if ($wanted !== null && ! in_array($wanted, $free, true)) {
            throw ValidationException::withMessages([$field => __('reservations.errors.not_free')]);
        }

        return $wanted ?? ($free[0] ?? null);
    }

    private function reference(): string
    {
        do {
            $reference = Str::upper(Str::random(6));
        } while (Booking::withoutGlobalScopes()->where('reference', $reference)->exists() || preg_match('/[O0I1]/', $reference));

        return $reference;
    }
}

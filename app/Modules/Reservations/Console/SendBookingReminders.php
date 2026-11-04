<?php

namespace App\Modules\Reservations\Console;

use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Support\BookingMessages;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'reservations:send-reminders';

    protected $description = 'WhatsApp a reminder for confirmed bookings starting within the next 24 hours';

    public function handle(CurrentTenant $tenant): int
    {
        $due = fn () => Booking::query()->where('status', BookingStatus::Confirmed)->whereNull('reminded_at')
            ->whereBetween('starts_at', [now()->addHours(2), now()->addDay()]);
        $sent = 0;

        foreach (Booking::withoutGlobalScopes()->where('status', BookingStatus::Confirmed)->whereNull('reminded_at')->whereBetween('starts_at', [now()->addHours(2), now()->addDay()])->distinct()->pluck('client_id') as $clientId) {
            $tenant->runFor($clientId, function () use ($due, &$sent): void {
                foreach ($due()->with('service')->get() as $booking) {
                    app(BookingMessages::class)->reminder($booking);
                    $sent++;
                }
            });
        }

        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}

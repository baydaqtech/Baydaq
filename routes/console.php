<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:mark-overdue')->dailyAt('00:10')->withoutOverlapping();

// HR: movements start on their date, document alerts go out once a day (Riyadh time).
Schedule::command('hr:apply-movements')->dailyAt('00:20');
Schedule::command('hr:notify-expiring-documents')->dailyAt('07:00');
Schedule::command('hr:recharge-secondments')->monthlyOn(1, '03:00');

// Subscriptions: memberships past their last day expire just after midnight (Riyadh time).
Schedule::command('subscriptions:expire')->dailyAt('00:30');
Schedule::command('reservations:send-reminders')->hourly();

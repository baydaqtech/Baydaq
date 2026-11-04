<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every job runs on one server at a time and never overlaps itself (no double reminders or postings).
Schedule::command('invoices:mark-overdue')->dailyAt('00:10')->withoutOverlapping()->onOneServer();

// Expired app tokens are deleted a day after they run out.
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('01:00')->withoutOverlapping()->onOneServer();

// HR: movements start on their date, document alerts go out once a day (Riyadh time).
Schedule::command('hr:apply-movements')->dailyAt('00:20')->withoutOverlapping()->onOneServer();
Schedule::command('hr:accept-overdue-resignations')->dailyAt('00:25')->withoutOverlapping()->onOneServer();
Schedule::command('hr:notify-expiring-documents')->dailyAt('07:00')->withoutOverlapping()->onOneServer();
Schedule::command('hr:send-messages')->dailyAt('08:00')->timezone('Asia/Riyadh')->withoutOverlapping()->onOneServer();

// Morning alerts in the notification centre (and by email unless switched off).
Schedule::command('suite:notify-approvals')->dailyAt('07:45')->timezone('Asia/Riyadh')->withoutOverlapping()->onOneServer();
Schedule::command('finance:notify-bills-due')->dailyAt('07:50')->timezone('Asia/Riyadh')->withoutOverlapping()->onOneServer();
Schedule::command('suite:notify-vat')->dailyAt('07:55')->timezone('Asia/Riyadh')->withoutOverlapping()->onOneServer();
Schedule::command('hr:recharge-secondments')->monthlyOn(1, '03:00')->withoutOverlapping()->onOneServer();

// Subscriptions: memberships past their last day expire just after midnight (Riyadh time).
Schedule::command('subscriptions:expire')->dailyAt('00:30')->withoutOverlapping()->onOneServer();
Schedule::command('reservations:send-reminders')->hourly()->withoutOverlapping()->onOneServer();

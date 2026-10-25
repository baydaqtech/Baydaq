<?php

/*
| WhatsApp message texts. The keys are also the template names sent to Baydaq's WhatsApp
| service, which maps them to the approved WhatsApp templates.
*/

return [
    'hr_leave_decided' => 'Hello :name, your :type leave from :from to :to was :status.',
    'hr_advance_decided' => 'Hello :name, your salary advance of :amount SAR was :status.',
    'hr_payslip_ready' => 'Hello :name, your salary for :month has been paid: :amount SAR.',
    'hr_document_expiring' => 'Hello :name, your :document expires on :date (:days days left). Please renew it.',
    'hr_task_assigned' => 'Hello :name, you have a new task: :title (due :due). Open the Baydaq app for details.',
    'hr_shift_assigned' => 'Hello :name, you have a shift at :branch on :date, :from–:to.',
    'res_booking_confirmed' => 'Hello :name, your booking :number for :service on :date at :time is confirmed.',
    'res_booking_reminder' => 'Reminder: :name, your booking :number for :service is tomorrow, :date at :time.',
    'inv_payment_link' => 'Hello :name, invoice :number for :amount SAR is ready. Pay securely here: :url',
];

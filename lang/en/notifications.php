<?php

return [
    'open' => 'Open Baydaq',

    // Kinds a user can switch email off for, in the order the settings page lists them.
    'kinds' => ['suite.approvals_waiting', 'hr.document_expiring', 'finance.bills_due', 'gov.vat_due'],

    'suite_approvals_waiting' => [
        'title' => 'Waiting on you',
        'body' => 'Requests waiting for your decision: :count.',
        'setting' => 'Requests waiting for my decision (once a day)',
    ],
    'hr_document_expiring' => [
        'title' => 'A document is expiring',
        'body' => ':type of :employee expires on :date (:days days left).',
        'setting' => 'Employee documents about to expire',
    ],
    'finance_bills_due' => [
        'title' => 'Supplier bills due',
        'body' => 'Supplier bills of :company due by :date: :count, :total SAR in all.',
        'setting' => 'Supplier bills due within three days',
    ],
    'gov_vat_due' => [
        'title' => 'VAT return due',
        'body' => 'The VAT return for the last quarter is due on :date (:days days left).',
        'setting' => 'VAT return deadline (a week and a day before)',
    ],
];

<?php

return [
    'open' => 'فتح بيدق',

    // Kinds a user can switch email off for, in the order the settings page lists them.
    'kinds' => ['suite.approvals_waiting', 'hr.document_expiring', 'finance.bills_due', 'gov.vat_due'],

    'suite_approvals_waiting' => [
        'title' => 'بانتظار قرارك',
        'body' => 'طلبات تنتظر قرارك: :count.',
        'setting' => 'الطلبات التي تنتظر قراري (مرة يومياً)',
    ],
    'hr_document_expiring' => [
        'title' => 'وثيقة قاربت على الانتهاء',
        'body' => 'تنتهي :type للموظف :employee في :date (بقي :days يوماً).',
        'setting' => 'وثائق الموظفين القريبة من الانتهاء',
    ],
    'finance_bills_due' => [
        'title' => 'فواتير موردين مستحقة',
        'body' => 'فواتير موردين على :company مستحقة حتى :date: :count، بإجمالي :total ريال.',
        'setting' => 'فواتير الموردين المستحقة خلال ثلاثة أيام',
    ],
    'gov_vat_due' => [
        'title' => 'موعد إقرار ضريبة القيمة المضافة',
        'body' => 'إقرار الربع الماضي مستحق في :date (بقي :days يوماً).',
        'setting' => 'موعد إقرار الضريبة (قبل أسبوع وقبل يوم)',
    ],
];

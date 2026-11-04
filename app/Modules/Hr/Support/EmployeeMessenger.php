<?php

namespace App\Modules\Hr\Support;

use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Messaging;

/**
 * WhatsApp to an employee about their own HR matters (H14), when the tenant switched it on and
 * the employee has a phone. Sent in the current language.
 */
class EmployeeMessenger
{
    public function __construct(private Messaging $messaging, private HrSettings $settings) {}

    /**
     * @param  array<string, string|int|float>  $params
     */
    public function send(Employee $employee, string $template, array $params, ?string $context = null): void
    {
        if (! $this->settings->get('whatsapp_notifications') || ! $employee->phone) {
            return;
        }

        $locale = app()->getLocale();
        $this->messaging->send($employee->phone, $template, ['name' => $employee->localized('name'), ...$params], $locale, $context);
    }
}

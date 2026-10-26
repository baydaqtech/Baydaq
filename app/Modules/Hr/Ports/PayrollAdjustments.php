<?php

namespace App\Modules\Hr\Ports;

/**
 * Command port: another module asks HR to take money from an employee's pay (an asset they
 * broke or lost, a cash shortage at the till). HR records pending deductions inside the Labor
 * Law caps, spreading a large amount over the following months, and HR approves them as usual.
 */
interface PayrollAdjustments
{
    /**
     * @param  string  $origin  where it came from, e.g. "assets:INC-00012"
     * @param  'damage'|'cash_shortage'|'staff_meal'  $kind  which deduction type it is filed under
     * @return list<array{id: int, pay_month: string, amount: string}> the deductions created
     */
    public function requestDeduction(int $employeeId, string $amount, string $reason, string $origin, string $kind = 'damage'): array;

    /**
     * Ask HR to pay an employee extra (a sales commission): a pending bonus for next month's payroll.
     *
     * @param  string  $origin  e.g. "pos:shift:41"
     */
    public function requestBonus(int $employeeId, string $amount, string $reason, string $origin): int;
}

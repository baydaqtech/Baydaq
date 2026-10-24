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
     * @return list<array{id: int, pay_month: string, amount: string}> the deductions created
     */
    public function requestDeduction(int $employeeId, string $amount, string $reason, string $origin): array;
}

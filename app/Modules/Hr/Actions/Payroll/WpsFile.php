<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Platform\Ports\OrgDirectory;

/**
 * The salary file for the Mudad / WPS upload: one row per employee with the bank account, the
 * basic, housing, other earnings and deductions, and the net. CSV with the column names Mudad
 * expects; the bank reference columns are left for the client to fill where their bank differs.
 */
class WpsFile
{
    public function __construct(private OrgDirectory $directory) {}

    public function handle(PayrollRun $run): string
    {
        $company = $this->directory->company($run->company_id);
        $rows = [[
            'Employee Number', 'Employee Name', 'National ID / Iqama', 'IBAN', 'Basic Salary', 'Housing Allowance',
            'Other Earnings', 'Deductions', 'Net Salary', 'Pay Month', 'Company', 'Establishment GOSI No.',
        ]];

        foreach ($run->lines()->with(['employee.activeContract.allowances.type'])->get() as $line) {
            $employee = $line->employee;
            $housing = $employee->activeContract?->allowances->first(fn ($allowance) => str_contains(strtolower($allowance->type->name_en), 'housing'))?->amount ?? '0.00';

            $rows[] = [
                $employee->employee_number,
                $employee->name_en,
                $employee->national_id ?? '',
                $employee->iban ?? '',
                (string) $line->basic,
                (string) $housing,
                (string) bcadd(bcsub((string) $line->allowances, (string) $housing, 2), (string) $line->bonuses, 2),
                (string) bcsub((string) $line->gross, (string) $line->net, 2),
                (string) $line->net,
                $run->pay_month->format('Y-m'),
                $company?->nameEn ?? '',
                '',
            ];
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF"); // BOM so Excel reads Arabic names correctly

        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}

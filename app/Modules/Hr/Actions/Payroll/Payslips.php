<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Enums\PayrollStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\PayrollLine;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Ports\PdfRenderer;
use Brick\Math\BigDecimal;

/**
 * An employee's payslips: their line of every approved or paid payroll run, as a summary for the
 * page and the app, and as an A4 PDF in their language. A draft run is not a payslip yet.
 */
class Payslips
{
    public function __construct(private PdfRenderer $pdf, private OrgDirectory $directory) {}

    /**
     * @return list<array{id: int, month: string, status: string, paid_on: ?string, gross: string, taken: string, net: string, breakdown: list<array<string, string>>}>
     */
    public function forEmployee(Employee $employee, int $limit = 24): array
    {
        return PayrollLine::query()->where('employee_id', $employee->id)
            ->whereHas('run', fn ($query) => $query->whereIn('status', [PayrollStatus::Approved, PayrollStatus::Paid]))
            ->with('run')->get()
            ->sortByDesc(fn (PayrollLine $line): string => $line->run->pay_month->toDateString())->take($limit)
            ->map(fn (PayrollLine $line): array => self::summary($line))->values()->all();
    }

    /** One payslip, or null when it is not this employee's or its run is still a draft. */
    public function find(Employee $employee, int $lineId): ?PayrollLine
    {
        $line = PayrollLine::query()->with('run')->whereKey($lineId)->where('employee_id', $employee->id)->first();

        return $line !== null && in_array($line->run->status, [PayrollStatus::Approved, PayrollStatus::Paid], true) ? $line : null;
    }

    /**
     * @return array{id: int, month: string, status: string, paid_on: ?string, gross: string, taken: string, net: string, breakdown: list<array<string, string>>}
     */
    public static function summary(PayrollLine $line): array
    {
        return [
            'id' => $line->id,
            'month' => $line->run->pay_month->format('Y-m'),
            'status' => $line->run->status->value,
            'paid_on' => $line->run->paid_on?->toDateString(),
            'gross' => (string) $line->gross,
            'taken' => (string) BigDecimal::of((string) $line->gross)->minus((string) $line->net)->toScale(2),
            'net' => (string) $line->net,
            'breakdown' => array_values($line->breakdown ?? []),
        ];
    }

    public function pdf(PayrollLine $line, string $locale): string
    {
        $line->loadMissing(['run', 'employee.job']);

        return $this->pdf->render('pdf.hr-payslip', [
            'line' => $line,
            'company' => $this->directory->company($line->employee->company_id),
            'branch' => $this->directory->branch($line->branch_id),
            'locale' => in_array($locale, ['ar', 'en'], true) ? $locale : 'ar',
        ], self::filename($line));
    }

    public static function filename(PayrollLine $line): string
    {
        return 'payslip-'.$line->employee->employee_number.'-'.$line->run->pay_month->format('Y-m');
    }
}

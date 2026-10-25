<?php

namespace App\Modules\Hr\Actions\Payroll;

use App\Modules\Hr\Models\PayrollLine;
use App\Modules\Hr\Models\PayrollRun;
use Brick\Math\BigDecimal;

/**
 * "What changed" since the company's previous run, so whoever approves sees joiners, leavers
 * and every net that moved (and which parts moved it) before signing off.
 */
class PayrollDiff
{
    /** The parts of a line compared between months. */
    public const PARTS = ['basic', 'allowances', 'bonuses', 'deductions', 'advance_installments', 'unpaid_leave', 'gosi_employee'];

    /**
     * @return array{previous_month: ?string, net_change: string, joined: list<array{employee_id: int, name: string, net: string}>, left: list<array{employee_id: int, name: string, net: string}>, changed: list<array{employee_id: int, name: string, from: string, to: string, change: string, parts: array<string, string>}>}|null
     */
    public function handle(PayrollRun $run): ?array
    {
        $previous = PayrollRun::query()->where('company_id', $run->company_id)->where('pay_month', '<', $run->pay_month)->orderByDesc('pay_month')->first();

        if ($previous === null) {
            return null;
        }

        $lines = fn (PayrollRun $of) => $of->lines()->with('employee:id,name_ar,name_en')->get()->keyBy('employee_id');
        $now = $lines($run);
        $before = $lines($previous);
        $row = fn (PayrollLine $line): array => ['employee_id' => $line->employee_id, 'name' => $line->employee->localized('name'), 'net' => (string) $line->net];

        $changed = $now->intersectByKeys($before)->map(function (PayrollLine $line) use ($before): ?array {
            $old = $before[$line->employee_id];
            $parts = collect(self::PARTS)
                ->mapWithKeys(fn (string $part): array => [$part => (string) BigDecimal::of((string) $line->{$part})->minus((string) $old->{$part})])
                ->reject(fn (string $change): bool => BigDecimal::of($change)->isZero())->all();

            if (BigDecimal::of((string) $line->net)->isEqualTo((string) $old->net) && $parts === []) {
                return null;
            }

            return [
                'employee_id' => $line->employee_id, 'name' => $line->employee->localized('name'),
                'from' => (string) $old->net, 'to' => (string) $line->net,
                'change' => (string) BigDecimal::of((string) $line->net)->minus((string) $old->net), 'parts' => $parts,
            ];
        })->filter()->sortByDesc(fn (array $row): float => abs((float) $row['change']))->values()->all();

        return [
            'previous_month' => $previous->pay_month->format('Y-m'),
            'net_change' => (string) BigDecimal::of((string) $run->total_net)->minus((string) $previous->total_net),
            'joined' => $now->diffKeys($before)->map($row)->values()->all(),
            'left' => $before->diffKeys($now)->map($row)->values()->all(),
            'changed' => $changed,
        ];
    }
}

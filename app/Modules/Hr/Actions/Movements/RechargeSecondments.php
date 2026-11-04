<?php

namespace App\Modules\Hr\Actions\Movements;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Enums\MovementStatus;
use App\Modules\Hr\Enums\MovementType;
use App\Modules\Hr\Models\EmployeeMovement;
use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bill host companies for people seconded to them from another company of the owner (H3).
 * The home company keeps paying the salary, so for each month it charges the host the daily
 * wage × days spent there, one draft invoice per company pair, through the Invoicing port.
 */
class RechargeSecondments
{
    public function __construct(private DailyWage $wage, private InvoiceIssuer $invoices, private Entitlements $entitlements, private CurrentTenant $tenant) {}

    /** @return int draft invoices created */
    public function handle(Carbon $month): int
    {
        if (! $this->entitlements->isActive(Module::Invoicing)) {
            return 0;
        }

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $movements = EmployeeMovement::query()->with('employee')
            ->where('type', MovementType::Secondment)->whereIn('status', [MovementStatus::Active, MovementStatus::Completed])
            ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)
            ->whereNotIn('id', DB::table('hr__recharges')->where('month', $start->toDateString())->select('movement_id'))->get()
            ->filter(fn (EmployeeMovement $movement): bool => $movement->to_company_id !== $movement->employee->company_id);

        $created = 0;

        foreach ($movements->groupBy(fn (EmployeeMovement $movement): string => $movement->employee->company_id.'>'.$movement->to_company_id) as $pair) {
            $lines = [];
            $rows = [];

            foreach ($pair as $movement) {
                $days = (int) Carbon::parse(max($movement->starts_on->toDateString(), $start->toDateString()))->diffInDays(Carbon::parse(min($movement->ends_on->toDateString(), $end->toDateString()))) + 1;
                $daily = $this->wage->daily($this->wage->contractOf($movement->employee));
                $amount = $daily->multipliedBy($days)->toScale(2, RoundingMode::HalfUp);

                if ($amount->isPositive()) {
                    $lines[] = ['description' => __('hr.movements.recharge_line', ['name' => $movement->employee->localized('name'), 'days' => $days, 'month' => $start->format('Y-m')]), 'quantity' => $days, 'unit_price' => (string) $daily, 'vat_rate' => '15'];
                }

                $rows[] = ['movement' => $movement, 'days' => $days, 'amount' => $amount];
            }

            $first = $pair->first();
            $invoiceId = $lines === [] ? null : $this->invoices->draftIntercompany($first->employee->company_id, $first->to_company_id, $lines, 'hr.recharge', __('hr.movements.recharge_note', ['month' => $start->format('Y-m')]));

            foreach ($rows as $row) {
                DB::table('hr__recharges')->insert([
                    'client_id' => $this->tenant->client()->id, 'movement_id' => $row['movement']->id, 'month' => $start->toDateString(),
                    'from_company_id' => $row['movement']->employee->company_id, 'to_company_id' => $row['movement']->to_company_id,
                    'days' => $row['days'], 'amount' => (string) $row['amount'], 'invoice_id' => $invoiceId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $created += $invoiceId ? 1 : 0;
        }

        return $created;
    }
}

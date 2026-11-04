<?php

namespace App\Modules\Hr\Actions\Pay;

use App\Modules\Hr\Enums\AdvanceStatus;
use App\Modules\Hr\Models\Advance;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Ports\Audit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestAdvance
{
    /** Advance policy: up to 3 months' wage, repaid within 12 months, one open advance. */
    public const MAX_MONTHS = 3;

    /** Longest repayment plan an employee can ask for themselves. */
    public const MAX_INSTALLMENTS = 12;

    public const KINDS = ['advance', 'loan'];

    public function __construct(private Audit $audit, private DailyWage $wage, private HrSettings $settings) {}

    /**
     * The limits for a kind: advances are short (fixed policy); loans are longer, set by the owner,
     * and each installment stays within a share of the wage. One open request of each kind.
     *
     * @return array{max_months: int, max_installments: int, max_installment_percent: ?int}
     */
    public static function policy(string $kind): array
    {
        $settings = app(HrSettings::class);

        return $kind === 'loan'
            ? ['max_months' => (int) $settings->get('loan_max_months'), 'max_installments' => (int) $settings->get('loan_max_installments'), 'max_installment_percent' => (int) $settings->get('loan_max_installment_percent')]
            : ['max_months' => self::MAX_MONTHS, 'max_installments' => self::MAX_INSTALLMENTS, 'max_installment_percent' => null];
    }

    /**
     * Ask for an advance and lay out its installments (equal amounts, remainder on the last one).
     *
     * @param  array{amount: numeric-string|float|int, installments: int, first_installment_month: string, reason?: ?string}  $data
     */
    public function handle(Employee $employee, array $data, ?Authenticatable $actor = null): Advance
    {
        $kind = $data['kind'] ?? 'advance';
        $policy = self::policy($kind);
        $amount = BigDecimal::of((string) $data['amount'])->toScale(2, RoundingMode::HalfUp);
        $monthly = $this->wage->monthly($this->wage->contractOf($employee));
        $count = max(1, (int) $data['installments']);
        $first = Carbon::parse($data['first_installment_month'])->startOfMonth();

        if ($monthly->isPositive() && $amount->isGreaterThan($monthly->multipliedBy($policy['max_months']))) {
            throw ValidationException::withMessages(['amount' => __('hr.pay.errors.advance_too_large', ['months' => $policy['max_months']])]);
        }

        if ($count > $policy['max_installments']) {
            throw ValidationException::withMessages(['installments' => __('hr.pay.errors.too_many_installments', ['max' => $policy['max_installments']])]);
        }

        // A loan's installment stays within a share of the wage (Labor Law Art. 92).
        if ($policy['max_installment_percent'] !== null && $monthly->isPositive()
            && BigDecimal::of(self::schedule($amount, $count, $first)[0]['amount'])->isGreaterThan($monthly->multipliedBy($policy['max_installment_percent'])->dividedBy(100, 2, RoundingMode::Down))) {
            throw ValidationException::withMessages(['installments' => __('hr.pay.errors.installment_share', ['percent' => $policy['max_installment_percent']])]);
        }

        return DB::transaction(function () use ($employee, $data, $amount, $count, $first, $actor, $kind): Advance {
            // One open request of each kind: lock the employee so two requests can't both pass.
            Employee::query()->lockForUpdate()->whereKey($employee->id)->value('id');

            if (Advance::query()->where('employee_id', $employee->id)->where('kind', $kind)->whereIn('status', [AdvanceStatus::Pending, AdvanceStatus::Approved, AdvanceStatus::Disbursed])->exists()) {
                throw ValidationException::withMessages(['amount' => __($kind === 'loan' ? 'hr.pay.errors.loan_open' : 'hr.pay.errors.advance_open')]);
            }

            $advance = new Advance(['amount' => (string) $amount, 'installments' => $count, 'first_installment_month' => $first->toDateString(), 'reason' => $data['reason'] ?? null]);
            $advance->forceFill(['client_id' => $employee->client_id, 'employee_id' => $employee->id, 'kind' => $kind, 'status' => AdvanceStatus::Pending->value, 'requested_by' => $actor?->getAuthIdentifier()])->save();

            foreach (self::schedule($amount, $count, $first) as $line) {
                $advance->schedule()->make()->forceFill($line)->save();
            }

            $this->audit->record($advance, 'requested', ['kind' => $kind, 'amount' => (string) $amount, 'installments' => $count], $actor);

            return $advance->load('schedule');
        });
    }

    /**
     * Equal installments rounded down to the halala, the remainder on the last one.
     *
     * @return list<array{due_month: string, amount: string}>
     */
    public static function schedule(BigDecimal $amount, int $count, Carbon $first): array
    {
        $each = $amount->dividedBy($count, 2, RoundingMode::Down);
        $remainder = $amount->minus($each->multipliedBy($count));

        return array_map(fn (int $i): array => [
            'due_month' => $first->copy()->addMonths($i)->toDateString(),
            'amount' => (string) ($i === $count - 1 ? $each->plus($remainder) : $each),
        ], range(0, $count - 1));
    }
}

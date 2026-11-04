<?php

namespace App\Modules\Subscriptions\Actions;

use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Subscriptions\Enums\MembershipStatus;
use App\Modules\Subscriptions\Enums\PaymentMeans;
use App\Modules\Subscriptions\Enums\PlanGender;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;
use App\Modules\Subscriptions\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellMembership
{
    public function __construct(private InvoiceIssuer $invoices, private Entitlements $entitlements, private OrgDirectory $directory, private Audit $audit) {}

    /**
     * Sell a plan to a member, or renew one: a renewal starts the day after the current period
     * ends (or today, if it already ended). Periods of one member never overlap. The price is
     * invoiced as a simplified tax invoice when the tenant has Invoicing and a ready unit, except
     * for a corporate plan, which the company pays for in bulk (S1). A sale through a
     * buy-now-pay-later provider records which one (S2).
     */
    public function handle(Member $member, Plan $plan, PaymentMeans $means, ?string $startsOn = null, ?int $branchId = null, ?Authenticatable $actor = null, ?Membership $renewing = null, ?string $bnplProvider = null): Membership
    {
        if ($plan->company_id !== $member->company_id || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => __('subscriptions.errors.plan_unavailable')]);
        }

        if ($plan->gender !== PlanGender::Any && $member->gender !== $plan->gender->value) {
            throw ValidationException::withMessages(['plan_id' => __('subscriptions.errors.plan_gender')]);
        }

        $timezone = $this->directory->company($member->company_id)?->timezone ?? 'Asia/Riyadh';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = $startsOn !== null ? CarbonImmutable::parse($startsOn, $timezone)->startOfDay()
            : ($renewing ? max($today, CarbonImmutable::parse($renewing->ends_on->toDateString(), $timezone)->addDay()) : $today);

        if ($start->lt($today)) {
            throw ValidationException::withMessages(['starts_on' => __('subscriptions.errors.start_in_past')]);
        }

        $end = $plan->period_unit->lastDay($start, $plan->period_count);

        return DB::transaction(function () use ($member, $plan, $means, $branchId, $actor, $renewing, $start, $end, $bnplProvider): Membership {
            $overlaps = Membership::query()->where('member_id', $member->id)->where('status', MembershipStatus::Active)->lockForUpdate()
                ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->exists();

            if ($overlaps) {
                throw ValidationException::withMessages(['starts_on' => __('subscriptions.errors.overlap')]);
            }

            $membership = (new Membership)->forceFill([
                'client_id' => $member->client_id, 'company_id' => $member->company_id, 'member_id' => $member->id, 'plan_id' => $plan->id,
                'renewed_from_id' => $renewing?->id, 'sold_at_branch_id' => $branchId, 'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(),
                'sessions_total' => $plan->sessions, 'price' => (string) $plan->price, 'bnpl_provider' => $bnplProvider, 'sold_by' => $actor?->getAuthIdentifier(),
            ]);
            $membership->save();

            $unitId = ! $plan->is_corporate && $this->entitlements->isActive(Module::Invoicing) ? $this->invoices->readyUnitFor($member->company_id) : null;

            if ($unitId !== null) {
                $issued = $this->invoices->issueSimplified($member->company_id, $unitId, [[
                    'description' => $plan->localized('name').' · '.$start->toDateString().' → '.$end->toDateString(),
                    'quantity' => 1, 'unit_price_incl_vat' => (string) $plan->price, 'vat_rate' => (string) $plan->vat_rate,
                ]], $means->value, $member->number, 'subscriptions.membership');
                $membership->forceFill(['invoice_id' => $issued->id])->save();
            }

            $this->audit->record($membership, $renewing ? 'renewed' : 'sold', ['plan' => $plan->id, 'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(), 'means' => $means->value], $actor);

            return $membership;
        });
    }
}

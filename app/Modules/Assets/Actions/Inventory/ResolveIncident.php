<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Actions\ManageCustody;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\IncidentResolution;
use App\Modules\Assets\Enums\IncidentStatus;
use App\Modules\Assets\Models\Incident;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use App\Modules\Hr\Ports\PayrollAdjustments;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Entitlements;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolveIncident
{
    public function __construct(private StockLedger $ledger, private ManageCustody $custody, private PayrollAdjustments $payroll, private Entitlements $entitlements, private Audit $audit) {}

    /**
     * Settle an incident. "Charged" asks HR for pending deductions from the responsible employee
     * (inside the Labor Law caps, spread over months if needed); "recovered" puts the stock back in
     * a warehouse (or the asset back in service). A lost asset's custody closes once settled.
     */
    public function handle(Incident $incident, IncidentResolution $resolution, ?string $note = null, ?string $chargeAmount = null, ?Warehouse $recoverTo = null, ?Authenticatable $actor = null): Incident
    {
        $incident->loadMissing(['asset', 'variant.item']);

        if ($incident->status !== IncidentStatus::Open) {
            throw ValidationException::withMessages(['status' => __('assets.errors.incident_closed')]);
        }

        return DB::transaction(function () use ($incident, $resolution, $note, $chargeAmount, $recoverTo, $actor): Incident {
            $charged = null;

            if ($resolution === IncidentResolution::Charged) {
                if ($incident->responsible_employee_id === null || ! $this->entitlements->isActive(Module::Hr)) {
                    throw ValidationException::withMessages(['resolution' => __('assets.errors.no_one_to_charge')]);
                }

                $charged = BigDecimal::of($chargeAmount ?: (string) $incident->value);

                if (! $charged->isPositive()) {
                    throw ValidationException::withMessages(['charge_amount' => __('assets.errors.charge_amount')]);
                }

                $this->payroll->requestDeduction($incident->responsible_employee_id, (string) $charged->toScale(2), __('assets.incidents.deduction_reason', ['number' => $incident->number, 'item' => $incident->label()]), 'assets:'.$incident->number);
            }

            if ($resolution === IncidentResolution::Recovered) {
                if ($incident->variant_id) {
                    $recoverTo ?? throw ValidationException::withMessages(['warehouse_id' => __('assets.errors.recover_where')]);
                    $this->ledger->move($incident->variant, (string) $incident->quantity, null, Holder::warehouse($recoverTo->id), 'recovered', 'incident', $incident->id, $actor);
                } elseif ($incident->asset && $incident->asset->status === AssetStatus::Lost) {
                    $incident->asset->forceFill(['status' => $incident->asset->openCustody()->exists() ? AssetStatus::Assigned->value : AssetStatus::Available->value])->save();
                }
            } elseif ($incident->asset && $incident->asset->status === AssetStatus::Lost && ($open = $incident->asset->openCustody()->first())) {
                $this->custody->return($open, today()->toDateString(), __('assets.incident_kind.'.$incident->kind->value), $actor);
                $incident->asset->forceFill(['status' => AssetStatus::Lost->value])->save();
            }

            $incident->forceFill([
                'status' => IncidentStatus::Resolved->value, 'resolution' => $resolution->value, 'resolution_note' => $note,
                'charged_amount' => $charged ? (string) $charged->toScale(2) : null, 'resolved_by' => $actor?->getAuthIdentifier(), 'resolved_at' => now(),
            ])->save();
            $this->audit->record($incident, 'resolved', ['resolution' => $resolution->value, 'charged' => $charged ? (string) $charged : null], $actor);

            return $incident;
        });
    }
}

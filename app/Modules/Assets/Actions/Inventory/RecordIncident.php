<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\IncidentKind;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Incident;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Support\Holder;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class RecordIncident
{
    public function __construct(private StockLedger $ledger, private NextNumber $numbers, private CurrentTenant $tenant, private Audit $audit) {}

    /**
     * Note that a quantity (or a tagged asset) broke, went missing, was stolen… valued at its cost.
     * When the stock is still on the holder's books it is taken off them. A lost or stolen asset is
     * marked lost; its custody stays open until the incident is settled.
     *
     * @param  array{kind: string, quantity: string, description?: ?string}  $data
     */
    public function handle(array $data, ?ItemVariant $variant, ?Asset $asset, ?Holder $holder, string $sourceType, ?int $sourceId, int $companyId, ?int $responsibleEmployeeId, bool $takeFromHolder, ?Authenticatable $actor = null): Incident
    {
        $kind = IncidentKind::from($data['kind']);
        $quantity = $asset ? '1' : (string) BigDecimal::of($data['quantity'])->toScale(3, RoundingMode::HalfUp);

        return DB::transaction(function () use ($kind, $quantity, $variant, $asset, $holder, $sourceType, $sourceId, $companyId, $responsibleEmployeeId, $takeFromHolder, $data, $actor): Incident {
            $value = $asset ? $asset->loadMissing('category')->bookValue(today()) : (string) BigDecimal::of($quantity)->multipliedBy((string) $variant->unit_cost)->toScale(2, RoundingMode::HalfUp);

            $incident = (new Incident)->forceFill([
                'client_id' => $this->tenant->client()->id, 'company_id' => $companyId, 'number' => $this->numbers->for(Incident::class, 'INC'),
                'kind' => $kind->value, 'source_type' => $sourceType, 'source_id' => $sourceId, 'variant_id' => $variant?->id, 'asset_id' => $asset?->id,
                'quantity' => $quantity, 'value' => $value, 'holder_type' => $holder?->type->value, 'holder_id' => $holder?->id,
                'responsible_employee_id' => $responsibleEmployeeId, 'description' => $data['description'] ?? null, 'reported_by' => $actor?->getAuthIdentifier(),
            ]);
            $incident->save();

            if ($variant && $takeFromHolder && $holder) {
                $this->ledger->move($variant, $quantity, $holder, null, 'incident', 'incident', $incident->id, $actor);
            }

            if ($asset && in_array($kind, [IncidentKind::Lost, IncidentKind::Stolen, IncidentKind::Missing], true)) {
                $asset->forceFill(['status' => AssetStatus::Lost->value])->save();
            }

            $this->audit->record($incident, 'reported', ['kind' => $kind->value, 'quantity' => $quantity, 'value' => $value], $actor);

            return $incident;
        });
    }
}

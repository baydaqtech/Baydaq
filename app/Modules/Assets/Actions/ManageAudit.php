<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\AuditItemStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Audit;
use App\Modules\Assets\Models\AuditItem;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageAudit
{
    public function __construct(private CurrentTenant $tenant, private OrgDirectory $directory, private DisposeAsset $dispose) {}

    /** Start a stock-take: every asset at the branch that is not written off is expected. */
    public function start(int $branchId, ?string $note = null, ?Authenticatable $actor = null): Audit
    {
        $branch = $this->directory->branch($branchId) ?? throw ValidationException::withMessages(['branch_id' => __('assets.errors.branch_company')]);

        if (Audit::query()->where('branch_id', $branchId)->whereNull('closed_at')->exists()) {
            throw ValidationException::withMessages(['branch_id' => __('assets.errors.audit_open')]);
        }

        return DB::transaction(function () use ($branch, $note, $actor): Audit {
            $audit = (new Audit)->forceFill(['client_id' => $this->tenant->client()->id, 'company_id' => $branch->companyId, 'branch_id' => $branch->id, 'started_on' => today()->toDateString(), 'note' => $note, 'started_by' => $actor?->getAuthIdentifier()]);
            $audit->save();

            $expected = Asset::query()->where('branch_id', $branch->id)->whereNotIn('status', [AssetStatus::Disposed->value, AssetStatus::Lost->value])->pluck('id');
            AuditItem::query()->insert($expected->map(fn (int $id): array => ['audit_id' => $audit->id, 'asset_id' => $id, 'status' => AuditItemStatus::Expected->value])->all());

            return $audit;
        });
    }

    /**
     * Tick off a scanned tag. An asset not expected here is noted as "unexpected" (it belongs to
     * another branch, or was never moved in the system).
     */
    public function scan(Audit $audit, string $tag): AuditItem
    {
        if ($audit->closed_at !== null) {
            throw ValidationException::withMessages(['tag' => __('assets.errors.audit_closed')]);
        }

        $asset = Asset::query()->where('company_id', $audit->company_id)->where('tag', strtoupper(trim($tag)))->first()
            ?? throw ValidationException::withMessages(['tag' => __('assets.errors.unknown_tag')]);

        $item = $audit->items()->where('asset_id', $asset->id)->first();
        $item ??= $audit->items()->make()->forceFill(['asset_id' => $asset->id, 'status' => AuditItemStatus::Unexpected->value]);
        $item->forceFill(['status' => $item->status === AuditItemStatus::Unexpected ? AuditItemStatus::Unexpected->value : AuditItemStatus::Found->value, 'scanned_at' => now()])->save();

        return $item->setRelation('asset', $asset);
    }

    /** Close: whatever was not scanned is missing, and may be written off as lost. */
    public function close(Audit $audit, bool $markMissingLost = false, ?Authenticatable $actor = null): Audit
    {
        if ($audit->closed_at !== null) {
            throw ValidationException::withMessages(['audit' => __('assets.errors.audit_closed')]);
        }

        return DB::transaction(function () use ($audit, $markMissingLost, $actor): Audit {
            $audit->items()->where('status', AuditItemStatus::Expected->value)->update(['status' => AuditItemStatus::Missing->value]);

            if ($markMissingLost) {
                $audit->items()->where('status', AuditItemStatus::Missing->value)->with('asset')->get()
                    ->each(fn (AuditItem $item) => rescue(fn () => $this->dispose->handle($item->asset, AssetStatus::Lost, today()->toDateString(), reason: __('assets.audits.lost_reason', ['date' => $audit->started_on->toDateString()]), actor: $actor), report: false));
            }

            $audit->forceFill(['closed_at' => now(), 'closed_by' => $actor?->getAuthIdentifier()])->save();

            return $audit;
        });
    }
}

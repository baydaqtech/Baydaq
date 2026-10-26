<?php

namespace App\Modules\Assets\Actions;

use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class DisposeAsset
{
    public function __construct(private Audit $audit) {}

    /** Write an asset off (sold, scrapped, donated) or record it as lost. It must not be held by anyone. */
    public function handle(Asset $asset, AssetStatus $as, string $on, ?string $amount = null, ?string $reason = null, ?Authenticatable $actor = null): Asset
    {
        if (! in_array($as, [AssetStatus::Disposed, AssetStatus::Lost], true) || in_array($asset->status, [AssetStatus::Disposed, AssetStatus::Lost], true)) {
            throw ValidationException::withMessages(['status' => __('assets.errors.not_disposable')]);
        }

        if ($asset->openCustody()->exists() || $asset->maintenance()->whereNull('closed_on')->exists()) {
            throw ValidationException::withMessages(['status' => __('assets.errors.in_use')]);
        }

        $asset->forceFill(['status' => $as->value, 'disposed_on' => $on, 'disposal_amount' => $amount, 'disposal_reason' => $reason])->save();
        $this->audit->record($asset, $as->value, ['amount' => $amount, 'reason' => $reason], $actor);

        return $asset;
    }
}

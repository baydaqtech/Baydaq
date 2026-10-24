<?php

namespace App\Actions\Usage;

use App\Enums\PricingModel;
use App\Models\ProjectService;
use App\Models\ServiceUsage;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordUsage
{
    /**
     * Add usage to one day of a project's service. Per-use services cost quantity × price; a
     * monthly service's cost is its flat price, counted once on the first usage day of the month.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function handle(ProjectService $projectService, string|float|int $quantity, ?string $date = null, string $source = 'api', ?array $meta = null): ServiceUsage
    {
        $day = ($date ? Carbon::parse($date) : today())->toDateString();

        return DB::transaction(function () use ($projectService, $quantity, $day, $source, $meta): ServiceUsage {
            $usage = ServiceUsage::query()->where('project_service_id', $projectService->id)->whereDate('usage_date', $day)->lockForUpdate()->first()
                ?? (new ServiceUsage)->forceFill(['project_service_id' => $projectService->id, 'usage_date' => $day, 'quantity' => '0', 'cost' => '0']);

            $total = BigDecimal::of((string) $usage->quantity)->plus((string) $quantity);
            $price = BigDecimal::of((string) ($projectService->price ?? '0'));

            $cost = match ($projectService->pricing_model) {
                PricingModel::PerUse => $total->multipliedBy($price)->toScale(2, RoundingMode::HalfUp),
                PricingModel::Monthly => $this->monthlyCost($projectService, $day, $usage) ? $price->toScale(2) : BigDecimal::zero(),
            };

            $usage->forceFill(['quantity' => (string) $total, 'cost' => (string) $cost, 'source' => $source, 'meta' => $meta ?? $usage->meta])->save();
            $projectService->forceFill(['last_used_at' => now()])->saveQuietly();

            return $usage;
        });
    }

    /** A flat monthly fee is booked on the earliest usage day of the month, so the month is charged once. */
    private function monthlyCost(ProjectService $projectService, string $day, ServiceUsage $usage): bool
    {
        $monthStart = Carbon::parse($day)->startOfMonth();

        return ! ServiceUsage::query()->where('project_service_id', $projectService->id)
            ->whereBetween('usage_date', [$monthStart, $monthStart->copy()->endOfMonth()])
            ->when($usage->exists, fn ($query) => $query->whereKeyNot($usage->id))
            ->where('cost', '>', 0)->exists();
    }
}

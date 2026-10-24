<?php

namespace App\Actions\Usage;

use App\Models\ProjectService;
use App\Models\ServiceUsage;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

class UsageSummary
{
    /**
     * Month-to-date quantity and cost, a straight-line forecast for the month, and the last six
     * months as a small series for the portal card.
     *
     * @return array{month: string, quantity: string, cost: string, forecast: string, last_used_at: ?string, history: list<array{month: string, quantity: string, cost: string}>}
     */
    public function handle(ProjectService $projectService, ?Carbon $today = null): array
    {
        $today ??= today();
        $monthStart = $today->copy()->startOfMonth();

        $rows = ServiceUsage::query()->where('project_service_id', $projectService->id)
            ->whereDate('usage_date', '>=', $monthStart->copy()->subMonths(5))
            ->get(['usage_date', 'quantity', 'cost']);

        $byMonth = $rows->groupBy(fn (ServiceUsage $row) => $row->usage_date->format('Y-m'))
            ->map(fn ($group) => [
                'quantity' => (string) $group->reduce(fn (BigDecimal $sum, $row) => $sum->plus((string) $row->quantity), BigDecimal::zero()),
                'cost' => (string) $group->reduce(fn (BigDecimal $sum, $row) => $sum->plus((string) $row->cost), BigDecimal::zero())->toScale(2),
            ]);

        $current = $byMonth->get($monthStart->format('Y-m'), ['quantity' => '0', 'cost' => '0.00']);
        $elapsed = max(1, $today->day);
        $forecast = $projectService->pricing_model->value === 'monthly'
            ? BigDecimal::of((string) ($projectService->price ?? '0'))
            : BigDecimal::of($current['cost'])->dividedBy($elapsed, 6, RoundingMode::HalfUp)->multipliedBy($today->daysInMonth);

        $history = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $monthStart->copy()->subMonths($i)->format('Y-m');
            $history[] = ['month' => $month, ...$byMonth->get($month, ['quantity' => '0', 'cost' => '0.00'])];
        }

        return [
            'month' => $monthStart->format('Y-m'),
            'quantity' => $current['quantity'],
            'cost' => $current['cost'],
            'forecast' => (string) $forecast->toScale(2, RoundingMode::HalfUp),
            'last_used_at' => $projectService->last_used_at?->toIso8601String(),
            'history' => $history,
        ];
    }
}

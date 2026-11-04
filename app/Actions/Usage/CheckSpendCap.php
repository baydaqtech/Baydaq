<?php

namespace App\Actions\Usage;

use App\Enums\Permission;
use App\Models\ProjectService;
use App\Models\ServiceUsage;
use App\Models\User;
use App\Notifications\SpendCapReached;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Notification;

/**
 * Warns the client and the team once when a service's spend this month crosses 80% and again at
 * 100% of its monthly cap (B2). The cap is a warning line; usage keeps being recorded.
 */
class CheckSpendCap
{
    /** @var list<int> */
    public const LEVELS = [80, 100];

    /**
     * The level just crossed, or null when no new alert was due.
     */
    public function handle(ProjectService $projectService): ?int
    {
        $cap = BigDecimal::of((string) ($projectService->monthly_cap ?? '0'));

        if ($cap->isLessThanOrEqualTo(0)) {
            return null;
        }

        $month = today()->format('Y-m');
        $spent = BigDecimal::of((string) ServiceUsage::query()->where('project_service_id', $projectService->id)
            ->whereBetween('usage_date', [today()->startOfMonth(), today()->endOfMonth()])->sum('cost'));
        $percent = $spent->multipliedBy(100)->dividedBy($cap, 2, RoundingMode::Down);
        $alerted = $projectService->cap_alert_month === $month ? (int) $projectService->cap_alert_level : 0;
        $level = collect(self::LEVELS)->filter(fn (int $level): bool => $percent->isGreaterThanOrEqualTo($level) && $level > $alerted)->max();

        if ($level === null) {
            return null;
        }

        $projectService->forceFill(['cap_alert_month' => $month, 'cap_alert_level' => $level])->saveQuietly();
        $recipients = User::clientAccountsOf($projectService->project->client_id)->merge(User::teamHolding(Permission::ProjectsManage));
        Notification::send($recipients, new SpendCapReached($projectService, $level, (string) $spent->toScale(2)));

        return $level;
    }
}

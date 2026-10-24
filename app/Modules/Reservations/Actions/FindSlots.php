<?php

namespace App\Modules\Reservations\Actions;

use App\Modules\Hr\Ports\StaffAvailability;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Reservations\Models\Booking;
use App\Modules\Reservations\Models\BranchSettings;
use App\Modules\Reservations\Models\Service;
use Carbon\CarbonImmutable;

class FindSlots
{
    public function __construct(private OrgDirectory $directory, private Entitlements $entitlements, private StaffAvailability $availability) {}

    /**
     * Free start times for a service at a branch on a company-local date. A slot needs a free
     * resource when the service is tied to resources, and a free staff member when it needs staff
     * and the tenant has HR (staff on leave or working elsewhere that day are not offered). Online
     * customers also respect the branch's notice and booking horizon.
     *
     * @return list<array{starts_at: string, time: string, resource_ids: list<int>, employee_ids: list<int>}>
     */
    public function handle(Service $service, int $branchId, string $date, bool $public = false, int $partySize = 1, ?int $ignoreBookingId = null): array
    {
        $branch = $this->directory->branch($branchId);

        if ($branch === null || $branch->companyId !== $service->company_id || ! $service->is_active) {
            return [];
        }

        $settings = BranchSettings::query()->find($branchId);
        $timezone = $this->directory->company($branch->companyId)?->timezone ?? 'Asia/Riyadh';
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $now = CarbonImmutable::now($timezone);
        $step = max(5, $settings->slot_minutes ?? 30);

        if ($day === false || $day->lt($now->startOfDay()) || ($public && $day->gt($now->startOfDay()->addDays($settings->max_days_ahead ?? 30)))) {
            return [];
        }

        $earliest = $public ? $now->addMinutes($settings->min_notice_minutes ?? 60) : $now->subMinutes($step);

        $needsResource = $service->resources()->exists();
        $resourceIds = $needsResource
            ? $service->resources()->where('branch_id', $branchId)->where('is_active', true)->where('capacity', '>=', $partySize)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [];

        $needsStaff = $service->needs_staff && $this->entitlements->isActive(Module::Hr);
        $staffIds = $needsStaff ? array_values(array_intersect($service->staffIds(), $this->availability->availableAt($branchId, $day))) : [];

        if (($needsResource && $resourceIds === []) || ($needsStaff && $staffIds === [])) {
            return [];
        }

        $busy = Booking::query()->holding($day->startOfDay(), $day->endOfDay()->addMinutes($service->buffer_minutes))
            ->where(fn ($query) => $query->whereIn('resource_id', $resourceIds ?: [0])->orWhereIn('employee_id', $staffIds ?: [0]))
            ->when($ignoreBookingId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->get(['resource_id', 'employee_id', 'starts_at', 'blocks_until']);

        $slots = [];

        foreach (($settings->hours ?? BranchSettings::defaultHours())[(string) $day->dayOfWeek] ?? [] as [$opens, $closes]) {
            $closing = $day->setTimeFromTimeString($closes);

            for ($start = $day->setTimeFromTimeString($opens); $start->addMinutes($service->duration_minutes)->lte($closing); $start = $start->addMinutes($step)) {
                if ($start->lt($earliest)) {
                    continue;
                }

                $blocksUntil = $start->addMinutes($service->duration_minutes + $service->buffer_minutes);
                $overlapping = $busy->filter(fn (Booking $booking): bool => $booking->starts_at->lt($blocksUntil) && $booking->blocks_until->gt($start));
                $freeResources = array_values(array_diff($resourceIds, $overlapping->pluck('resource_id')->filter()->all()));
                $freeStaff = array_values(array_diff($staffIds, $overlapping->pluck('employee_id')->filter()->all()));

                if (($needsResource && $freeResources === []) || ($needsStaff && $freeStaff === [])) {
                    continue;
                }

                $slots[] = ['starts_at' => $start->toIso8601String(), 'time' => $start->format('H:i'), 'resource_ids' => $freeResources, 'employee_ids' => $freeStaff];
            }
        }

        return $slots;
    }
}

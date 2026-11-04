<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Subscriptions\Actions\CheckInMember;
use App\Modules\Subscriptions\Enums\SubscriptionsFeature;
use App\Modules\Subscriptions\Models\CheckIn;
use App\Modules\Subscriptions\Models\Zone;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The front desk: scan or type a card code, see at once whether the member may come in.
 */
class CheckInController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function show(Request $request, OrgDirectory $directory): Response
    {
        $branches = $this->branchesFor(SubscriptionsFeature::CheckIns, FeatureAction::View);
        $branch = $branches->first(fn (BranchRef $branch): bool => $branch->id === (int) $request->query('branch')) ?? $branches->first();
        $timezone = $branch ? ($directory->company($branch->companyId)?->timezone ?? 'Asia/Riyadh') : 'Asia/Riyadh';

        return $this->suitePage('Subscriptions/CheckIn', [
            'branches' => $branches->map(fn (BranchRef $item): array => ['value' => $item->id, 'label' => $item->name])->all(),
            'branch_id' => $branch?->id,
            'zones' => $branch ? Zone::query()->where('branch_id', $branch->id)->get()->map(fn (Zone $zone): array => ['value' => $zone->id, 'label' => $zone->localized('name')])->all() : [],
            'can_check_in' => $branch !== null && $this->access()->allowsBranch(SubscriptionsFeature::CheckIns->key('check_in'), $branch->id, $branch->companyId),
            'recent' => $branch ? CheckIn::query()->where('branch_id', $branch->id)->where('checked_in_at', '>=', CarbonImmutable::now($timezone)->startOfDay()->utc())->with('member')->latest('checked_in_at')->limit(25)->get()->map(fn (CheckIn $checkIn): array => [
                'id' => $checkIn->id, 'time' => $checkIn->checked_in_at->setTimezone($timezone)->format('H:i'), 'member_id' => $checkIn->member_id, 'member' => $checkIn->member->name, 'number' => $checkIn->member->number,
                'allowed' => $checkIn->allowed, 'reason' => $checkIn->reason?->value,
            ])->all() : [],
        ], ['subscriptions']);
    }

    public function store(Request $request, CheckInMember $checkIn): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'code' => ['required', 'string', 'max:64'], 'zone_id' => ['nullable', 'integer']]);
        $branch = $this->branchesFor(SubscriptionsFeature::CheckIns, 'check_in')->first(fn (BranchRef $branch): bool => $branch->id === (int) $data['branch_id']) ?? abort(403);

        $outcome = $checkIn->handle($data['code'], $branch->id, 'staff', $request->user(), $data['zone_id'] ?? null);
        Inertia::flash('check_in', [
            'result' => $outcome['result']->value,
            'allowed' => $outcome['check_in']?->allowed ?? false,
            'member' => $outcome['member'] ? ['id' => $outcome['member']->id, 'name' => $outcome['member']->name, 'number' => $outcome['member']->number] : null,
            'membership' => $outcome['membership'] ? ['ends_on' => $outcome['membership']->ends_on->toDateString(), 'sessions_left' => $outcome['membership']->sessionsLeft()] : null,
        ]);

        return back();
    }
}

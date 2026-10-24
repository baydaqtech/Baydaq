<?php

namespace App\Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Subscriptions\Actions\ManageMembership;
use App\Modules\Subscriptions\Actions\SaveMember;
use App\Modules\Subscriptions\Actions\SellMembership;
use App\Modules\Subscriptions\Enums\MembershipStatus;
use App\Modules\Subscriptions\Enums\PaymentMeans;
use App\Modules\Subscriptions\Enums\SubscriptionsFeature;
use App\Modules\Subscriptions\Models\CheckIn;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Membership;
use App\Modules\Subscriptions\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

class MemberController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(Request $request): Response
    {
        $filters = ['search' => $request->query('search'), 'status' => $request->query('status')];
        $companies = $this->companiesFor(SubscriptionsFeature::Members, FeatureAction::View);
        $today = CarbonImmutable::now('Asia/Riyadh')->toDateString();

        $members = Member::query()->whereIn('company_id', $companies->pluck('id'))
            ->with(['memberships' => fn ($query) => $query->where('status', MembershipStatus::Active)->whereDate('ends_on', '>=', $today)->orderBy('starts_on')->with('plan')])
            ->when($filters['search'], fn ($query, string $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('number', $search)))
            ->when($filters['status'] === 'active', fn ($query) => $query->whereHas('memberships', fn ($query) => $query->where('status', MembershipStatus::Active)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)))
            ->when($filters['status'] === 'lapsed', fn ($query) => $query->whereDoesntHave('memberships', fn ($query) => $query->where('status', MembershipStatus::Active)->whereDate('ends_on', '>=', $today)))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return $this->suitePage('Subscriptions/Members/Index', [
            'members' => $members->through(fn (Member $member): array => [
                'id' => $member->id, 'number' => $member->number, 'name' => $member->name, 'phone' => $member->phone, 'is_active' => $member->is_active,
                'current' => ($membership = $member->memberships->first()) ? ['plan' => $membership->plan->localized('name'), 'starts_on' => $membership->starts_on->toDateString(), 'ends_on' => $membership->ends_on->toDateString(), 'sessions_left' => $membership->sessionsLeft()] : null,
            ]),
            'filters' => $filters,
            'companies' => $companies->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->all(),
        ], ['subscriptions']);
    }

    public function store(Request $request, SaveMember $save): RedirectResponse
    {
        $data = $this->validated($request, creating: true);
        $this->authorizeCompanyFor(SubscriptionsFeature::Members, FeatureAction::Create, (int) $data['company_id']);

        $member = $save->handle(new Member, collect($data)->except('company_id')->all(), (int) $data['company_id']);
        $this->toast(__('subscriptions.members.saved'));

        return to_route('subscriptions.members.show', $member);
    }

    public function show(Member $member, OrgDirectory $directory): Response
    {
        $this->authorizeCompanyFor(SubscriptionsFeature::Members, FeatureAction::View, $member->company_id);
        $timezone = $directory->company($member->company_id)?->timezone ?? 'Asia/Riyadh';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $branchNames = collect($directory->branches($member->company_id))->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);

        return $this->suitePage('Subscriptions/Members/Show', [
            'member' => [
                ...$member->only(['id', 'number', 'name', 'phone', 'email', 'gender', 'note', 'is_active']), 'birth_date' => $member->birth_date?->toDateString(),
                'card_url' => route('member.card', $member->card_token),
            ],
            'memberships' => $member->memberships()->with(['plan', 'freezes'])->latest('starts_on')->get()->map(fn (Membership $membership): array => [
                'id' => $membership->id, 'plan' => $membership->plan->localized('name'), 'status' => $membership->status->value,
                'starts_on' => $membership->starts_on->toDateString(), 'ends_on' => $membership->ends_on->toDateString(),
                'is_current' => $membership->status === MembershipStatus::Active && $membership->covers($today), 'is_frozen' => $membership->isFrozenOn($today),
                'sessions_total' => $membership->sessions_total, 'sessions_left' => $membership->sessionsLeft(), 'price' => (string) $membership->price,
                'freeze_days_left' => max(0, $membership->plan->freeze_days - $membership->frozen_days), 'invoice_id' => $membership->invoice_id,
                'freezes' => $membership->freezes->map(fn ($freeze): array => ['starts_on' => $freeze->starts_on->toDateString(), 'ends_on' => $freeze->ends_on->toDateString(), 'days' => $freeze->days, 'reason' => $freeze->reason])->all(),
            ])->all(),
            'check_ins' => CheckIn::query()->where('member_id', $member->id)->latest('checked_in_at')->limit(30)->get()->map(fn (CheckIn $checkIn): array => [
                'id' => $checkIn->id, 'at' => $checkIn->checked_in_at->setTimezone($timezone)->format('Y-m-d H:i'), 'branch' => $branchNames[$checkIn->branch_id] ?? null,
                'allowed' => $checkIn->allowed, 'reason' => $checkIn->reason?->value, 'used_session' => $checkIn->used_session,
            ])->all(),
            'plans' => Plan::query()->where('company_id', $member->company_id)->where('is_active', true)->orderBy('sort_order')->get()->map(fn (Plan $plan): array => [
                'value' => $plan->id, 'label' => $plan->localized('name'), 'price' => (string) $plan->price, 'gender' => $plan->gender->value,
            ])->all(),
            'payment_means' => PaymentMeans::options(),
            'today' => $today->toDateString(),
        ], ['subscriptions']);
    }

    public function update(Request $request, Member $member, SaveMember $save): RedirectResponse
    {
        $this->authorizeCompanyFor(SubscriptionsFeature::Members, FeatureAction::Update, $member->company_id);
        $save->handle($member, $this->validated($request, creating: false));
        $this->toast(__('subscriptions.members.saved'));

        return back();
    }

    public function sell(Request $request, Member $member, SellMembership $sell): RedirectResponse
    {
        abort_unless($this->companiesFor(SubscriptionsFeature::Members, 'sell')->contains('id', $member->company_id), 403);
        $data = $request->validate(['plan_id' => ['required', 'integer'], 'starts_on' => ['nullable', 'date'], 'payment_means' => ['required', Rule::enum(PaymentMeans::class)], 'renew_id' => ['nullable', 'integer']]);
        $renewing = isset($data['renew_id']) ? $member->memberships()->findOrFail($data['renew_id']) : null;

        $sell->handle($member, Plan::query()->findOrFail($data['plan_id']), PaymentMeans::from($data['payment_means']), $renewing ? null : ($data['starts_on'] ?? null), actor: $request->user(), renewing: $renewing);
        $this->toast(__('subscriptions.members.sold'));

        return back();
    }

    public function freeze(Request $request, Membership $membership, ManageMembership $manage): RedirectResponse
    {
        abort_unless($this->companiesFor(SubscriptionsFeature::Members, 'freeze')->contains('id', $membership->company_id), 403);
        $data = $request->validate(['starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255']]);

        $manage->freeze($membership, $data['starts_on'], $data['ends_on'], $data['reason'] ?? null, $request->user());
        $this->toast(__('subscriptions.members.frozen'));

        return back();
    }

    public function cancel(Request $request, Membership $membership, ManageMembership $manage): RedirectResponse
    {
        abort_unless($this->companiesFor(SubscriptionsFeature::Members, 'cancel')->contains('id', $membership->company_id), 403);
        $manage->cancel($membership, $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null, $request->user());
        $this->toast(__('subscriptions.members.cancelled'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            ...($creating ? ['company_id' => ['required', 'integer']] : []),
            'name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'], 'email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])], 'birth_date' => ['nullable', 'date', 'before:today'], 'note' => ['nullable', 'string', 'max:255'], 'is_active' => ['boolean'],
        ]);
    }
}

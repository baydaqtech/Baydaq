<?php

namespace App\Modules\Assets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\ManageAudit;
use App\Modules\Assets\Enums\AssetsFeature;
use App\Modules\Assets\Enums\AuditItemStatus;
use App\Modules\Assets\Models\Audit;
use App\Modules\Assets\Models\AuditItem;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock-takes: start one at a branch, scan tags (a barcode scanner types and presses Enter),
 * close it to see what is missing.
 */
class AuditController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(): Response
    {
        $branches = $this->branchesFor(AssetsFeature::Audits, FeatureAction::View);
        $names = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);

        return $this->suitePage('Assets/Audits/Index', [
            'audits' => Audit::query()->whereIn('branch_id', $branches->pluck('id'))->withCount([
                'items', 'items as found_count' => fn ($query) => $query->where('status', AuditItemStatus::Found->value),
                'items as missing_count' => fn ($query) => $query->where('status', AuditItemStatus::Missing->value),
                'items as unexpected_count' => fn ($query) => $query->where('status', AuditItemStatus::Unexpected->value),
            ])->latest('id')->limit(50)->get()->map(fn (Audit $audit): array => [
                'id' => $audit->id, 'branch' => $names[$audit->branch_id] ?? null, 'started_on' => $audit->started_on->toDateString(), 'closed_at' => $audit->closed_at?->toDateString(),
                'items' => $audit->items_count, 'found' => $audit->found_count, 'missing' => $audit->missing_count, 'unexpected' => $audit->unexpected_count,
            ])->all(),
            'branches' => $this->branchesFor(AssetsFeature::Audits, FeatureAction::Create)->map(fn (BranchRef $branch): array => ['value' => $branch->id, 'label' => $branch->name])->all(),
        ], ['assets']);
    }

    public function store(Request $request, ManageAudit $audits): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->authorizeBranchFor(AssetsFeature::Audits, FeatureAction::Create, (int) $data['branch_id']);

        $audit = $audits->start((int) $data['branch_id'], $data['note'] ?? null, $request->user());

        return to_route('assets.audits.show', $audit);
    }

    public function show(Audit $audit): Response
    {
        $this->authorizeBranchFor(AssetsFeature::Audits, FeatureAction::View, $audit->branch_id);
        $branch = $this->branchesFor(AssetsFeature::Audits, FeatureAction::View)->firstWhere('id', $audit->branch_id);

        return $this->suitePage('Assets/Audits/Show', [
            'audit' => ['id' => $audit->id, 'branch' => $branch?->name, 'started_on' => $audit->started_on->toDateString(), 'closed_at' => $audit->closed_at?->toDateString(), 'note' => $audit->note],
            'items' => $audit->items()->with('asset')->get()->sortBy(fn (AuditItem $item) => $item->asset->tag)->values()->map(fn (AuditItem $item): array => [
                'id' => $item->id, 'asset_id' => $item->asset_id, 'tag' => $item->asset->tag, 'name' => $item->asset->localized('name'), 'serial' => $item->asset->serial,
                'status' => $item->status->value, 'scanned_at' => $item->scanned_at?->toIso8601String(),
            ])->all(),
            'can_close' => $this->access()->allowsBranch(AssetsFeature::Audits->key('close'), $audit->branch_id, $audit->company_id),
        ], ['assets']);
    }

    public function scan(Request $request, Audit $audit, ManageAudit $audits): RedirectResponse
    {
        $this->authorizeBranchFor(AssetsFeature::Audits, FeatureAction::Create, $audit->branch_id);
        $item = $audits->scan($audit, $request->validate(['tag' => ['required', 'string', 'max:20']])['tag']);
        Inertia::flash('scanned', ['tag' => $item->asset->tag, 'name' => $item->asset->localized('name'), 'status' => $item->status->value]);

        return back();
    }

    public function close(Request $request, Audit $audit, ManageAudit $audits): RedirectResponse
    {
        abort_unless($this->access()->allowsBranch(AssetsFeature::Audits->key('close'), $audit->branch_id, $audit->company_id), 403);
        $audits->close($audit, $request->boolean('mark_missing_lost'), $request->user());
        $this->toast(__('assets.audits.closed'));

        return back();
    }
}

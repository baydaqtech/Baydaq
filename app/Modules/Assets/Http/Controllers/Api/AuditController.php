<?php

namespace App\Modules\Assets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Assets\Actions\ManageAudit;
use App\Modules\Assets\Enums\AuditItemStatus;
use App\Modules\Assets\Models\Audit;
use App\Modules\Assets\Models\AuditItem;
use App\Modules\Platform\Ports\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stock-takes from the employee app (A2): the open audits of branches the person may count,
 * and scanning QR asset tags into one.
 */
class AuditController extends Controller
{
    public function __construct(private Access $access) {}

    public function index(Request $request): JsonResponse
    {
        $audits = Audit::query()->whereNull('closed_at')->withCount([
            'items as expected_count' => fn ($query) => $query->where('status', '!=', AuditItemStatus::Unexpected->value),
            'items as found_count' => fn ($query) => $query->where('status', AuditItemStatus::Found->value),
        ])->latest('started_on')->get()->filter(fn (Audit $audit): bool => $this->mayCount($request, $audit));

        return response()->json(['audits' => $audits->map(fn (Audit $audit): array => [
            'id' => $audit->id, 'branch_id' => $audit->branch_id, 'started_on' => $audit->started_on->toDateString(), 'note' => $audit->note,
            'expected' => $audit->expected_count, 'found' => $audit->found_count,
        ])->values()->all()]);
    }

    public function scan(Request $request, Audit $audit, ManageAudit $manage): JsonResponse
    {
        abort_unless($this->mayCount($request, $audit), 403);
        $data = $request->validate(['tag' => ['required', 'string', 'max:40']]);

        $item = $manage->scan($audit, $data['tag']);

        return response()->json(['tag' => $item->asset->tag, 'name' => $item->asset->localized('name'), 'status' => $item->status->value]);
    }

    public function show(Request $request, Audit $audit): JsonResponse
    {
        abort_unless($this->mayCount($request, $audit), 403);

        return response()->json(['items' => $audit->items()->with('asset')->get()->map(fn (AuditItem $item): array => [
            'tag' => $item->asset->tag, 'name' => $item->asset->localized('name'), 'status' => $item->status->value,
        ])->all()]);
    }

    private function mayCount(Request $request, Audit $audit): bool
    {
        return $this->access->allowsBranch('assets.audits.create', $audit->branch_id, $audit->company_id, $request->user());
    }
}

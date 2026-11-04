<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\Approvals\CollectApprovals;
use App\Modules\Platform\Data\ApprovalItem;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * One inbox for everything waiting on me: every module contributes its requests through the
 * ApprovalSource port, each limited to what I may decide. Deciding goes to the module's own
 * route, so its rules, locks and audit apply as they do on its own page.
 */
class ApprovalsController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function __invoke(Request $request, CollectApprovals $approvals): Response
    {
        $items = $approvals->handle($this->context(), $request->user()->id);

        return $this->suitePage('Suite/Approvals', [
            'items' => array_map(fn (ApprovalItem $item): array => [
                ...(array) $item,
                'approve' => $item->approve ? (array) $item->approve : null,
                'reject' => $item->reject ? (array) $item->reject : null,
            ], $items),
            'kinds' => array_values(array_unique(array_map(fn (ApprovalItem $item): string => $item->kind, $items))),
        ]);
    }

    private function context(): ScopeContext
    {
        $access = $this->access();
        $scope = $this->scope();

        return new ScopeContext(fn (string $key) => $scope->branchesFor($key, $access), $scope->branchCompanies);
    }
}

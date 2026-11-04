<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Data\SearchHit;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Ports\SuiteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Record search for the ⌘K palette (H1), across every module that registered a search.
 */
class SearchController extends Controller
{
    use AuthorizesSuite;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['hits' => []]);
        }

        $access = $this->access();
        $scope = $this->scope();
        $context = new ScopeContext(fn (string $key) => $scope->branchesFor($key, $access), $scope->branchCompanies);

        $hits = collect(app()->tagged(SuiteSearch::TAG))
            ->flatMap(fn (SuiteSearch $search): array => $search->search($term, $context))
            ->map(fn (SearchHit $hit): array => ['type' => $hit->type, 'label' => $hit->label, 'detail' => $hit->detail, 'path' => $hit->path])
            ->values();

        return response()->json(['hits' => $hits]);
    }
}

<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Data\BranchSignal;
use App\Modules\Platform\Data\ScopeContext;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Ports\BoardSignals;
use Inertia\Response;

/**
 * The owner board (X1): every company in view as a row of the board, every branch a piece,
 * coloured by the worst health reading the modules report for it.
 */
class BoardController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function __invoke(): Response
    {
        $access = $this->access();
        $scope = $this->scope();
        $context = new ScopeContext(fn (string $key) => $scope->branchesFor($key, $access), $scope->branchCompanies);
        $branchIds = array_keys($scope->branchCompanies);

        if ($scope->selectedBranchIds !== []) {
            $branchIds = $scope->selectedBranchIds;
        }

        $signals = [];

        foreach (app()->tagged(BoardSignals::TAG) as $provider) {
            foreach ($provider->signals($branchIds, $context) as $branchId => $readings) {
                $signals[$branchId] = [...($signals[$branchId] ?? []), ...$readings];
            }
        }

        $companies = Company::query()->whereIn('id', array_unique(array_intersect_key($scope->branchCompanies, array_flip($branchIds))))
            ->with(['branches' => fn ($query) => $query->whereIn('id', $branchIds)->orderByDesc('is_head_office')->orderBy('code')])
            ->orderBy('id')->get();

        return $this->suitePage('Suite/Board', [
            'companies' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'slug' => $company->slug,
                'name' => $company->localized('name'),
                'branches' => $company->branches->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $branch->localized('name'),
                    'is_head_office' => $branch->is_head_office,
                    'health' => $this->health($signals[$branch->id] ?? []),
                    'signals' => array_map(fn (BranchSignal $signal): array => (array) $signal, $signals[$branch->id] ?? []),
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * The worst reading decides the colour of the piece; with no readings it stays neutral.
     *
     * @param  list<BranchSignal>  $signals
     */
    private function health(array $signals): string
    {
        $statuses = array_map(fn (BranchSignal $signal): string => $signal->status, $signals);

        return match (true) {
            in_array('bad', $statuses, true) => 'bad',
            in_array('warn', $statuses, true) => 'warn',
            in_array('good', $statuses, true) => 'good',
            default => 'none',
        };
    }
}

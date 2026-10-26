<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\FinanceFeature;
use App\Modules\Finance\Http\Controllers\Concerns\ScopesFinance;
use App\Modules\Finance\Reports\Books;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Trial balance, profit and loss (for a company or one branch) and the VAT return for a period.
 */
class ReportController extends Controller
{
    use RendersSuitePages, ScopesFinance;

    public function __invoke(Request $request, Books $books): Response
    {
        $from = (string) $request->query('from', today()->startOfQuarter()->toDateString());
        $to = (string) $request->query('to', today()->toDateString());
        $companyId = $request->integer('company_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        $reach = $this->reach(FinanceFeature::Reports, FeatureAction::View);

        return $this->suitePage('Finance/Reports', [
            'trial_balance' => $books->trialBalance($reach, null, $to, $companyId, $branchId),
            'profit_and_loss' => $books->profitAndLoss($reach, $from, $to, $companyId, $branchId),
            'vat' => $books->vatReturn($reach, $from, $to, $companyId),
            'filters' => ['from' => $from, 'to' => $to, 'company_id' => $companyId ? (string) $companyId : '', 'branch_id' => $branchId ? (string) $branchId : ''],
            ...$this->places(FinanceFeature::Reports, FeatureAction::View),
        ], ['finance']);
    }
}

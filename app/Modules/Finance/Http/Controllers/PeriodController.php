<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\CloseFiscalYear;
use App\Modules\Finance\Actions\OpeningBalances;
use App\Modules\Finance\Enums\FinanceFeature;
use App\Modules\Finance\Http\Controllers\Concerns\ScopesFinance;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\FiscalClose;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Support\ChartOfAccounts;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * The start and the ends of a company's books: the opening balances when it moved to Baydaq, and
 * closing finished years. Both are company-wide, so they need the ledger across the company.
 */
class PeriodController extends Controller
{
    use RendersSuitePages, ScopesFinance;

    public function index(Request $request, OrgDirectory $directory): Response
    {
        ChartOfAccounts::ensure();
        $companies = $this->companies($directory);
        $companyId = in_array($request->integer('company'), array_column($companies, 'value'), true) ? $request->integer('company') : ($companies[0]['value'] ?? null);
        $opening = $companyId ? OpeningBalances::entryFor($companyId) : null;
        $closed = $companyId ? FiscalClose::query()->where('company_id', $companyId)->orderByDesc('year')->get() : collect();
        $thisYear = $companyId ? (int) substr($directory->today($companyId), 0, 4) : (int) date('Y');
        $years = $companyId ? JournalEntry::query()->where('company_id', $companyId)->selectRaw('distinct year(entry_date) as year')->pluck('year')->map(fn ($year): int => (int) $year) : collect();

        return $this->suitePage('Finance/Periods', [
            'companies' => $companies,
            'company_id' => $companyId,
            'accounts' => Account::query()->where('is_active', true)->whereIn('type', ['asset', 'liability', 'equity'])->where(fn ($query) => $query->whereNull('system_key')->orWhere('system_key', '!=', 'opening_equity'))
                ->orderBy('code')->get()->map(fn (Account $account): array => ['id' => $account->id, 'code' => $account->code, 'name' => $account->localized('name'), 'type' => $account->type])->all(),
            'opening' => $opening ? [
                'date' => $opening->entry_date->toDateString(),
                'balances' => $opening->lines->filter(fn (JournalLine $line): bool => $line->account->system_key !== 'opening_equity')
                    ->mapWithKeys(fn (JournalLine $line): array => [$line->account_id => ['debit' => (string) $line->debit, 'credit' => (string) $line->credit]])->all(),
            ] : null,
            'closes' => $closed->map(fn (FiscalClose $close): array => ['year' => $close->year, 'profit' => (string) $close->profit, 'closed_at' => $close->created_at->toDateString()])->values()->all(),
            // Finished years with entries that are not closed yet.
            'closable' => $years->filter(fn (int $year): bool => $year < $thisYear && ! $closed->contains('year', $year))->sort()->values()->all(),
        ], ['finance']);
    }

    public function saveOpening(Request $request, OrgDirectory $directory, OpeningBalances $opening): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::in(array_column($this->companies($directory), 'value'))],
            'date' => ['required', 'date'],
            'balances' => ['array'], 'balances.*.debit' => ['nullable', 'numeric', 'min:0'], 'balances.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $opening->save((int) $data['company_id'], $data['date'], $data['balances'] ?? [], $request->user());
        $this->toast(__('finance.periods.opening_saved'));

        return back();
    }

    public function close(Request $request, OrgDirectory $directory, CloseFiscalYear $close): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::in(array_column($this->companies($directory), 'value'))],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $closed = $close->handle((int) $data['company_id'], (int) $data['year'], $request->user());
        $this->toast(__('finance.periods.closed', ['year' => $closed->year]));

        return back();
    }

    /**
     * Companies whose whole ledger the user may change.
     *
     * @return list<array{value: int, label: string}>
     */
    private function companies(OrgDirectory $directory): array
    {
        $reach = $this->reach(FinanceFeature::Ledger, FeatureAction::Update);

        return collect($directory->companies())->filter(fn (CompanyRef $company): bool => $reach->allowsWholeCompany($company->id))
            ->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->values()->all();
    }
}

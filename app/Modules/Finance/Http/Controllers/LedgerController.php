<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Enums\FinanceFeature;
use App\Modules\Finance\Http\Controllers\Concerns\ScopesFinance;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Support\ChartOfAccounts;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/**
 * The chart of accounts and the journal: what every module posted, and manual entries.
 */
class LedgerController extends Controller
{
    use RendersSuitePages, ScopesFinance;

    public function index(Request $request, OrgDirectory $directory): Response
    {
        ChartOfAccounts::ensure();
        $source = (string) $request->query('source', '');
        $entries = $this->reach(FinanceFeature::Ledger, FeatureAction::View)->apply(JournalEntry::query())->with('lines.account')
            ->when($source !== '', fn ($query) => $query->where('source', $source))
            ->latest('entry_date')->latest('id')->paginate(30)->withQueryString();

        return $this->suitePage('Finance/Ledger', [
            'accounts' => Account::query()->orderBy('code')->get()->map(fn (Account $account): array => [
                'id' => $account->id, 'code' => $account->code, 'name' => $account->localized('name'), 'name_ar' => $account->name_ar, 'name_en' => $account->name_en,
                'type' => $account->type, 'system_key' => $account->system_key, 'is_active' => $account->is_active,
            ])->all(),
            'entries' => $entries->through(fn (JournalEntry $entry): array => [
                'id' => $entry->id, 'date' => $entry->entry_date->toDateString(), 'source' => $entry->source, 'memo' => $entry->memo, 'is_reversal' => $entry->is_reversal,
                'company' => $directory->company($entry->company_id)?->name, 'branch' => $entry->branch_id ? $directory->branch($entry->branch_id)?->name : null,
                'lines' => $entry->lines->map(fn (JournalLine $line): array => [
                    'account' => $line->account->code.' '.$line->account->localized('name'), 'debit' => (string) $line->debit, 'credit' => (string) $line->credit,
                ])->all(),
            ]),
            'filters' => ['source' => $source],
            'types' => Account::TYPES,
            'can_edit' => $this->access()->allows(FinanceFeature::Ledger->key(FeatureAction::Update)),
            'can_post' => $this->access()->allows(FinanceFeature::Ledger->key(FeatureAction::Create)),
            ...$this->places(FinanceFeature::Ledger, FeatureAction::Create),
        ], ['finance']);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizeAccount(FinanceFeature::Ledger->key(FeatureAction::Update));
        (new Account($this->validateAccount($request)))->save();
        $this->toast(__('finance.saved'));

        return back();
    }

    public function updateAccount(Request $request, Account $account): RedirectResponse
    {
        $this->authorizeAccount(FinanceFeature::Ledger->key(FeatureAction::Update));
        $data = $this->validateAccount($request, $account);

        // A system account keeps its type: modules rely on it.
        if ($account->system_key !== null) {
            unset($data['type']);
        }

        $account->update($data);
        $this->toast(__('finance.saved'));

        return back();
    }

    /** A manual journal entry; it must balance. */
    public function storeEntry(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'entry_date' => ['required', 'date'],
            'memo' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:30'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists(Account::class, 'id')->where('client_id', $request->user()->client_id)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $branch = app(OrgDirectory::class)->branch((int) $data['branch_id']) ?? abort(422);
        $this->authorizeBranch(FinanceFeature::Ledger, FeatureAction::Create, $branch->id, $branch->companyId);
        $debits = collect($data['lines'])->reduce(fn (BigDecimal $sum, array $line) => $sum->plus((string) ($line['debit'] ?? 0)), BigDecimal::zero());
        $credits = collect($data['lines'])->reduce(fn (BigDecimal $sum, array $line) => $sum->plus((string) ($line['credit'] ?? 0)), BigDecimal::zero());

        if (! $debits->isEqualTo($credits) || $debits->isZero()) {
            throw ValidationException::withMessages(['lines' => __('finance.errors.unbalanced', ['debit' => (string) $debits, 'credit' => (string) $credits])]);
        }

        DB::transaction(function () use ($data, $branch, $request): void {
            $entry = (new JournalEntry)->forceFill([
                'company_id' => $branch->companyId, 'branch_id' => $branch->id, 'entry_date' => $data['entry_date'], 'source' => 'manual', 'memo' => $data['memo'], 'created_by' => $request->user()->id,
            ]);
            $entry->save();

            foreach ($data['lines'] as $line) {
                $entry->lines()->make()->forceFill(['account_id' => $line['account_id'], 'debit' => (string) ($line['debit'] ?? 0), 'credit' => (string) ($line['credit'] ?? 0)])->save();
            }
        });
        $this->toast(__('finance.saved'));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccount(Request $request, ?Account $account = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique(Account::class)->where('client_id', $request->user()->client_id)->ignore($account?->id)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Account::TYPES)],
            'is_active' => ['boolean'],
        ]);
    }
}

<?php

namespace Tests\Feature\Finance;

use App\Modules\Finance\Actions\CloseFiscalYear;
use App\Modules\Finance\Actions\OpeningBalances;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Reports\Books;
use App\Modules\Finance\Support\ChartOfAccounts;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\AssertsLedger;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Where a company's books start (opening balances) and how its finished years end (closing).
 */
class PeriodsTest extends TestCase
{
    use AssertsLedger, BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-03-01 09:00:00');
        $this->asTenant();
        ChartOfAccounts::ensure();
    }

    private function account(string $key): Account
    {
        return Account::query()->where('system_key', $key)->sole();
    }

    public function test_opening_balances_balance_through_opening_equity_and_can_be_replaced(): void
    {
        $this->actingAs($this->owner)->put($this->suiteUrl('finance/opening'), [
            'company_id' => $this->company->id, 'date' => '2025-12-31',
            'balances' => [$this->account('cash')->id => ['debit' => '10000'], $this->account('payables')->id => ['credit' => '4000']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('-6000.00', $this->ledgerBalance($this->company->id, 'opening_equity'));
        $this->actingAs($this->owner)->get($this->suiteUrl('finance/periods'))->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Periods')->where('opening.date', '2025-12-31')->where("opening.balances.{$this->account('cash')->id}.debit", '10000.00'));

        app(OpeningBalances::class)->save($this->company->id, '2025-12-31', [$this->account('cash')->id => ['debit' => '7000']]);
        $this->assertSame(1, JournalEntry::query()->where('source', OpeningBalances::SOURCE)->count());
        $this->assertSame('7000.00', $this->ledgerBalance($this->company->id, 'cash'));

        $this->expectException(ValidationException::class);
        app(OpeningBalances::class)->save($this->company->id, '2025-12-31', [$this->account('sales')->id => ['credit' => '500']]);
    }

    public function test_closing_a_year_moves_its_profit_to_retained_earnings_and_locks_it(): void
    {
        $ledger = app(Ledger::class);
        $ledger->post(new LedgerPosting($this->company->id, null, '2025-06-01', 'test.sale', 1, 'Sale', [['account' => 'cash', 'debit' => '1000'], ['account' => 'sales', 'credit' => '1000']]));
        $ledger->post(new LedgerPosting($this->company->id, null, '2025-07-01', 'test.rent', 1, 'Rent', [['account' => 'rent', 'debit' => '300'], ['account' => 'cash', 'credit' => '300']]));

        try {
            app(CloseFiscalYear::class)->handle($this->company->id, 2026, $this->owner);
            $this->fail('An unfinished year was closed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('year', $exception->errors());
        }

        $this->actingAs($this->owner)->post($this->suiteUrl('finance/closes'), ['company_id' => $this->company->id, 'year' => 2025])->assertSessionHasNoErrors();

        $this->assertSame('-700.00', $this->ledgerBalance($this->company->id, 'retained_earnings'));
        $this->assertSame('0.00', $this->ledgerBalance($this->company->id, 'sales'));
        $this->assertSame('700.00', app(Books::class)->profitAndLoss(BranchSet::all(), '2025-01-01', '2025-12-31', $this->company->id)['profit']);

        $this->actingAs($this->owner)->post($this->suiteUrl('finance/journal'), [
            'branch_id' => $this->headOffice->id, 'entry_date' => '2025-12-30', 'memo' => 'Late',
            'lines' => [['account_id' => $this->account('cash')->id, 'debit' => '5'], ['account_id' => $this->account('other_income')->id, 'credit' => '5']],
        ])->assertSessionHasErrors('entry_date');
        $this->actingAs($this->owner)->post($this->suiteUrl('finance/closes'), ['company_id' => $this->company->id, 'year' => 2025])->assertSessionHasErrors('year');
    }
}

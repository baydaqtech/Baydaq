<?php

namespace App\Modules\Invoicing\Actions;

use App\Modules\Invoicing\Enums\InvoiceKind;
use App\Modules\Invoicing\Enums\InvoiceStatus;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Invoicing\Enums\UnitStatus;
use App\Modules\Invoicing\Events\InvoiceIssued;
use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Zatca\Certificate;
use App\Modules\Invoicing\Zatca\Signer;
use App\Modules\Invoicing\Zatca\UblBuilder;
use App\Modules\Invoicing\Zatca\ZatcaClient;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Platform\Ports\OrgDirectory;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueInvoice
{
    public function __construct(
        private OrgDirectory $directory,
        private UblBuilder $ubl,
        private Signer $signer,
        private ZatcaClient $zatca,
        private Audit $audit,
        private Ledger $ledger,
    ) {}

    /** Modules whose simplified receipts are paid at the counter when issued. */
    private const COUNTER_SOURCES = ['reservations.booking', 'subscriptions.membership'];

    /** Longer than ZATCA's 30-second timeout, so the lock outlives the call it guards. */
    private const LOCK_SECONDS = 120;

    private const WAIT_SECONDS = 40;

    /** UN/ECE 4461 payment means → where the money lands. */
    private const MEANS_ACCOUNTS = ['10' => 'cash', '42' => 'bank', '48' => 'card_clearing', '1' => 'card_clearing', '30' => 'receivables'];

    /**
     * The invoice for the books: receivable against sales and output VAT; a credit note the other way.
     */
    public static function posting(Invoice $invoice): LedgerPosting
    {
        $vat = BigDecimal::of((string) $invoice->vat);
        $revenue = BigDecimal::of((string) $invoice->total)->minus($vat);
        $rounding = BigDecimal::of((string) ($invoice->rounding ?? 0));
        $sign = $invoice->kind === InvoiceKind::CreditNote ? -1 : 1;
        $side = fn (BigDecimal $amount, string $normal): array => ($amount->multipliedBy($sign)->isNegative() xor $normal === 'credit')
            ? ['credit' => (string) $amount->abs()->toScale(2)] : ['debit' => (string) $amount->abs()->toScale(2)];

        // A receipt raised by another module at its counter (a booking, a membership) was paid there
        // and then; anything else is owed until paid.
        $paidAtCounter = $invoice->type === InvoiceType::Simplified && in_array($invoice->source, self::COUNTER_SOURCES, true);
        $received = $paidAtCounter ? (self::MEANS_ACCOUNTS[$invoice->payment_means] ?? 'receivables') : 'receivables';

        return new LedgerPosting($invoice->company_id, null, $invoice->issue_date->toDateString(), 'invoicing.invoice', $invoice->id, "{$invoice->number}", [
            ['account' => $received, ...$side(BigDecimal::of($invoice->payableAmount()), 'debit')],
            ['account' => 'sales', ...$side($revenue, 'credit')],
            ['account' => 'vat_output', ...$side($vat, 'credit'), 'tax_base' => (string) $revenue->multipliedBy($sign)->toScale(2)],
            ['account' => 'other_income', ...$side($rounding, 'credit')],
        ]);
    }

    /**
     * An invoice between two companies of the group is also the buyer's purchase: the cost (a
     * secondment recharge is wages) and the input VAT against what it now owes the seller.
     */
    public static function purchasePosting(Invoice $invoice): ?LedgerPosting
    {
        $invoice->loadMissing('customer');
        $buyerId = $invoice->intercompany ? $invoice->customer?->company_id : null;

        if ($buyerId === null || $buyerId === $invoice->company_id) {
            return null;
        }

        $vat = BigDecimal::of((string) $invoice->vat);
        $net = BigDecimal::of((string) $invoice->total)->minus($vat);
        $credit = $invoice->kind === InvoiceKind::CreditNote;
        $line = fn (string $account, BigDecimal $amount, bool $debit): array => ['account' => $account, (($debit xor $credit) ? 'debit' : 'credit') => (string) $amount->toScale(2)];

        return new LedgerPosting($buyerId, null, $invoice->issue_date->toDateString(), 'invoicing.purchase', $invoice->id, "{$invoice->number}", [
            $line(str_starts_with((string) $invoice->source, 'hr.') ? 'salaries' : 'other_expense', $net, true),
            [...$line('vat_input', $vat, true), 'tax_base' => (string) $net->multipliedBy($credit ? -1 : 1)->toScale(2)],
            $line('payables', BigDecimal::of((string) $invoice->total), false),
        ]);
    }

    /**
     * Issue a draft through a unit, in three steps so ZATCA's answer is never lost:
     *
     * 1. under the unit lock, take the next number and counter, sign the XML with the previous
     *    hash and commit it as an "issued" invoice with a submission in flight;
     * 2. send it for clearance (standard) or reporting (simplified) with no transaction open;
     * 3. record the answer and, only when ZATCA accepted, advance the unit's chain.
     *
     * The books, the audit and the event follow once the answer is stored, so a failure there
     * no longer rolls back an invoice ZATCA already holds. A cache lock on the unit keeps the
     * three steps of one invoice from interleaving with another on the same chain. A rejected
     * invoice keeps its number, leaves the chain untouched and can be fixed and issued again.
     */
    public function handle(Invoice $invoice, EinvoiceUnit $unit, ?Authenticatable $actor = null): Invoice
    {
        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Rejected, InvoiceStatus::Issued], true)) {
            throw ValidationException::withMessages(['status' => __('invoicing.errors.not_draft')]);
        }

        if ($unit->company_id !== $invoice->company_id || ! in_array($unit->status, [UnitStatus::Compliance, UnitStatus::Production], true) || $unit->credentials() === null) {
            throw ValidationException::withMessages(['unit_id' => __('invoicing.errors.unit_not_ready')]);
        }

        $company = $this->directory->company($invoice->company_id) ?? throw ValidationException::withMessages(['company_id' => __('invoicing.errors.unit_not_ready')]);

        if (! $company->vatNumber) {
            throw ValidationException::withMessages(['company_id' => __('invoicing.errors.company_vat')]);
        }

        try {
            return Cache::lock("invoicing:unit:{$unit->id}", self::LOCK_SECONDS)->block(self::WAIT_SECONDS, fn (): Invoice => $this->issue($invoice, $unit, $company, $actor));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['unit_id' => __('invoicing.errors.unit_busy')]);
        }
    }

    private function issue(Invoice $invoice, EinvoiceUnit $unit, CompanyRef $company, ?Authenticatable $actor): Invoice
    {
        $simplified = $invoice->type === InvoiceType::Simplified;

        [$unit, $signed, $submission] = DB::transaction(function () use ($invoice, $unit, $company): array {
            $unit = EinvoiceUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $status = Invoice::query()->lockForUpdate()->whereKey($invoice->id)->value('status');

            // "Issued" with this unit means an earlier attempt stopped between sending and
            // recording; holding the unit lock, nobody else is sending it, so it may go again.
            $retry = $status === InvoiceStatus::Issued && $invoice->unit_id === $unit->id;

            if (! $retry && ! in_array($status, [InvoiceStatus::Draft, InvoiceStatus::Rejected], true)) {
                throw ValidationException::withMessages(['status' => __('invoicing.errors.not_draft')]);
            }

            $invoice->refresh();
            $counter = $unit->counter + 1;

            $invoice->forceFill([
                'number' => $invoice->number ?? $this->nextNumber($invoice),
                'issue_time' => $invoice->issue_time ?? now()->format('H:i:s'),
                'unit_id' => $unit->id,
                'counter' => $counter,
                'status' => InvoiceStatus::Issued->value,
            ])->save();

            $certificate = new Certificate($unit->credentials()['certificate']);
            $unsigned = $this->ubl->build($invoice->refresh(), UblBuilder::seller($company), $counter, $unit->previous_hash);
            $signed = $this->signer->sign(
                $unsigned, $unit->private_key, $certificate,
                $company->nameAr, $company->vatNumber,
                $invoice->issue_date->toDateString().'T'.$invoice->issue_time, (string) $invoice->total, (string) $invoice->vat,
                $invoice->type === InvoiceType::Simplified,
            );

            $invoice->submissions()->where('status', 'sending')->update(['status' => 'error']);
            $submission = $invoice->submissions()->make()->forceFill([
                'unit_id' => $unit->id,
                'mode' => $invoice->type === InvoiceType::Simplified ? 'reporting' : 'clearance',
                'environment' => $unit->environment->value,
                'status' => 'sending',
                'counter' => $counter,
                'hash' => $signed['hash'],
                'xml' => $signed['xml'],
                'submitted_at' => now(),
            ]);
            $submission->save();

            return [$unit, $signed, $submission];
        });

        $result = $this->send($unit, $invoice, $signed, $simplified);
        $accepted = in_array($result['status'], [200, 202], true);

        DB::transaction(function () use ($invoice, $unit, $signed, $submission, $result, $accepted, $simplified, $actor): void {
            $unit = EinvoiceUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $status = $result['body']['reportingStatus'] ?? $result['body']['clearanceStatus'] ?? ($accepted ? 'ACCEPTED' : 'ERROR');

            $submission->forceFill([
                'status' => $accepted ? ($result['status'] === 202 ? 'accepted_with_warnings' : 'accepted') : ($result['status'] >= 500 ? 'error' : 'rejected'),
                'xml' => $accepted && ! $simplified && isset($result['body']['clearedInvoice']) ? (base64_decode($result['body']['clearedInvoice'], true) ?: $signed['xml']) : $signed['xml'],
                'response' => ['http' => $result['status'], 'status' => $status, 'body' => $result['body']],
            ])->save();

            if ($accepted) {
                $unit->forceFill(['counter' => $invoice->counter, 'previous_hash' => $signed['hash']])->save();
                $invoice->forceFill([
                    'status' => ($simplified ? InvoiceStatus::Reported : InvoiceStatus::Cleared)->value,
                    'hash' => $signed['hash'],
                    'qr' => $signed['qr'],
                    'issued_by' => $actor?->getAuthIdentifier(),
                    'issued_at' => now(),
                ])->save();
                $this->audit->record($invoice, 'issued', ['number' => $invoice->number, 'counter' => $invoice->counter, 'mode' => $simplified ? 'reporting' : 'clearance'], $actor);
            } else {
                $invoice->forceFill(['status' => InvoiceStatus::Rejected->value, 'counter' => null])->save();
                $this->audit->record($invoice, 'rejected', ['http' => $result['status'], 'errors' => $result['body']['validationResults']['errorMessages'] ?? $result['body']], $actor);
            }
        });

        if ($accepted) {
            InvoiceIssued::dispatch($invoice->id, $invoice->company_id, $invoice->number, (string) $invoice->total);

            // A POS receipt is already in the books as the sale itself. The ledger takes each
            // source once, so a post that failed here can simply be made again.
            if (! str_starts_with((string) $invoice->source, 'pos.')) {
                $this->ledger->post(self::posting($invoice));
            }

            if (($purchase = self::purchasePosting($invoice)) !== null) {
                $this->ledger->post($purchase);
            }
        }

        return $invoice->refresh()->load(['lines', 'customer', 'submissions']);
    }

    /**
     * Clearance or reporting. A network failure counts as a server error: the invoice is marked
     * rejected with an "error" submission and can be issued again.
     *
     * @param  array{xml: string, hash: string, qr: string}  $signed
     * @return array{status: int, body: array<string, mixed>}
     */
    private function send(EinvoiceUnit $unit, Invoice $invoice, array $signed, bool $simplified): array
    {
        $credentials = $unit->credentials();

        try {
            return $simplified
                ? $this->zatca->report($unit->environment, $credentials['certificate'], $credentials['secret'], $invoice->uuid, $signed['hash'], $signed['xml'])
                : $this->zatca->clear($unit->environment, $credentials['certificate'], $credentials['secret'], $invoice->uuid, $signed['hash'], $signed['xml']);
        } catch (ConnectionException $exception) {
            report($exception);

            return ['status' => 503, 'body' => ['error' => $exception->getMessage()]];
        }
    }

    /** Sequential per company and year: INV-2026-00001 (notes share the sequence, prefixed CN-/DN-). */
    private function nextNumber(Invoice $invoice): string
    {
        $prefix = match ($invoice->kind->value) {
            'credit_note' => 'CN',
            'debit_note' => 'DN',
            default => 'INV',
        }.'-'.$invoice->issue_date->year.'-';

        $last = Invoice::query()->where('company_id', $invoice->company_id)->where('number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('number')->value('number');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}

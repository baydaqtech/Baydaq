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
use App\Modules\Platform\Data\LedgerPosting;
use App\Modules\Platform\Ports\Audit;
use App\Modules\Platform\Ports\Ledger;
use App\Modules\Platform\Ports\OrgDirectory;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
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
     * Issue a draft through a unit: take the next number and counter under a row lock, build the
     * XML with the previous hash, sign it, send it for clearance (standard) or reporting
     * (simplified), store the submission, and advance the unit's chain only when ZATCA accepted
     * it. A rejected invoice keeps its number and can be fixed and re-issued? No: ZATCA's chain is
     * per accepted document, so a rejection leaves the chain untouched and the invoice marked rejected.
     */
    public function handle(Invoice $invoice, EinvoiceUnit $unit, ?Authenticatable $actor = null): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft && $invoice->status !== InvoiceStatus::Rejected) {
            throw ValidationException::withMessages(['status' => __('invoicing.errors.not_draft')]);
        }

        if ($unit->company_id !== $invoice->company_id || ! in_array($unit->status, [UnitStatus::Compliance, UnitStatus::Production], true) || $unit->credentials() === null) {
            throw ValidationException::withMessages(['unit_id' => __('invoicing.errors.unit_not_ready')]);
        }

        $company = $this->directory->company($invoice->company_id) ?? throw ValidationException::withMessages(['company_id' => __('invoicing.errors.unit_not_ready')]);

        if (! $company->vatNumber) {
            throw ValidationException::withMessages(['company_id' => __('invoicing.errors.company_vat')]);
        }

        return DB::transaction(function () use ($invoice, $unit, $company, $actor): Invoice {
            $unit = EinvoiceUnit::query()->lockForUpdate()->findOrFail($unit->id);

            // A second request for the same draft waits on the unit lock, then must see it already issued.
            if (! in_array(Invoice::query()->lockForUpdate()->whereKey($invoice->id)->value('status'), [InvoiceStatus::Draft, InvoiceStatus::Rejected], true)) {
                throw ValidationException::withMessages(['status' => __('invoicing.errors.not_draft')]);
            }
            $counter = $unit->counter + 1;

            $invoice->forceFill([
                'number' => $invoice->number ?? $this->nextNumber($invoice),
                'issue_time' => $invoice->issue_time ?? now()->format('H:i:s'),
                'unit_id' => $unit->id,
                'counter' => $counter,
            ])->save();

            $credentials = $unit->credentials();
            $certificate = new Certificate($credentials['certificate']);
            $unsigned = $this->ubl->build($invoice->refresh(), UblBuilder::seller($company), $counter, $unit->previous_hash);
            $signed = $this->signer->sign(
                $unsigned, $unit->private_key, $certificate,
                $company->nameAr, $company->vatNumber,
                $invoice->issue_date->toDateString().'T'.$invoice->issue_time, (string) $invoice->total, (string) $invoice->vat,
                $invoice->type === InvoiceType::Simplified,
            );

            $simplified = $invoice->type === InvoiceType::Simplified;
            $result = $simplified
                ? $this->zatca->report($unit->environment, $credentials['certificate'], $credentials['secret'], $invoice->uuid, $signed['hash'], $signed['xml'])
                : $this->zatca->clear($unit->environment, $credentials['certificate'], $credentials['secret'], $invoice->uuid, $signed['hash'], $signed['xml']);

            $accepted = in_array($result['status'], [200, 202], true);
            $status = $result['body']['reportingStatus'] ?? $result['body']['clearanceStatus'] ?? ($accepted ? 'ACCEPTED' : 'ERROR');
            $xml = $accepted && ! $simplified && isset($result['body']['clearedInvoice']) ? (base64_decode($result['body']['clearedInvoice'], true) ?: $signed['xml']) : $signed['xml'];

            $invoice->submissions()->make()->forceFill([
                'unit_id' => $unit->id,
                'mode' => $simplified ? 'reporting' : 'clearance',
                'environment' => $unit->environment->value,
                'status' => $accepted ? ($result['status'] === 202 ? 'accepted_with_warnings' : 'accepted') : ($result['status'] >= 500 ? 'error' : 'rejected'),
                'counter' => $counter,
                'hash' => $signed['hash'],
                'xml' => $xml,
                'response' => ['http' => $result['status'], 'status' => $status, 'body' => $result['body']],
                'submitted_at' => now(),
            ])->save();

            if ($accepted) {
                $unit->forceFill(['counter' => $counter, 'previous_hash' => $signed['hash']])->save();
                $invoice->forceFill([
                    'status' => ($simplified ? InvoiceStatus::Reported : InvoiceStatus::Cleared)->value,
                    'hash' => $signed['hash'],
                    'qr' => $signed['qr'],
                    'issued_by' => $actor?->getAuthIdentifier(),
                    'issued_at' => now(),
                ])->save();
                $this->audit->record($invoice, 'issued', ['number' => $invoice->number, 'counter' => $counter, 'mode' => $simplified ? 'reporting' : 'clearance'], $actor);
                InvoiceIssued::dispatch($invoice->id, $invoice->company_id, $invoice->number, (string) $invoice->total);

                // A POS receipt is already in the books as the sale itself.
                if (! str_starts_with((string) $invoice->source, 'pos.')) {
                    $this->ledger->post(self::posting($invoice));
                }
            } else {
                $invoice->forceFill(['status' => InvoiceStatus::Rejected->value, 'counter' => null])->save();
                $this->audit->record($invoice, 'rejected', ['http' => $result['status'], 'errors' => $result['body']['validationResults']['errorMessages'] ?? $result['body']], $actor);
            }

            return $invoice->refresh()->load(['lines', 'customer', 'submissions']);
        });
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

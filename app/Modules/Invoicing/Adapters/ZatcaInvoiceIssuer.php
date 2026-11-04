<?php

namespace App\Modules\Invoicing\Adapters;

use App\Modules\Invoicing\Actions\IssueInvoice;
use App\Modules\Invoicing\Actions\SaveInvoice;
use App\Modules\Invoicing\Data\IssuedInvoice;
use App\Modules\Invoicing\Enums\InvoiceStatus;
use App\Modules\Invoicing\Enums\UnitStatus;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Throwable;

class ZatcaInvoiceIssuer implements InvoiceIssuer
{
    public function __construct(private SaveInvoice $save, private IssueInvoice $issue, private OrgDirectory $directory, private CurrentTenant $tenant) {}

    public function issueSimplified(int $companyId, int $unitId, array $lines, string $paymentMeans, ?string $note = null, ?string $source = null): IssuedInvoice
    {
        $netLines = self::netLines($lines);

        $invoice = $this->save->handle(new Invoice, $companyId, ['type' => 'simplified', 'kind' => 'invoice', 'issue_date' => today()->toDateString(), 'payment_means' => $paymentMeans, 'note' => $note], $netLines);

        // VAT rounded per line can leave the total a halala or two off the VAT-inclusive amount the
        // customer paid; record the gap as payable rounding (BT-114) so PayableAmount matches it.
        $paid = array_reduce($lines, fn (BigDecimal $sum, array $line) => $sum->plus(
            BigDecimal::of((string) $line['unit_price_incl_vat'])->multipliedBy((string) $line['quantity'])->toScale(2, RoundingMode::HalfUp)->minus((string) ($line['discount_incl_vat'] ?? 0))
        ), BigDecimal::zero());
        $rounding = $paid->minus((string) $invoice->total);

        if (! $rounding->isZero() && $rounding->abs()->isLessThanOrEqualTo('1')) {
            $invoice->forceFill(['rounding' => (string) $rounding->toScale(2)])->save();
        }

        if ($source !== null) {
            $invoice->forceFill(['source' => $source])->save();
        }

        $invoice = $this->issueOnceCommitted($invoice, $unitId);

        return new IssuedInvoice($invoice->id, $invoice->number, $invoice->status->value, $invoice->qr, $invoice->payableAmount(), (string) $invoice->vat);
    }

    public function creditNote(int $invoiceId, array $lines, string $paymentMeans, string $reason, string $source): ?int
    {
        $original = Invoice::query()->find($invoiceId);

        if ($original === null || ! in_array($original->status, [InvoiceStatus::Reported, InvoiceStatus::Cleared], true) || $original->unit_id === null) {
            return null;
        }

        $note = $this->save->handle(new Invoice, $original->company_id, [
            'type' => $original->type->value, 'kind' => 'credit_note', 'related_invoice_id' => $original->id, 'customer_id' => $original->customer_id,
            'issue_date' => today()->toDateString(), 'payment_means' => $paymentMeans, 'reason' => $reason,
        ], self::netLines($lines));
        $note->forceFill(['source' => $source])->save();

        return $this->issueOnceCommitted($note, $original->unit_id)->id;
    }

    /**
     * VAT-inclusive prices as the customer saw them → net unit prices (4 decimals, as ZATCA
     * allows) and net discounts.
     *
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price_incl_vat: numeric-string|float|int, vat_rate?: numeric-string|float|int, discount_incl_vat?: numeric-string|float|int}>  $lines
     * @return list<array{description: string, quantity: string, unit_price: string, discount: string, vat_rate: string}>
     */
    private static function netLines(array $lines): array
    {
        return array_map(function (array $line): array {
            $rate = BigDecimal::of((string) ($line['vat_rate'] ?? '15'));
            $gross = BigDecimal::of((string) $line['unit_price_incl_vat']);
            $divisor = BigDecimal::one()->plus($rate->dividedBy(100, 6, RoundingMode::HalfUp));
            $net = $gross->dividedBy($divisor, 4, RoundingMode::HalfUp);
            $discount = BigDecimal::of((string) ($line['discount_incl_vat'] ?? 0))->dividedBy($divisor, 2, RoundingMode::HalfUp);

            return ['description' => $line['description'], 'quantity' => (string) $line['quantity'], 'unit_price' => (string) $net, 'discount' => (string) $discount, 'vat_rate' => (string) $rate];
        }, $lines);
    }

    public function draftIntercompany(int $sellerCompanyId, int $buyerCompanyId, array $lines, string $source, ?string $note = null): int
    {
        $buyer = $this->directory->company($buyerCompanyId) ?? throw new \InvalidArgumentException("Unknown company {$buyerCompanyId}.");
        $customer = Customer::query()->where('company_id', $buyer->id)->first() ?? tap((new Customer([
            'name_ar' => $buyer->nameAr, 'name_en' => $buyer->nameEn, 'vat_number' => $buyer->vatNumber, 'cr_number' => $buyer->crNumber, ...$buyer->address,
        ]))->forceFill(['client_id' => $this->tenant->client()->id, 'company_id' => $buyer->id]))->save();
        $standard = $customer->isVatRegistered() && $customer->city;

        $invoice = $this->save->handle(new Invoice, $sellerCompanyId, [
            'type' => $standard ? 'standard' : 'simplified', 'kind' => 'invoice', 'customer_id' => $customer->id,
            'issue_date' => today()->toDateString(), 'payment_means' => '42', 'note' => $note,
        ], $lines);
        $invoice->forceFill(['intercompany' => true, 'source' => $source])->save();

        return $invoice->id;
    }

    public function issueToBuyer(int $companyId, array $buyer, array $lines, string $source, ?string $note = null): ?IssuedInvoice
    {
        $unitId = $this->readyUnitFor($companyId);

        if ($unitId === null) {
            return null;
        }

        $customer = Customer::query()->whereNull('company_id')->whereNull('vat_number')->where('name_ar', $buyer['name_ar'])->first()
            ?? tap((new Customer([
                'name_ar' => $buyer['name_ar'], 'name_en' => $buyer['name_en'] ?? null, 'email' => $buyer['email'] ?? null, 'phone' => $buyer['phone'] ?? null,
            ]))->forceFill(['client_id' => $this->tenant->client()->id]))->save();

        $invoice = $this->save->handle(new Invoice, $companyId, [
            'type' => 'simplified', 'kind' => 'invoice', 'customer_id' => $customer->id,
            'issue_date' => today()->toDateString(), 'payment_means' => '42', 'note' => $note,
        ], $lines);
        $invoice->forceFill(['source' => $source])->save();
        $invoice = $this->issueOnceCommitted($invoice, $unitId);

        return new IssuedInvoice($invoice->id, $invoice->number, $invoice->status->value, $invoice->qr, $invoice->payableAmount(), (string) $invoice->vat);
    }

    public function readyUnitFor(int $companyId): ?int
    {
        return EinvoiceUnit::query()->where('company_id', $companyId)->whereIn('status', [UnitStatus::Production, UnitStatus::Compliance])->orderByRaw("status = 'production' desc")->value('id');
    }

    public function unitsFor(int $companyId): array
    {
        return EinvoiceUnit::query()->where('company_id', $companyId)->whereIn('status', [UnitStatus::Production, UnitStatus::Compliance])->get()
            ->map(fn (EinvoiceUnit $unit): array => ['id' => $unit->id, 'name' => $unit->name.' · '.$unit->environment->label()])->all();
    }

    /**
     * Send to ZATCA only once the caller's transaction has committed (at once when there is
     * none): a sale rolled back after ZATCA accepted its invoice would leave an accepted invoice
     * the books never saw. If issuing fails the sale stands and the invoice waits in Invoicing
     * to be issued again.
     */
    private function issueOnceCommitted(Invoice $invoice, int $unitId): Invoice
    {
        $actor = auth()->user();
        $clientId = $this->tenant->client()->id;

        DB::afterCommit(fn () => $this->tenant->runFor($clientId, function () use ($invoice, $unitId, $actor): void {
            try {
                $this->issue->handle($invoice->fresh(), EinvoiceUnit::query()->findOrFail($unitId), $actor);
            } catch (Throwable $exception) {
                report($exception);
            }
        }));

        return $invoice->fresh();
    }
}

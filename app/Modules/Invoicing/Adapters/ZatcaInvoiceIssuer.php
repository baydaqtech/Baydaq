<?php

namespace App\Modules\Invoicing\Adapters;

use App\Modules\Invoicing\Actions\IssueInvoice;
use App\Modules\Invoicing\Actions\SaveInvoice;
use App\Modules\Invoicing\Data\IssuedInvoice;
use App\Modules\Invoicing\Enums\UnitStatus;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Invoicing\Models\EinvoiceUnit;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Ports\InvoiceIssuer;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class ZatcaInvoiceIssuer implements InvoiceIssuer
{
    public function __construct(private SaveInvoice $save, private IssueInvoice $issue, private OrgDirectory $directory, private CurrentTenant $tenant) {}

    public function issueSimplified(int $companyId, int $unitId, array $lines, string $paymentMeans, ?string $note = null): IssuedInvoice
    {
        $netLines = array_map(function (array $line): array {
            $rate = BigDecimal::of((string) ($line['vat_rate'] ?? '15'));
            $gross = BigDecimal::of((string) $line['unit_price_incl_vat']);
            $divisor = BigDecimal::one()->plus($rate->dividedBy(100, 6, RoundingMode::HalfUp));
            $net = $gross->dividedBy($divisor, 4, RoundingMode::HalfUp);
            $discount = BigDecimal::of((string) ($line['discount_incl_vat'] ?? 0))->dividedBy($divisor, 2, RoundingMode::HalfUp);

            return ['description' => $line['description'], 'quantity' => (string) $line['quantity'], 'unit_price' => (string) $net, 'discount' => (string) $discount, 'vat_rate' => (string) $rate];
        }, $lines);

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
        $invoice = $this->issue->handle($invoice, EinvoiceUnit::query()->findOrFail($unitId), auth()->user());

        return new IssuedInvoice($invoice->id, $invoice->number, $invoice->status->value, $invoice->qr, $invoice->payableAmount(), (string) $invoice->vat);
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

    public function readyUnitFor(int $companyId): ?int
    {
        return EinvoiceUnit::query()->where('company_id', $companyId)->whereIn('status', [UnitStatus::Production, UnitStatus::Compliance])->orderByRaw("status = 'production' desc")->value('id');
    }

    public function unitsFor(int $companyId): array
    {
        return EinvoiceUnit::query()->where('company_id', $companyId)->whereIn('status', [UnitStatus::Production, UnitStatus::Compliance])->get()
            ->map(fn (EinvoiceUnit $unit): array => ['id' => $unit->id, 'name' => $unit->name.' · '.$unit->environment->label()])->all();
    }
}

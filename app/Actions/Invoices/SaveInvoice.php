<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaveInvoice
{
    /**
     * Create or update a draft invoice with its items. Line totals, subtotal,
     * VAT and total are always computed here (exact decimals, rounded half up).
     * New invoices get the next number for their issue year: INV-2026-0001.
     *
     * @param  array{client_id: int, project_id?: int|null, issue_date: string, due_date: string, notes?: string|null}  $data
     * @param  list<array{description: string, quantity: numeric-string|float|int, unit_price: numeric-string|float|int}>  $items
     */
    public function handle(Invoice $invoice, array $data, array $items): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $items): Invoice {
            $invoice->fill([...Arr::only($data, ['client_id', 'project_id', 'issue_date', 'due_date', 'notes']), 'project_id' => $data['project_id'] ?? null]);

            if (! $invoice->exists) {
                $invoice->forceFill([
                    'number' => $this->nextNumber(Carbon::parse($data['issue_date'])->year),
                    'status' => InvoiceStatus::Draft,
                    'vat_rate' => config('baydaq.vat_rate'),
                    'currency' => config('baydaq.currency'),
                ]);
            }

            $subtotal = BigDecimal::zero();
            $lines = [];

            foreach (array_values($items) as $index => $item) {
                $lineTotal = BigDecimal::of((string) $item['quantity'])
                    ->multipliedBy((string) $item['unit_price'])
                    ->toScale(2, RoundingMode::HalfUp);
                $subtotal = $subtotal->plus($lineTotal);

                $lines[] = [
                    'description' => $item['description'],
                    'quantity' => (string) $item['quantity'],
                    'unit_price' => (string) $item['unit_price'],
                    'total' => (string) $lineTotal,
                    'sort_order' => $index + 1,
                ];
            }

            $vat = $subtotal->multipliedBy((string) $invoice->vat_rate)->toScale(2, RoundingMode::HalfUp);

            $invoice->forceFill([
                'subtotal' => (string) $subtotal->toScale(2),
                'vat' => (string) $vat,
                'total' => (string) $subtotal->plus($vat)->toScale(2),
            ])->save();

            $invoice->items()->delete();

            foreach ($lines as $line) {
                $invoice->items()->make()->forceFill($line)->save();
            }

            return $invoice->refresh();
        });
    }

    /**
     * The next free number in the year, read under a lock so two admins can't take the same one.
     */
    private function nextNumber(int $year): string
    {
        $prefix = "INV-{$year}-";

        $last = Invoice::query()
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}

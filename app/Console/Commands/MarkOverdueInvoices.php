<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('invoices:mark-overdue')]
#[Description('Mark sent invoices past their due date as overdue')]
class MarkOverdueInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = Invoice::query()
            ->where('status', InvoiceStatus::Sent)
            ->whereDate('due_date', '<', today())
            ->update(['status' => InvoiceStatus::Overdue]);

        $this->info("{$count} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}

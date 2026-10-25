<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Invoicing\Enums\InvoiceKind;
use App\Modules\Invoicing\Enums\InvoiceStatus;
use App\Modules\Invoicing\Enums\InvoiceType;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tax invoice, credit note or debit note of a tenant company. Drafts are editable; issuing
 * assigns the number and submits to ZATCA through the company's unit.
 */
#[Table('inv__invoices')]
#[Fillable(['customer_id', 'type', 'kind', 'related_invoice_id', 'issue_date', 'due_date', 'supply_date', 'payment_means', 'note', 'reason'])]
class Invoice extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'invoice',
        'status' => 'draft',
        'currency' => 'SAR',
        'payment_means' => '10',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'kind' => InvoiceKind::class,
            'intercompany' => 'boolean',
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'supply_date' => 'date',
            'issued_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
            'rounding' => 'decimal:2',
            'counter' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<EinvoiceUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(EinvoiceUnit::class, 'unit_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function related(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'related_invoice_id');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class)->latest('submitted_at');
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /** What the buyer pays: the total with VAT plus the payable rounding (BT-114). */
    public function payableAmount(): string
    {
        return (string) BigDecimal::of((string) $this->total)->plus((string) ($this->rounding ?? 0))->toScale(2);
    }
}

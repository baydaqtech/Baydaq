<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baydaq's bill to a client (not a ZATCA e-invoice). Number, status and
 * totals are set by the invoice actions, never mass-assigned.
 */
#[Fillable(['client_id', 'project_id', 'issue_date', 'due_date', 'notes'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'SAR',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_rate' => 'decimal:4',
            'vat' => 'decimal:2',
            'total' => 'decimal:2',
            'issuer' => 'array',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Invoices the client still has to pay.
     *
     * @param  Builder<Invoice>  $query
     */
    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue]);
    }

    /**
     * Invoices the client can see (drafts stay internal).
     *
     * @param  Builder<Invoice>  $query
     */
    #[Scope]
    protected function issued(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::Paid]);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }
}

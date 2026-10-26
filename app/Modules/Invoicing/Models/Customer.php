<?php

namespace App\Modules\Invoicing\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A buyer. VAT number and the ZATCA address block are required for standard (B2B) invoices.
 */
#[Table('inv__customers')]
#[Fillable(['name_ar', 'name_en', 'vat_number', 'cr_number', 'email', 'phone', 'street', 'building_number', 'district', 'city', 'postal_code', 'country', 'is_active'])]
class Customer extends Model
{
    use BelongsToClient, HasBilingualFields;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'country' => 'SA',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function isVatRegistered(): bool
    {
        return $this->vat_number !== null && $this->vat_number !== '';
    }
}

<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Invoicing\Enums\UnitStatus;
use App\Modules\Invoicing\Enums\ZatcaEnvironment;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One issuing unit (a device or an installation) registered with ZATCA. It owns a key pair, a
 * certificate per stage, and the invoice counter with its hash chain. Chain writes lock the row.
 */
#[Table('inv__einvoice_units')]
#[Fillable(['name', 'environment'])]
#[Hidden(['private_key', 'compliance_secret', 'production_secret'])]
class EinvoiceUnit extends Model
{
    use BelongsToClient;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'environment' => 'sandbox',
        'status' => 'new',
        'counter' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => ZatcaEnvironment::class,
            'status' => UnitStatus::class,
            'private_key' => 'encrypted',
            'compliance_certificate' => 'encrypted',
            'compliance_secret' => 'encrypted',
            'production_certificate' => 'encrypted',
            'production_secret' => 'encrypted',
            'compliance_checks' => 'array',
            'counter' => 'integer',
        ];
    }

    /** The certificate (base64 DER) and secret to use right now: production once issued, else compliance. */
    public function credentials(): ?array
    {
        if ($this->status === UnitStatus::Production && $this->production_certificate) {
            return ['certificate' => $this->production_certificate, 'secret' => $this->production_secret];
        }

        if ($this->compliance_certificate) {
            return ['certificate' => $this->compliance_certificate, 'secret' => $this->compliance_secret];
        }

        return null;
    }
}

<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use Database\Factories\ClientRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A partnership or service request sent from the public forms.
 * Status, handler and resulting client are set by the admin actions.
 */
#[Table('requests')]
#[Fillable(['type', 'name', 'company', 'email', 'phone', 'message', 'services', 'locale', 'ip'])]
class ClientRequest extends Model
{
    /** @use HasFactory<ClientRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'locale' => 'ar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'status' => RequestStatus::class,
            'services' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

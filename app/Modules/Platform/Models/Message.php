<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One outgoing WhatsApp message and how it went. Kernel record; client_id says whose it was.
 */
#[Table('platform__messages')]
class Message extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['params' => 'array'];
    }
}

<?php

namespace App\Modules\Assets\Actions\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * The owner's next document number: TR-00001, HO-00001, INC-00001. Call inside a transaction.
 */
class NextNumber
{
    /**
     * @param  class-string<Model>  $model
     */
    public function for(string $model, string $prefix): string
    {
        $last = $model::query()->lockForUpdate()->orderByDesc('id')->value('number');
        $next = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}

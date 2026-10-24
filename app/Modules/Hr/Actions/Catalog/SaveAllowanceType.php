<?php

namespace App\Modules\Hr\Actions\Catalog;

use App\Modules\Hr\Models\AllowanceType;

class SaveAllowanceType
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AllowanceType $type, array $data): AllowanceType
    {
        $type->fill($data)->save();

        return $type;
    }
}

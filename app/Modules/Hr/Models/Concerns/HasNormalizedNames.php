<?php

namespace App\Modules\Hr\Models\Concerns;

use App\Modules\Hr\Support\ArabicText;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps name_ar_key / name_en_key in step with name_ar / name_en so the unique indexes see
 * normalized values. Set explicitly on save (seeders run without model events).
 */
trait HasNormalizedNames
{
    public static function bootHasNormalizedNames(): void
    {
        static::saving(fn (Model $model) => $model->normalizeNames());
    }

    public function normalizeNames(): static
    {
        $this->setAttribute('name_ar_key', ArabicText::key((string) $this->getAttribute('name_ar')));
        $this->setAttribute('name_en_key', ArabicText::key((string) $this->getAttribute('name_en')));

        return $this;
    }
}

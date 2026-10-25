<?php

namespace App\Modules\Hr\Models;

use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One person's answers to a survey. Anonymous surveys keep no employee id.
 */
#[Table('hr__survey_responses')]
class SurveyResponse extends Model
{
    use BelongsToClient;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['answers' => 'array'];
    }
}

<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable key/value settings, e.g. the company details printed on invoices.
 * Company data lives here, entered in the admin, never in code or seeders.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * The values for the given keys; missing keys come back as null.
     *
     * @param  list<string>  $keys
     * @return array<string, string|null>
     */
    public static function valuesFor(array $keys): array
    {
        $stored = static::query()->whereIn('key', $keys)->pluck('value', 'key');

        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $stored[$key] ?? null])->all();
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}

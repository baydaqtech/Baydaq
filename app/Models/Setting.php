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
     * The company details printed on invoices (entered under Admin → Settings).
     *
     * @var list<string>
     */
    public const ISSUER_KEYS = [
        'company_name_ar', 'company_name_en', 'vat_number', 'cr_number',
        'address_ar', 'address_en', 'bank_name', 'account_name', 'iban', 'invoice_note',
    ];

    /**
     * The current issuer details, keyed by ISSUER_KEYS.
     *
     * @return array<string, string|null>
     */
    public static function issuer(): array
    {
        return static::valuesFor(self::ISSUER_KEYS);
    }

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

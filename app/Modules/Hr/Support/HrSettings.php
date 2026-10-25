<?php

namespace App\Modules\Hr\Support;

use App\Modules\Hr\Models\HrSetting;
use App\Modules\Platform\Tenancy\CurrentTenant;

/**
 * The tenant's HR rules with their defaults. Unknown keys are refused so a typo can't create
 * a rule nobody reads.
 */
class HrSettings
{
    /** Rule => default. */
    public const DEFAULTS = [
        'dual_control' => false,          // IBAN and salary changes wait for a second person (H13)
    ];

    public function __construct(private CurrentTenant $tenant) {}

    public function get(string $key): mixed
    {
        $this->known($key);

        return HrSetting::query()->where('key', $key)->value('value') ?? self::DEFAULTS[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->known($key);

        $setting = HrSetting::query()->firstOrNew(['key' => $key]);
        $setting->forceFill(['client_id' => $this->tenant->client()->id, 'value' => $value])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = HrSetting::query()->pluck('value', 'key')->all();

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    private function known(string $key): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new \InvalidArgumentException("Unknown HR setting [{$key}].");
        }
    }
}

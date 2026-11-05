<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum RequestType: string
{
    use HasLabels;

    const LABELS = 'request_type';

    case Partnership = 'partnership';
    case Service = 'service';

    /**
     * The type's part of the public form's address. The site offers the partnership type as a project, so its
     * address says so; the stored value stays as it is.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Partnership => 'project',
            self::Service => 'service',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->slug() === $slug) {
                return $type;
            }
        }

        return null;
    }
}

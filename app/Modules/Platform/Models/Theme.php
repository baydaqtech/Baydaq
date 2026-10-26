<?php

namespace App\Modules\Platform\Models;

use App\Actions\Uploads\StoreLogo;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use App\Modules\Platform\Ports\Entitlements;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A client's own look. Used only while the package includes custom branding (suite.theme).
 */
#[Table('platform__themes')]
#[Fillable(['app_name', 'primary_color', 'accent_color', 'radius', 'mode'])]
class Theme extends Model
{
    use BelongsToClient;

    public const RADII = ['small' => '0.35rem', 'medium' => '0.625rem', 'large' => '1rem'];

    /**
     * The current tenant's theme as the web and the app read it, or null for Baydaq's own look.
     *
     * @return array{app_name: ?string, logo_url: ?string, primary: ?string, primary_foreground: ?string, accent: ?string, radius: string, mode: string}|null
     */
    public static function payload(): ?array
    {
        if (! app(Entitlements::class)->hasFeature('suite.theme')) {
            return null;
        }

        $theme = static::query()->first();

        return $theme ? [
            'app_name' => $theme->app_name,
            'logo_url' => StoreLogo::url($theme->logo_path),
            'primary' => $theme->primary_color,
            'primary_foreground' => $theme->primary_color ? self::readableOn($theme->primary_color) : null,
            'accent' => $theme->accent_color,
            'radius' => self::RADII[$theme->radius] ?? self::RADII['medium'],
            'mode' => $theme->mode,
        ] : null;
    }

    /** White or near-black text, whichever reads better on the colour (WCAG relative luminance). */
    public static function readableOn(string $hex): string
    {
        [$r, $g, $b] = array_map(function (string $pair): float {
            $channel = hexdec($pair) / 255;

            return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b > 0.179 ? '#1A1A1A' : '#FFFFFF';
    }
}

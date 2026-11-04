<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Actions\Uploads\StoreLogo;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

/**
 * The owner's branding: colours, logo, name, roundness and light or dark mode, used by the
 * suite, /me and the employee app.
 */
class ThemeController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    public function edit(): Response
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('update'));
        $theme = Theme::query()->first();

        return $this->suitePage('Suite/Theme', [
            'theme' => [
                'app_name' => $theme?->app_name ?? '', 'primary_color' => $theme?->primary_color ?? '#3B2414', 'accent_color' => $theme?->accent_color ?? '#C8902F',
                'radius' => $theme?->radius ?? 'medium', 'mode' => $theme?->mode ?? 'light', 'logo_url' => StoreLogo::url($theme?->logo_path),
            ],
        ]);
    }

    public function update(Request $request, StoreLogo $storeLogo): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('update'));
        $data = $request->validate([
            'app_name' => ['nullable', 'string', 'max:60'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'radius' => ['required', 'in:small,medium,large'],
            'mode' => ['required', 'in:light,dark,system'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['boolean'],
        ]);

        $theme = Theme::query()->first() ?? new Theme;
        $theme->fill([...$data, 'primary_color' => strtoupper($data['primary_color']), 'accent_color' => strtoupper($data['accent_color'])]);

        if ($request->hasFile('logo')) {
            $theme->logo_path = $storeLogo->handle($request->file('logo'), $theme->logo_path);
        } elseif ($request->boolean('remove_logo') && $theme->logo_path) {
            Storage::disk(config('baydaq.logo_disk'))->delete($theme->logo_path);
            $theme->logo_path = null;
        }

        $theme->save();
        $this->toast(__('suite.theme.saved'));

        return back();
    }

    public function reset(): RedirectResponse
    {
        $this->authorizeAccount(SuiteFeature::Structure->key('update'));
        $theme = Theme::query()->first();

        if ($theme?->logo_path) {
            Storage::disk(config('baydaq.logo_disk'))->delete($theme->logo_path);
        }

        $theme?->delete();
        $this->toast(__('suite.theme.reset_done'));

        return back();
    }
}

<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * The owner's own look: colours, name, roundness and mode, sent to every suite page and the app.
 */
class ThemeTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
    }

    public function test_the_owner_brands_the_suite_and_can_go_back_to_the_default(): void
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('settings/theme'), [
            'app_name' => 'Noura Cafés', 'primary_color' => '#0f766e', 'accent_color' => '#f59e0b', 'radius' => 'large', 'mode' => 'dark',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get($this->suiteUrl())->assertInertia(fn (Assert $page) => $page
            ->where('suite.theme.app_name', 'Noura Cafés')
            ->where('suite.theme.primary', '#0F766E')
            ->where('suite.theme.primary_foreground', '#FFFFFF')
            ->where('suite.theme.mode', 'dark'));

        $this->actingAs($this->owner)->delete($this->suiteUrl('settings/theme'))->assertSessionHasNoErrors();
        $this->asTenant();
        $this->assertSame(0, Theme::query()->count());
    }

    public function test_bad_colours_are_refused_and_only_account_wide_editors_may_brand(): void
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('settings/theme'), [
            'primary_color' => 'teal', 'accent_color' => '#f59e0b', 'radius' => 'large', 'mode' => 'light',
        ])->assertSessionHasErrors('primary_color');

        $branchEditor = $this->memberWith(['suite.structure.update'], ScopeLevel::Branch, $this->headOffice);
        $this->actingAs($branchEditor)->get($this->suiteUrl('settings/theme'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Suite;

use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\ScopeShortcut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
    }

    public function test_search_finds_only_the_employees_the_user_may_see(): void
    {
        $this->employeeAt($this->branch('RUH'), ['name_en' => 'Faisal Riyadh', 'name_ar' => 'فيصل الرياض']);
        $this->employeeAt($this->branch('JED'), ['name_en' => 'Faisal Jeddah', 'name_ar' => 'فيصل جدة']);
        $jeddahHr = $this->memberWith(['hr.employees.view'], ScopeLevel::Branch, $this->branch('JED'));

        $this->actingAs($this->owner)->getJson($this->suiteUrl('search?q=Faisal'))->assertOk()->assertJsonCount(2, 'hits');

        $this->actingAs($jeddahHr)->getJson($this->suiteUrl('search?q=Faisal'))->assertOk()
            ->assertJsonCount(1, 'hits')
            ->assertJsonPath('hits.0.label', 'فيصل جدة')
            ->assertJsonPath('hits.0.type', 'employee');

        $this->actingAs($this->owner)->getJson($this->suiteUrl('search?q=F'))->assertJsonCount(0, 'hits');
    }

    public function test_the_branch_filter_narrows_search(): void
    {
        $this->employeeAt($this->branch('RUH'), ['name_en' => 'Faisal Riyadh']);
        $this->employeeAt($this->branch('JED'), ['name_en' => 'Faisal Jeddah']);

        $this->actingAs($this->owner)->getJson($this->suiteUrl('search?q=Faisal&branches='.$this->branch('RUH')->id))
            ->assertJsonCount(1, 'hits');
    }

    public function test_visited_scopes_become_recent_and_a_view_can_be_saved_and_removed(): void
    {
        $jeddah = $this->branch('JED')->id;

        $this->actingAs($this->owner)->get($this->suiteUrl('?branches='.$jeddah, 'c-'.$this->company->slug))->assertOk();
        $this->actingAs($this->owner)->post($this->suiteUrl('shortcuts?branches='.$jeddah, 'c-'.$this->company->slug), ['name' => 'Jeddah'])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get($this->suiteUrl())->assertInertia(fn (Assert $page) => $page
            ->where('suite.shortcuts.0.name', 'Jeddah')
            ->where('suite.shortcuts.0.pinned', true)
            ->where('suite.shortcuts.0.branches', [$jeddah])
            ->where('suite.shortcuts.1.segment', 'all'));

        $shortcut = ScopeShortcut::query()->where('pinned', true)->sole();
        $stranger = $this->memberWith(['suite.structure.view'], ScopeLevel::Client);
        $this->actingAs($stranger)->delete($this->suiteUrl("shortcuts/{$shortcut->id}"))->assertNotFound();
        $this->actingAs($this->owner)->delete($this->suiteUrl("shortcuts/{$shortcut->id}"))->assertRedirect();
        $this->assertModelMissing($shortcut);
    }

    public function test_only_the_latest_recent_scopes_are_kept(): void
    {
        foreach (range(1, ScopeShortcut::RECENT + 3) as $index) {
            $this->travel(1)->minutes();
            ScopeShortcut::visit($this->owner->id, 'all', [$index]);
        }

        $this->assertSame(ScopeShortcut::RECENT, ScopeShortcut::query()->where('user_id', $this->owner->id)->count());
    }
}

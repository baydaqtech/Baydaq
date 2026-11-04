<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_landing_page_shows_each_project_as_a_logo_that_leads_to_it(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing')
                ->has('work', 5)
                ->where('work.0', ['slug' => 'opsmaster', 'href' => 'https://opsmaster.tech', 'external' => true])
                ->where('work.1', ['slug' => 'nuzul', 'href' => '/work/nuzul', 'external' => false])
                ->where('work.4', ['slug' => 'pchome', 'href' => 'https://pchome-dev-k0sgqb.laravel.cloud', 'external' => true])
                ->where('copy.work.projects.nuzul.name', 'نُزُل'));
    }

    public function test_a_project_page_shows_its_story_screens_and_neighbours(): void
    {
        $this->get('/work/nuzul-umrah?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WorkProject')
                ->where('slug', 'nuzul-umrah')
                ->has('web', 4)
                ->has('mobile', 4)
                ->where('web.0.src', '/images/work/nuzul-umrah/os-web-01-landing.jpg')
                ->where('web.0.caption', fn (string $caption) => $caption !== '')
                ->where('position', ['index' => 2, 'total' => 3])
                ->where('previous', 'nuzul')
                ->where('next', 'healthy-meals')
                ->where('copy.work.projects.nuzul-umrah.name', 'Nuzul Umrah'));
    }

    public function test_the_pages_run_in_a_loop(): void
    {
        $this->get('/work/nuzul')
            ->assertInertia(fn (Assert $page) => $page->where('previous', 'healthy-meals')->where('next', 'nuzul-umrah'));

        $this->get('/work/healthy-meals')
            ->assertInertia(fn (Assert $page) => $page->where('previous', 'nuzul-umrah')->where('next', 'nuzul'));
    }

    public function test_a_project_with_its_own_website_or_an_unknown_one_has_no_page(): void
    {
        $this->get('/work/opsmaster')->assertNotFound();
        $this->get('/work/pchome')->assertNotFound();
        $this->get('/work/not-a-project')->assertNotFound();
    }

    /**
     * Every screenshot exists, and each page project has its story in both languages with a caption per screenshot.
     */
    #[DataProvider('locales')]
    public function test_every_page_project_has_its_screenshots_and_copy(string $locale): void
    {
        $projects = array_filter(config('work.projects'), fn (array $project): bool => ! isset($project['url']));

        foreach ($projects as $slug => $project) {
            $copy = trans("work.projects.{$slug}", locale: $locale);

            foreach (['name', 'tagline', 'summary', 'overview', 'tags', 'architecture', 'specs', 'numbers'] as $key) {
                $this->assertNotEmpty($copy[$key] ?? null, "{$locale}: {$slug} has no {$key}");
            }

            foreach (['web', 'mobile'] as $kind) {
                $this->assertCount(count($project[$kind]), $copy[$kind], "{$locale}: {$slug} needs one {$kind} caption per screenshot");

                foreach ($project[$kind] as $file) {
                    $this->assertFileExists(public_path("images/work/{$slug}/{$file}"));
                }
            }
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['arabic' => ['ar'], 'english' => ['en']];
    }
}

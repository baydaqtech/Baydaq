<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_landing_page_lists_active_products_and_services_in_order(): void
    {
        Product::factory()->create(['slug' => 'second', 'name_ar' => 'الثاني', 'sort_order' => 20]);
        Product::factory()->create(['slug' => 'first', 'name_ar' => 'الأول', 'sort_order' => 10]);
        Product::factory()->inactive()->create(['slug' => 'hidden']);
        Service::factory()->create(['key' => 'email', 'name_ar' => 'البريد']);
        Service::factory()->inactive()->create(['key' => 'maps']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing')
                ->has('products', 2)
                ->where('products.0.slug', 'first')
                ->where('products.0.name', 'الأول')
                ->has('services', 1)
                ->where('services.0.name', 'البريد')
                ->where('copy.landing.hero.title_1', 'كل حركة'));
    }

    public function test_content_follows_the_chosen_language(): void
    {
        Product::factory()->create(['name_ar' => 'نزل', 'name_en' => 'Nuzol']);

        $this->get('/?lang=en')
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.0.name', 'Nuzol')
                ->where('copy.landing.hero.title_1', 'Every move'));
    }

    public function test_the_products_and_services_pages_render(): void
    {
        Product::factory()->count(8)->create();
        Service::factory()->count(2)->create();

        $this->get('/products')
            ->assertOk()
            ->assertDontSee('class="loader"', false)
            ->assertInertia(fn (Assert $page) => $page->component('Products')->has('products', 8));

        $this->get('/services')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Services')->has('services', 2));
    }

    public function test_the_landing_page_shows_the_loading_screen(): void
    {
        $this->get('/')->assertSee('class="loader"', false);
    }

    public function test_the_service_request_form_offers_active_services(): void
    {
        Service::factory()->create(['key' => 'sms', 'name_en' => 'SMS']);
        Service::factory()->inactive()->create(['key' => 'maps']);

        $this->get('/request/service?lang=en')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RequestForm')
                ->where('type', 'service')
                ->where('services', [['key' => 'sms', 'name' => 'SMS']])
                ->has('copy.requests'));

        $this->get('/request/partnership')
            ->assertInertia(fn (Assert $page) => $page->where('type', 'partnership')->where('services', []));
    }

    public function test_unknown_request_types_are_not_found(): void
    {
        $this->get('/request/job')->assertNotFound();
        $this->post('/request/job', [])->assertNotFound();
    }
}

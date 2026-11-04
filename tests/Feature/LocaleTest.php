<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_arabic_is_the_default_language(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('lang="ar" dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('dir', 'rtl')
                ->where('i18n.common.brand', 'بيدق تك'));
    }

    public function test_the_lang_query_switches_to_english_and_is_remembered(): void
    {
        $this->get('/?lang=en')
            ->assertSee('lang="en" dir="ltr"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('dir', 'ltr')
                ->where('i18n.common.brand', 'Baydaq Tech'));

        $this->get('/')->assertSee('lang="en" dir="ltr"', false);
    }

    public function test_an_unsupported_language_falls_back_to_arabic(): void
    {
        $this->get('/?lang=fr')->assertSee('lang="ar" dir="rtl"', false);
    }

    public function test_language_links_keep_the_rest_of_the_query_string(): void
    {
        $this->get('/?ref=campaign')
            ->assertInertia(fn (Assert $page) => $page
                ->where('localeUrls.en', url('/').'/?ref=campaign&lang=en')
                ->where('localeUrls.ar', url('/').'/?ref=campaign&lang=ar'));
    }

    public function test_a_signed_in_user_keeps_their_language_choice(): void
    {
        $user = User::factory()->client()->create(['locale' => 'ar']);

        $this->actingAs($user)->get('/app?lang=en')->assertSee('lang="en" dir="ltr"', false);
        $this->assertSame('en', $user->fresh()->locale);

        // A new session (another device) still opens in English.
        $this->flushSession();
        $this->actingAs($user->fresh())->get('/app')->assertSee('lang="en" dir="ltr"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_renders(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page->component('Landing', false));
    }

    public function test_a_contact_message_is_stored(): void
    {
        $this->post('/contact', [
            'name' => 'سارة العتيبي',
            'email' => 'sara@example.com',
            'company' => '',
            'service' => 'الذكاء الاصطناعي',
            'message' => 'نحتاج مساعداً ذكياً يجيب عن استفسارات العملاء.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas(ContactMessage::class, [
            'email' => 'sara@example.com',
            'service' => 'الذكاء الاصطناعي',
        ]);
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->post('/contact', [
            'name' => 'سارة',
            'email' => 'not-an-email',
            'service' => 'الذكاء الاصطناعي',
            'message' => 'رسالة طويلة بما يكفي للتحقق.',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount(ContactMessage::class, 0);
    }
}

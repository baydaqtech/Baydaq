<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_flagged_account_must_choose_a_new_password_first(): void
    {
        $admin = User::factory()->systemAdmin()->mustChangePassword()->create();

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('password.change'));
        $this->actingAs($admin)->withoutVite()->get('/password/change')->assertOk();
    }

    public function test_choosing_the_new_password_unlocks_the_account(): void
    {
        $admin = User::factory()->systemAdmin()->mustChangePassword()->create();

        $this->actingAs($admin)
            ->put('/password/change', [
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $admin->refresh();
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check('new-password-1', $admin->password));
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_the_new_password_must_be_confirmed_and_strong_enough(): void
    {
        $user = User::factory()->client()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put('/password/change', ['password' => 'short', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_accounts_that_are_not_flagged_cannot_use_the_screen(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)->get('/password/change')->assertRedirect(route('portal.home'));
        $this->actingAs($user)
            ->put('/password/change', ['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertRedirect(route('portal.home'));

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}

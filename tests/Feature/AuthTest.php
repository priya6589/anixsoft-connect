<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/', '/contacts', '/contacts/import', '/profile'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->get(route('login'))->assertOk()->assertSee('Log in');
    }

    public function test_user_can_log_in_and_lands_on_dashboard(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk()->assertSee('Dashboard')->assertSee($user->name);
    }

    public function test_wrong_password_is_rejected_and_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_password_can_be_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();

            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_user_can_change_password_and_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->put(route('profile.password'), [
            'current_password' => 'wrong',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHas('success');

        $this->patch(route('profile.update'), ['name' => 'Client Admin', 'email' => 'admin@example.com'])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertSame('Client Admin', $user->name);
    }

    public function test_create_user_command(): void
    {
        $this->artisan('user:create', ['email' => 'Owner@Example.com', '--password' => 'secret-pass-1'])
            ->assertSuccessful();

        $this->post(route('login'), ['email' => 'owner@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect(route('dashboard'));
    }
}

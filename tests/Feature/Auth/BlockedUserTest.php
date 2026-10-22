<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class BlockedUserTest extends TestCase
{
    use RefreshDatabase;

    private function blocked(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['email' => 'budi@example.com', 'blocked_at' => now()], $attrs));
    }

    public function test_a_blocked_customer_cannot_log_in_and_is_told_why(): void
    {
        $this->blocked();

        $this->from('/login')->post('/login', ['email' => 'budi@example.com', 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => User::BLOCKED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_the_blocked_message_is_not_shown_for_a_wrong_password(): void
    {
        $this->blocked();

        $this->from('/login')->post('/login', ['email' => 'budi@example.com', 'password' => 'salah-banget'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);

        $this->assertGuest();
    }

    public function test_unblocking_restores_login(): void
    {
        $user = $this->blocked();
        $user->forceFill(['blocked_at' => null])->save();

        $this->post('/login', ['email' => 'budi@example.com', 'password' => 'password'])->assertRedirect(route('profil'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_blocked_google_customer_is_turned_away(): void
    {
        $this->blocked(['google_id' => 'google-123', 'password' => null]);

        $google = (new GoogleUser)->setRaw(['email_verified' => true])->map(['id' => 'google-123', 'name' => 'Budi', 'email' => 'budi@example.com', 'avatar' => null]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google' => User::BLOCKED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_blocking_ends_a_session_that_is_already_open(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profil')->assertOk();

        $user->forceFill(['blocked_at' => now()])->save();

        $this->get('/profil')->assertRedirect(route('login'))->assertSessionHasErrors(['email' => User::BLOCKED_MESSAGE]);
        $this->assertGuest();
    }

    public function test_inertia_requests_of_a_blocked_customer_are_sent_to_login(): void
    {
        $this->actingAs($this->blocked());

        $this->get('/profil', ['X-Inertia' => 'true'])->assertStatus(409)->assertHeader('X-Inertia-Location', route('login'));
        $this->assertGuest();
    }

    public function test_admins_are_never_locked_out_by_the_blocked_flag(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'blocked_at' => now()]);

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }
}

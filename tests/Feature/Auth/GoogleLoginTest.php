<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogle(array $profile = [], bool $verified = true): void
    {
        $google = (new GoogleUser)
            ->setRaw(['email_verified' => $verified])
            ->map(array_merge([
                'id' => 'google-123',
                'name' => 'Budi Google',
                'email' => 'budi@gmail.com',
                'avatar' => 'https://lh3.googleusercontent.com/a/budi',
            ], $profile));

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_without_credentials_shows_a_friendly_error(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get('/auth/google')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google' => 'Login Google belum dikonfigurasi.']);
    }

    public function test_redirect_with_credentials_goes_to_google(): void
    {
        config([
            'services.google.client_id' => 'id',
            'services.google.client_secret' => 'secret',
            'services.google.redirect' => '/auth/google/callback',
        ]);

        $response = $this->get('/auth/google');

        $response->assertRedirectContains('https://accounts.google.com/');
    }

    public function test_new_google_user_is_created_verified_and_logged_in(): void
    {
        $this->fakeGoogle();

        $this->get('/auth/google/callback')->assertRedirect(route('profil'));

        $user = User::where('email', 'budi@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Budi Google', $user->name);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->is_admin);
    }

    public function test_returning_google_user_is_matched_by_google_id_even_if_email_changed(): void
    {
        $user = User::factory()->create(['email' => 'lama@example.com', 'google_id' => 'google-123']);
        $this->fakeGoogle(['email' => 'baru@gmail.com']);

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_links_to_existing_verified_account_and_keeps_password(): void
    {
        $user = User::factory()->create(['email' => 'budi@gmail.com']);
        $hash = $user->password;
        $this->fakeGoogle();

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame($hash, $user->password);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_linking_to_unverified_local_account_revokes_its_password(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'budi@gmail.com']);
        $this->fakeGoogle();

        $this->get('/auth/google/callback');
        auth()->logout();

        $user->refresh();
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->post('/login', ['email' => 'budi@gmail.com', 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogle(verified: false);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_failure_or_cancel_redirects_back_with_error(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('access_denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_google_admin_is_sent_to_admin_dashboard(): void
    {
        User::factory()->create(['email' => 'budi@gmail.com', 'is_admin' => true]);
        $this->fakeGoogle();

        $this->get('/auth/google/callback')->assertRedirect(route('admin.dashboard'));
    }
}

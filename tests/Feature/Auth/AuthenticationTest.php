<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'terms' => '1',
        ], $overrides);
    }

    public function test_login_page_offers_google_and_email_and_links_to_register(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Lanjutkan dengan Google')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee(route('register'));
    }

    public function test_register_page_offers_google_and_email_and_links_to_login(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Daftar dengan Google')
            ->assertDontSee('password_confirmation')
            ->assertSee(route('login'));
    }

    public function test_only_the_register_password_field_has_a_strength_meter(): void
    {
        $register = $this->get('/register')->assertOk()->getContent();
        $login = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('passwordMeter', $register);
        $this->assertStringContainsString('x-model="pw"', $register);
        $this->assertSame(1, substr_count($register, 'window.passwordMeter'));
        $this->assertStringNotContainsString('passwordMeter', $login);
    }

    public function test_navbar_links_to_register_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee(route('register'));
    }

    public function test_user_can_login_and_lands_on_profile(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $this->post('/login', ['email' => 'budi@example.com', 'password' => 'password'])
            ->assertRedirect(route('profil'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_email_is_case_insensitive(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $this->post('/login', ['email' => 'BUDI@Example.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->from('/login')
            ->post('/login', ['email' => 'budi@example.com', 'password' => 'salah-banget'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_email_gets_same_error_as_wrong_password(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'tidakada@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    }

    public function test_google_only_account_cannot_login_with_a_password(): void
    {
        User::factory()->create(['email' => 'budi@example.com', 'password' => null, 'google_id' => '123']);

        $this->post('/login', ['email' => 'budi@example.com', 'password' => ''])
            ->assertSessionHasErrors('password');
        $this->post('/login', ['email' => 'budi@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah']);
        }

        $this->post('/login', ['email' => 'x@example.com', 'password' => 'salah'])->assertStatus(429);
    }

    public function test_register_creates_user_and_logs_in(): void
    {
        $this->post('/register', $this->validRegistration())->assertRedirect(route('profil'));

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(password_verify('rahasia123', $user->password));
        $this->assertFalse($user->is_admin);
    }

    public function test_registered_user_can_log_in_again_later(): void
    {
        $this->post('/register', $this->validRegistration());
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['email' => 'budi@example.com', 'password' => 'rahasia123'])
            ->assertRedirect(route('profil'));
        $this->assertAuthenticated();
    }

    public function test_register_cannot_make_an_admin_via_mass_assignment(): void
    {
        $this->post('/register', $this->validRegistration(['is_admin' => '1']));

        $this->assertFalse(User::where('email', 'budi@example.com')->firstOrFail()->is_admin);
    }

    public function test_register_normalizes_email_to_lowercase(): void
    {
        $this->post('/register', $this->validRegistration(['email' => 'Budi@Example.COM']));

        $this->assertDatabaseHas('users', ['email' => 'budi@example.com']);
    }

    public function test_register_validation_rules(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->post('/register', $this->validRegistration())->assertSessionHasErrors('email');
        $this->post('/register', $this->validRegistration(['email' => 'baru@example.com', 'password' => 'pendek1']))->assertSessionHasErrors('password');
        $this->post('/register', $this->validRegistration(['email' => 'baru@example.com', 'password' => 'hurufsajaa']))->assertSessionHasErrors('password');
        $this->post('/register', $this->validRegistration(['email' => 'baru@example.com', 'password' => '1234567890']))->assertSessionHasErrors('password');
        $this->post('/register', $this->validRegistration(['email' => 'baru@example.com', 'terms' => null]))->assertSessionHasErrors('terms');
        $this->post('/register', $this->validRegistration(['email' => 'bukan-email']))->assertSessionHasErrors('email');
        $this->post('/register', $this->validRegistration(['email' => 'baru@example.com', 'name' => '']))->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registering_with_an_email_that_has_a_google_account_is_rejected(): void
    {
        User::factory()->create(['email' => 'budi@gmail.com', 'password' => null, 'google_id' => '123']);

        $this->post('/register', $this->validRegistration(['email' => 'budi@gmail.com']))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_guest_cannot_see_profile_and_is_sent_to_login(): void
    {
        $this->get('/profil')->assertRedirect(route('login'));
    }

    public function test_logged_in_user_is_redirected_away_from_login_and_register(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect();
        $this->get('/register')->assertRedirect();
    }

    public function test_admin_login_goes_to_admin_dashboard(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'is_admin' => true]);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_area_is_closed_to_guests_and_regular_users(): void
    {
        foreach (['/dashboard', '/admin'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_prefix_redirects_admins_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')
            ->assertRedirect('/dashboard');
    }

    public function test_admin_can_open_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Admin Uji', 'email' => 'admin-uji@example.com']);
    }

    public function test_dashboard_lives_at_the_dashboard_url(): void
    {
        $this->assertSame('/dashboard', route('admin.dashboard', absolute: false));
        $this->assertSame(route('admin.dashboard'), $this->admin()->homeUrl());
    }

    public function test_admin_page_is_not_indexed_and_uses_the_admin_shell(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertSee('data-page', false);
    }

    public function test_shared_props_expose_the_logged_in_admin_and_app_name(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('app.name', config('app.name'))
                ->where('auth.user.name', 'Admin Uji')
                ->where('auth.user.email', 'admin-uji@example.com')
                ->has('auth.user.avatar')
                ->missing('auth.user.password'));
    }

    public function test_user_numbers_count_only_non_admins(): void
    {
        User::factory()->count(3)->create();
        User::factory()->create(['created_at' => now()->subMonths(2)]);
        $this->admin();

        $this->actingAs(User::where('is_admin', true)->first())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('users.total', 4)
                ->where('users.this_month', 3));
    }

    public function test_latest_users_are_the_five_newest_non_admins(): void
    {
        foreach (range(1, 7) as $i) {
            User::factory()->create(['name' => "User $i", 'created_at' => now()->subDays(10 - $i)]);
        }

        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('users.latest', 5)
                ->where('users.latest.0.name', 'User 7')
                ->where('users.latest.4.name', 'User 3')
                ->missing('users.latest.0.password')
                ->missing('users.latest.0.remember_token'));
    }

    public function test_dashboard_works_with_no_users(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('users.total', 0)
                ->where('users.latest', []));
    }

    public function test_logout_from_the_admin_panel_does_a_full_redirect_to_the_store(): void
    {
        $this->actingAs($this->admin())
            ->post('/logout', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('home'));

        $this->assertGuest();
    }

    public function test_seeder_creates_the_local_admin_who_can_log_in_with_admin_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'admin'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(User::where('email', 'admin@gmail.com')->firstOrFail()->is_admin);
    }

    public function test_seeder_never_creates_the_weak_password_accounts_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new DatabaseSeeder)->run();

        $this->assertDatabaseCount('users', 0);
    }
}

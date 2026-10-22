<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Toko', 'email' => 'admin@toko.test']);
        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'phone' => '081234567890'], $overrides);
    }

    private function member(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    // ---------- akses ----------

    public function test_guests_and_regular_users_cannot_reach_user_routes(): void
    {
        $member = $this->member();
        $routes = [
            ['get', '/admin/user'], ['post', '/admin/user'],
            ['put', "/admin/user/{$member->id}"], ['patch', "/admin/user/{$member->id}/blokir"],
            ['delete', "/admin/user/{$member->id}"],
            ['patch', '/admin/user/massal/blokir'], ['delete', '/admin/user/massal'],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs($member);
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertNull($member->fresh()->blocked_at);
        $this->assertNotNull($member->fresh());
    }

    // ---------- daftar ----------

    public function test_index_lists_only_users_newest_first_with_login_methods(): void
    {
        $old = $this->member(['name' => 'Lama', 'password' => 'rahasia123', 'created_at' => now()->subDays(40)]);
        $google = $this->member(['name' => 'Google', 'password' => null, 'google_id' => 'g-1', 'avatar' => 'https://x.test/a.png']);
        $both = $this->member(['name' => 'Keduanya', 'password' => 'rahasia123', 'google_id' => 'g-2']);
        $none = $this->member(['name' => 'Buatan Admin', 'password' => null]);

        $this->get('/admin/user')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data', 4) // admin tidak ikut terdaftar
            ->where('users.data.0.name', 'Buatan Admin')
            ->where('users.data.0.methods', [])
            ->where('users.data.1.methods', ['google', 'email'])
            ->where('users.data.2.methods', ['google'])
            ->where('users.data.2.avatar', 'https://x.test/a.png')
            ->where('users.data.3.methods', ['email'])
            ->where('users.data.3.is_blocked', false));
    }

    public function test_the_list_shows_paid_orders_and_total_spent(): void
    {
        $buyer = User::factory()->create(['name' => 'Pembeli Tetap']);
        \App\Models\Order::factory()->for($buyer)->create(['total' => 45000])->forceFill(['status' => \App\Enums\OrderStatus::Completed])->save();
        \App\Models\Order::factory()->for($buyer)->create(['total' => 30000])->forceFill(['status' => \App\Enums\OrderStatus::Paid])->save();
        \App\Models\Order::factory()->for($buyer)->create(['total' => 99000])->forceFill(['status' => \App\Enums\OrderStatus::Expired])->save();

        $this->get('/admin/user')->assertInertia(fn ($page) => $page
            ->where('users.data.0', fn ($u) => $u['name'] === 'Pembeli Tetap' && $u['paid_orders'] === 2 && $u['total_spent'] === 75000));
    }

    public function test_the_list_never_exposes_passwords_or_hashes(): void
    {
        $this->member(['password' => 'rahasia-banget-123']);

        $content = $this->get('/admin/user')->getContent();

        $this->assertStringNotContainsString('rahasia-banget-123', $content);
        $this->assertStringNotContainsString('$2y$', $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }

    public function test_filters_by_search_status_and_method(): void
    {
        $this->member(['name' => 'Siti Aminah', 'email' => 'siti@example.com', 'phone' => '0811111111', 'google_id' => 'g-1', 'password' => null]);
        $this->member(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0822222222', 'blocked_at' => now()]);
        $this->member(['name' => 'Rina', 'email' => 'rina@example.com', 'phone' => '0833333333']);

        $names = fn (string $query) => collect($this->get("/admin/user?$query")->viewData('page')['props']['users']['data'])->pluck('name')->sort()->values()->all();

        $this->assertSame(['Siti Aminah'], $names('q=aminah'));
        $this->assertSame(['Budi'], $names('q=budi@example'));
        $this->assertSame(['Rina'], $names('q=0833'));
        $this->assertSame(['Budi'], $names('status=blocked'));
        $this->assertSame(['Rina', 'Siti Aminah'], $names('status=active'));
        $this->assertSame(['Siti Aminah'], $names('method=google'));
        $this->assertSame(['Budi', 'Rina'], $names('method=email'));
        $this->assertSame(['Rina'], $names('status=active&method=email'));
        $this->assertCount(3, $names('status=ngawur&method=ngawur'));
    }

    public function test_counts_and_stats(): void
    {
        $this->member(['google_id' => 'g-1', 'password' => null, 'created_at' => now()->subDays(60)]);
        $this->member(['blocked_at' => now()]);
        $this->member();

        $this->get('/admin/user')->assertInertia(fn ($page) => $page
            ->where('counts', ['all' => 3, 'active' => 2, 'blocked' => 1])
            ->where('stats', ['total' => 3, 'new_30_days' => 2, 'google' => 1, 'email' => 2]));
    }

    public function test_index_is_paginated(): void
    {
        User::factory()->count(17)->create();

        $this->get('/admin/user')->assertInertia(fn ($page) => $page->has('users.data', 15)->where('users.total', 17));
        $this->get('/admin/user?page=2')->assertInertia(fn ($page) => $page->has('users.data', 2));
    }

    // ---------- tambah ----------

    public function test_store_creates_a_user_who_is_not_an_admin_and_has_no_password(): void
    {
        $this->post('/admin/user', $this->payload())->assertSessionHasNoErrors()->assertSessionHas('success');

        $member = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(['Budi Santoso', '081234567890', false, null, null], [$member->name, $member->phone, $member->is_admin, $member->password, $member->google_id]);
    }

    public function test_store_cannot_be_used_to_make_an_admin(): void
    {
        $this->post('/admin/user', $this->payload(['is_admin' => true, 'password' => 'admin', 'blocked_at' => now()]));

        $member = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertFalse($member->is_admin);
        $this->assertNull($member->password);
        $this->assertNull($member->blocked_at);
    }

    public function test_store_normalizes_email_and_phone(): void
    {
        $this->post('/admin/user', $this->payload(['email' => '  Budi@Example.COM ', 'phone' => '+62 812-3456 (7890)']))->assertSessionHasNoErrors();

        $member = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame('+6281234567890', $member->phone);
    }

    public function test_phone_is_optional(): void
    {
        $this->post('/admin/user', $this->payload(['phone' => null]))->assertSessionHasNoErrors();
        $this->post('/admin/user', $this->payload(['email' => 'lain@example.com', 'phone' => '   ']))->assertSessionHasNoErrors();

        $this->assertSame([null, null], User::nonAdmin()->orderBy('id')->pluck('phone')->all());
    }

    public function test_store_validation_rules(): void
    {
        $this->member(['email' => 'dipakai@example.com']);

        $cases = [
            'nama kosong' => [['name' => ''], 'name'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name'],
            'email kosong' => [['email' => ''], 'email'],
            'email salah' => [['email' => 'bukan-email'], 'email'],
            'email sudah dipakai user' => [['email' => 'dipakai@example.com'], 'email'],
            'email sudah dipakai admin' => [['email' => 'admin@toko.test'], 'email'],
            'email beda huruf besar' => [['email' => 'DIPAKAI@example.com'], 'email'],
            'telepon huruf' => [['phone' => '08abc'], 'phone'],
            'telepon terlalu pendek' => [['phone' => '0812'], 'phone'],
            'telepon terlalu panjang' => [['phone' => '08123456789012345678901'], 'phone'],
        ];

        foreach ($cases as $name => [$override, $field]) {
            try {
                $this->post('/admin/user', $this->payload($override))->assertSessionHasErrors($field);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail("Kasus '$name' harus menolak field '$field': ".$e->getMessage());
            }
        }

        $this->assertSame(1, User::nonAdmin()->count());
    }

    // ---------- ubah ----------

    public function test_update_changes_name_and_phone_only(): void
    {
        $member = $this->member(['name' => 'Lama', 'email' => 'tetap@example.com', 'phone' => '0811111111']);

        $this->put("/admin/user/{$member->id}", ['name' => 'Baru', 'phone' => '0899-9999-999'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $member->refresh();
        $this->assertSame(['Baru', '08999999999', 'tetap@example.com'], [$member->name, $member->phone, $member->email]);
    }

    public function test_update_refuses_to_change_the_email(): void
    {
        $member = $this->member(['email' => 'tetap@example.com']);

        $this->put("/admin/user/{$member->id}", ['name' => 'Baru', 'email' => 'ganti@example.com'])->assertSessionHasErrors('email');

        $this->assertSame('tetap@example.com', $member->fresh()->email);
        $this->assertNotSame('Baru', $member->fresh()->name);
    }

    public function test_update_can_clear_the_phone_and_validates_the_name(): void
    {
        $member = $this->member(['phone' => '0811111111']);

        $this->put("/admin/user/{$member->id}", ['name' => 'Tetap Ada', 'phone' => null])->assertSessionHasNoErrors();
        $this->assertNull($member->fresh()->phone);

        $this->put("/admin/user/{$member->id}", ['name' => '', 'phone' => null])->assertSessionHasErrors('name');
    }

    public function test_admins_and_unknown_ids_are_not_reachable_as_users(): void
    {
        foreach ([$this->admin->id, 9999] as $id) {
            $this->put("/admin/user/{$id}", ['name' => 'Diretas'])->assertNotFound();
            $this->patch("/admin/user/{$id}/blokir")->assertNotFound();
            $this->delete("/admin/user/{$id}")->assertNotFound();
        }

        $this->assertSame('Admin Toko', $this->admin->fresh()->name);
        $this->assertNull($this->admin->fresh()->blocked_at);
    }

    // ---------- blokir ----------

    public function test_toggle_blocks_and_unblocks(): void
    {
        $member = $this->member(['name' => 'Budi']);

        $this->patch("/admin/user/{$member->id}/blokir")->assertSessionHas('success', '"Budi" diblokir.');
        $this->assertTrue($member->fresh()->isBlocked());

        $this->patch("/admin/user/{$member->id}/blokir")->assertSessionHas('success', 'Blokir "Budi" dibuka.');
        $this->assertFalse($member->fresh()->isBlocked());
    }

    // ---------- hapus ----------

    public function test_destroy_deletes_the_user_permanently(): void
    {
        $member = $this->member(['name' => 'Budi']);
        $other = $this->member();

        $this->delete("/admin/user/{$member->id}")->assertSessionHas('success', 'User "Budi" dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertNotNull($other->fresh(), 'user lain tidak terpengaruh');
        $this->assertNotNull($this->admin->fresh());
    }

    public function test_destroy_also_clears_active_sessions_and_reset_tokens(): void
    {
        $member = $this->member(['email' => 'budi@example.com']);
        $other = $this->member(['email' => 'lain@example.com']);

        foreach ([[$member, 's1'], [$other, 's2']] as [$user, $id]) {
            \Illuminate\Support\Facades\DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'hash', 'created_at' => now()]);
        }

        $this->delete("/admin/user/{$member->id}");

        $this->assertDatabaseMissing('sessions', ['id' => 's1']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'budi@example.com']);
        $this->assertDatabaseHas('sessions', ['id' => 's2']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'lain@example.com']);
    }

    public function test_a_deleted_user_loses_access_and_the_email_can_be_used_again(): void
    {
        $member = $this->member(['email' => 'budi@example.com']);

        $this->delete("/admin/user/{$member->id}");

        auth()->logout();
        $this->post('/login', ['email' => 'budi@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/register', ['name' => 'Budi Baru', 'email' => 'budi@example.com', 'password' => 'rahasia123', 'terms' => '1'])->assertRedirect(route('profil'));
        $this->assertSame('Budi Baru', User::where('email', 'budi@example.com')->value('name'));
    }

    public function test_a_blocked_user_can_be_deleted_too(): void
    {
        $member = $this->member(['blocked_at' => now()]);

        $this->delete("/admin/user/{$member->id}")->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    // ---------- aksi massal ----------

    public function test_bulk_block_blocks_only_the_selected_active_users(): void
    {
        [$a, $b, $keep] = User::factory()->count(3)->create()->all();
        $already = $this->member(['blocked_at' => now()->subDay()]);

        $this->patch('/admin/user/massal/blokir', ['ids' => [$a->id, $b->id, $already->id], 'blocked' => true])
            ->assertSessionHasNoErrors()->assertSessionHas('success', '2 user diblokir.');

        $this->assertTrue($a->fresh()->isBlocked() && $b->fresh()->isBlocked());
        $this->assertFalse($keep->fresh()->isBlocked());
        $this->assertTrue($already->fresh()->blocked_at->isYesterday(), 'yang sudah diblokir tidak diperbarui waktunya');
    }

    public function test_bulk_unblock_reopens_only_blocked_users(): void
    {
        $blocked = User::factory()->count(2)->create(['blocked_at' => now()]);
        $active = $this->member();

        $this->patch('/admin/user/massal/blokir', ['ids' => [...$blocked->pluck('id'), $active->id], 'blocked' => false])
            ->assertSessionHas('success', 'Blokir 2 user dibuka.');

        $this->assertSame([false, false], $blocked->map(fn ($u) => $u->fresh()->isBlocked())->all());
        $this->assertFalse($active->fresh()->isBlocked());
    }

    public function test_bulk_block_ends_the_sessions_of_everyone_it_blocks(): void
    {
        $member = $this->member();
        $this->actingAs($member)->get('/profil')->assertOk();

        $this->actingAs($this->admin)->patch('/admin/user/massal/blokir', ['ids' => [$member->id], 'blocked' => true]);

        $this->actingAs($member->fresh())->get('/profil')->assertRedirect(route('login'));
    }

    public function test_bulk_destroy_deletes_only_the_selected_users_with_their_leftovers(): void
    {
        [$a, $b] = [$this->member(['email' => 'a@example.com']), $this->member(['email' => 'b@example.com'])];
        $keep = $this->member(['email' => 'keep@example.com']);

        foreach ([$a, $b, $keep] as $u) {
            \Illuminate\Support\Facades\DB::table('sessions')->insert(['id' => "s{$u->id}", 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert(['email' => $u->email, 'token' => 'hash', 'created_at' => now()]);
        }

        $this->delete('/admin/user/massal', ['ids' => [$a->id, $b->id]])->assertSessionHas('success', '2 user dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $a->id]);
        $this->assertDatabaseMissing('users', ['id' => $b->id]);
        $this->assertDatabaseMissing('sessions', ['id' => "s{$a->id}"]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'b@example.com']);
        $this->assertDatabaseHas('users', ['id' => $keep->id]);
        $this->assertDatabaseHas('sessions', ['id' => "s{$keep->id}"]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'keep@example.com']);
    }

    public function test_bulk_actions_refuse_admins_and_unknown_ids(): void
    {
        $member = $this->member();
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $cases = [
            ['patch', '/admin/user/massal/blokir', ['ids' => [$this->admin->id], 'blocked' => true], 'ids.0'],
            ['patch', '/admin/user/massal/blokir', ['ids' => [$member->id, $otherAdmin->id], 'blocked' => true], 'ids.1'],
            ['patch', '/admin/user/massal/blokir', ['ids' => [9999], 'blocked' => true], 'ids.0'],
            ['patch', '/admin/user/massal/blokir', ['blocked' => true], 'ids'],
            ['patch', '/admin/user/massal/blokir', ['ids' => [], 'blocked' => true], 'ids'],
            ['patch', '/admin/user/massal/blokir', ['ids' => [$member->id, $member->id], 'blocked' => true], 'ids.1'],
            ['patch', '/admin/user/massal/blokir', ['ids' => [$member->id]], 'blocked'],
            ['patch', '/admin/user/massal/blokir', ['ids' => [$member->id], 'blocked' => 'mungkin'], 'blocked'],
            ['patch', '/admin/user/massal/blokir', ['ids' => range(1, 501), 'blocked' => true], 'ids'],
            ['delete', '/admin/user/massal', ['ids' => [$this->admin->id]], 'ids.0'],
            ['delete', '/admin/user/massal', [], 'ids'],
        ];

        foreach ($cases as $i => [$method, $url, $data, $field]) {
            try {
                $this->$method($url, $data)->assertSessionHasErrors($field);
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail("Kasus #$i harus menolak field '$field': ".$e->getMessage());
            }
        }

        $this->assertNotNull($this->admin->fresh());
        $this->assertNotNull($otherAdmin->fresh());
        $this->assertFalse($otherAdmin->fresh()->isBlocked());
        $this->assertFalse($member->fresh()->isBlocked());
    }

    public function test_the_numeric_user_routes_still_work_next_to_the_bulk_routes(): void
    {
        $member = $this->member(['name' => 'Budi']);

        $this->patch("/admin/user/{$member->id}/blokir")->assertSessionHas('success');
        $this->delete("/admin/user/{$member->id}")->assertSessionHas('success', 'User "Budi" dihapus.');
    }

    // ---------- penamaan ----------

    public function test_the_old_customer_address_redirects_to_the_user_page(): void
    {
        $this->get('/admin/pelanggan')->assertRedirect('/admin/user');
        $this->get('/admin/user')->assertOk()->assertInertia(fn ($page) => $page->component('Users/Index'));
    }

    public function test_the_profile_page_only_offers_the_admin_panel_to_admins(): void
    {
        $this->actingAs($this->member(['name' => 'Budi']))->get('/profil')->assertOk()
            ->assertSee('Budi')->assertDontSee('Panel Admin');

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/profil')->assertOk()->assertSee('Panel Admin');
    }
}

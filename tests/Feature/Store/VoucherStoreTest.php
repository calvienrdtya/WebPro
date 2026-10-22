<?php

namespace Tests\Feature\Store;

use App\Models\Product;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherStoreTest extends TestCase
{
    use RefreshDatabase;

    private function claimed(User $user, Voucher $voucher): UserVoucher
    {
        return UserVoucher::create(['user_id' => $user->id, 'voucher_id' => $voucher->id, 'claimed_at' => now()]);
    }

    public function test_guest_sees_only_running_listed_vouchers_with_their_details(): void
    {
        Voucher::factory()->create(['code' => 'JALAN10', 'title' => 'Diskon Jalan', 'discount_value' => 10, 'max_discount' => 20000, 'min_purchase' => 50000, 'ends_on' => '2030-12-31', 'quota' => 10, 'used_count' => 4]);
        Voucher::factory()->fixed(25000)->create(['code' => 'NOMINAL', 'title' => 'Potongan Nominal']);
        Voucher::factory()->create(['code' => 'TERSEMBUNYI', 'is_listed' => false]);
        Voucher::factory()->inactive()->create(['code' => 'MATIAKTIF']);
        Voucher::factory()->create(['code' => 'SUDAHLEWAT', 'ends_on' => today()->subDay()]);
        Voucher::factory()->create(['code' => 'BELUMMULAI', 'starts_on' => today()->addDay()]);
        Voucher::factory()->create(['code' => 'KUOTAHABIS', 'quota' => 3, 'used_count' => 3]);

        $this->get('/voucher')->assertOk()
            ->assertSee('<title>Voucher Diskon Akun Premium — ProAkses</title>', false)
            ->assertSee('JALAN10')->assertSee('10%')->assertSee('Maks. potongan Rp20.000')->assertSee('Min. belanja Rp50.000')
            ->assertSee('Hingga 31 Des 2030')->assertSee('Sisa kuota 6')->assertSee('Semua produk')
            ->assertSee('NOMINAL')->assertSee('Rp25.000')
            ->assertDontSee('TERSEMBUNYI')->assertDontSee('MATIAKTIF')->assertDontSee('SUDAHLEWAT')->assertDontSee('BELUMMULAI')->assertDontSee('KUOTAHABIS')
            ->assertSee('Masuk untuk klaim')->assertDontSee('Diklaim');
    }

    public function test_scoped_voucher_lists_its_products(): void
    {
        $voucher = Voucher::factory()->create(['applies_to_all' => false]);
        $voucher->products()->sync([Product::factory()->create(['name' => 'Netflix'])->id, Product::factory()->create(['name' => 'Spotify'])->id]);

        $this->get('/voucher')->assertOk()->assertSee('Netflix dan Spotify');
    }

    public function test_voucher_without_claim_shows_the_code_and_a_note_instead_of_a_button(): void
    {
        Voucher::factory()->create(['code' => 'LANGSUNG', 'requires_claim' => false]);

        $this->get('/voucher')->assertOk()->assertSee('LANGSUNG')->assertSee('ketik kode ini langsung')->assertDontSee('Masuk untuk klaim');
    }

    public function test_user_can_claim_a_voucher_once_and_sees_it_as_ready_to_use(): void
    {
        $user = User::factory()->create();
        $voucher = Voucher::factory()->create(['code' => 'KLAIMKU']);

        $this->actingAs($user)->get('/voucher')->assertOk()->assertSee('Klaim')->assertDontSee('Siap Dipakai');

        $this->post("/voucher/{$voucher->id}/klaim")->assertRedirect(route('vouchers.index'))->assertSessionHas('success');
        $this->assertDatabaseCount('user_vouchers', 1);
        $this->assertDatabaseHas('user_vouchers', ['user_id' => $user->id, 'voucher_id' => $voucher->id]);

        $this->get('/voucher')->assertOk()->assertSee('Siap Dipakai')->assertSee('Pilih produk');

        // Klaim ulang tidak menggandakan data.
        $this->post("/voucher/{$voucher->id}/klaim")->assertSessionHas('info');
        $this->assertDatabaseCount('user_vouchers', 1);
    }

    public function test_claim_is_refused_for_hidden_direct_ended_inactive_scheduled_and_exhausted_vouchers(): void
    {
        $user = User::factory()->create();
        $blocked = [
            Voucher::factory()->create(['is_listed' => false]),
            Voucher::factory()->create(['requires_claim' => false]),
            Voucher::factory()->create(['ends_on' => today()->subDay()]),
            Voucher::factory()->inactive()->create(),
            Voucher::factory()->create(['starts_on' => today()->addDay()]),
            Voucher::factory()->create(['quota' => 1, 'used_count' => 1]),
        ];

        foreach ($blocked as $voucher) {
            $this->actingAs($user)->post("/voucher/{$voucher->id}/klaim")->assertRedirect(route('vouchers.index'))->assertSessionHas('error');
        }

        $this->assertDatabaseCount('user_vouchers', 0);
        $this->post('/voucher/9999/klaim')->assertNotFound();
    }

    public function test_guest_claim_goes_through_login_and_returns_with_the_voucher_claimed(): void
    {
        $voucher = Voucher::factory()->create();

        $this->get("/voucher/{$voucher->id}/klaim")->assertRedirect(route('login'));
        $this->post("/voucher/{$voucher->id}/klaim")->assertRedirect(route('login'));
        $this->assertDatabaseCount('user_vouchers', 0);

        // Setelah login, URL tujuan (klaim) dijalankan.
        $user = User::factory()->create(['password' => 'rahasia123']);
        $this->get("/voucher/{$voucher->id}/klaim");
        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])->assertRedirect("/voucher/{$voucher->id}/klaim");
        $this->get("/voucher/{$voucher->id}/klaim")->assertRedirect(route('vouchers.index'));
        $this->assertDatabaseHas('user_vouchers', ['user_id' => $user->id, 'voucher_id' => $voucher->id]);
    }

    public function test_claimed_voucher_that_ended_shows_as_expired_but_inactive_ones_are_hidden(): void
    {
        $user = User::factory()->create();
        $this->claimed($user, Voucher::factory()->create(['code' => 'LEWATKU', 'ends_on' => today()->subDay()]));
        $this->claimed($user, Voucher::factory()->inactive()->create(['code' => 'DIMATIKAN']));
        // Voucher kadaluarsa milik orang lain tidak tampil ke user ini.
        Voucher::factory()->create(['code' => 'LEWATORANGLAIN', 'ends_on' => today()->subDay()]);

        $this->actingAs($user)->get('/voucher')->assertOk()
            ->assertSee('LEWATKU')->assertSee('Kadaluarsa')
            ->assertDontSee('DIMATIKAN')->assertDontSee('LEWATORANGLAIN');
    }

    public function test_tabs_filter_available_and_claimed_vouchers_and_guests_have_no_claimed_tab(): void
    {
        $user = User::factory()->create();
        $mine = Voucher::factory()->create(['code' => 'PUNYAKU']);
        Voucher::factory()->create(['code' => 'BELUMKU']);
        $this->claimed($user, $mine);

        $this->actingAs($user);
        $this->get('/voucher?status=tersedia')->assertOk()->assertSee('BELUMKU')->assertDontSee('PUNYAKU')->assertSee('noindex', false);
        $this->get('/voucher?status=diklaim')->assertOk()->assertSee('PUNYAKU')->assertDontSee('BELUMKU');
        $this->get('/voucher?status=ngawur')->assertOk()->assertSee('PUNYAKU')->assertSee('BELUMKU');

        auth()->logout();
        $this->get('/voucher')->assertOk()->assertDontSee('status=diklaim', false);
    }

    public function test_empty_state_and_sitemap(): void
    {
        $this->get('/voucher')->assertOk()->assertSee('Belum ada voucher tersedia');
        $this->get('/sitemap.xml')->assertSee('<loc>'.url('/voucher').'</loc>', false);
    }

    public function test_voucher_text_is_escaped(): void
    {
        Voucher::factory()->create(['title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>']);

        $content = $this->get('/voucher')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringNotContainsString('<img src=x', $content);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('legacy');

        Schema::connection('legacy')->create('users', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('phone')->nullable();
            $t->boolean('is_admin')->default(false);
            $t->string('remember_token')->nullable();
            $t->timestamps();
        });

        Schema::connection('legacy')->create('accounts', function ($t) {
            $t->id();
            $t->string('type');
            $t->string('email');
            $t->string('password');
            $t->string('profile_name')->nullable();
            $t->string('profile_pin')->nullable();
            $t->string('status');
            $t->date('expired_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::connection('legacy')->create('order_items', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('accountable_id')->nullable();
            $t->decimal('price', 10, 2)->default(0);
            $t->decimal('cogs_snapshot', 10, 2)->nullable();
        });

        Schema::connection('legacy')->create('orders', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('warranty_type_id')->nullable();
            $t->string('voucher_code')->nullable();
            $t->decimal('voucher_discount', 10, 2)->default(0);
            $t->string('order_number');
            $t->string('status');
            $t->decimal('product_price', 10, 2);
            $t->decimal('warranty_price', 10, 2)->default(0);
            $t->decimal('service_fee_amount', 10, 2)->default(0);
            $t->decimal('biaya_admin', 10, 2)->nullable();
            $t->decimal('total_amount', 10, 2);
            $t->text('notes')->nullable();
            $t->string('buyer_name')->nullable();
            $t->string('buyer_email')->nullable();
            $t->string('buyer_phone')->nullable();
            $t->timestamps();
        });

        Schema::connection('legacy')->create('payments', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('status');
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('claimed_paid_at')->nullable();
        });
    }

    private function legacyUser(array $attrs): void
    {
        DB::connection('legacy')->table('users')->insert($attrs + [
            'name' => 'Pelanggan Lama',
            'password' => Hash::make('sandi-lama-123'),
            'phone' => '081234567890',
            'is_admin' => false,
            'remember_token' => 'token-lama',
            'created_at' => '2026-07-04 14:51:09',
            'updated_at' => '2026-07-05 10:00:00',
        ]);
    }

    public function test_imports_users_keeping_password_and_times_and_skips_admin(): void
    {
        $this->legacyUser(['id' => 15, 'email' => 'admin.lama@example.com', 'is_admin' => true]);
        $this->legacyUser(['id' => 16, 'name' => '  Budi Lama ', 'email' => ' Budi@Example.com ']);

        $this->artisan('legacy:import', ['table' => 'users'])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'admin.lama@example.com']);
        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame('Budi Lama', $user->name);
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->remember_token);
        $this->assertSame('081234567890', $user->phone);
        $this->assertSame('2026-07-04 14:51:09', $user->created_at->format('Y-m-d H:i:s'));
        $this->assertTrue(Hash::check('sandi-lama-123', $user->password));

        $this->assertDatabaseHas('legacy_id_maps', ['source_table' => 'users', 'legacy_id' => 16, 'new_id' => $user->id]);
        $this->assertDatabaseHas('legacy_id_maps', ['source_table' => 'users', 'legacy_id' => 15, 'new_id' => null]);

        // Diulang: tidak ada duplikat.
        $this->artisan('legacy:import', ['table' => 'users'])->assertSuccessful();
        $this->assertSame(1, User::where('email', 'budi@example.com')->count());
        $this->assertSame(2, DB::table('legacy_id_maps')->count());
    }

    public function test_stops_when_email_already_used_locally(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);
        $this->legacyUser(['id' => 16, 'email' => 'budi@example.com']);
        $this->legacyUser(['id' => 17, 'email' => 'sari@example.com']);

        $this->artisan('legacy:import', ['table' => 'users'])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'sari@example.com']);
        $this->assertSame(0, DB::table('legacy_id_maps')->count());
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->legacyUser(['id' => 16, 'email' => 'budi@example.com']);

        $this->artisan('legacy:import', ['table' => 'users', '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'budi@example.com']);
        $this->assertSame(0, DB::table('legacy_id_maps')->count());
    }

    private function legacyAccount(array $attrs): void
    {
        DB::connection('legacy')->table('accounts')->insert($attrs + [
            'type' => 'netflix',
            'email' => 'akun@example.com',
            'password' => 'rahasia-lama',
            'profile_name' => 'Profil 1',
            'profile_pin' => '1234',
            'notes' => "Eksternal\n",
            'created_at' => '2026-10-01 09:00:00',
            'updated_at' => '2026-10-02 10:00:00',
        ]);
    }

    public function test_imports_accounts_with_product_status_and_exact_expiry(): void
    {
        $this->travelTo('2026-10-08 12:00:00');
        $netflix = Product::factory()->create(['slug' => 'netflix-premium']);
        $this->legacyAccount(['id' => 1, 'status' => 'available', 'expired_at' => '2026-10-30']);
        $this->legacyAccount(['id' => 2, 'status' => 'reserved', 'expired_at' => '2026-10-15']);
        $this->legacyAccount(['id' => 3, 'status' => 'reserved', 'expired_at' => '2026-09-22']);
        $this->legacyAccount(['id' => 4, 'status' => 'sold', 'expired_at' => '2026-10-29']);
        $this->legacyAccount(['id' => 5, 'status' => 'sold', 'expired_at' => '2026-10-29']);
        $this->legacyAccount(['id' => 6, 'status' => 'replaced', 'expired_at' => '2026-10-29']);
        DB::connection('legacy')->table('order_items')->insert(['accountable_id' => 5]);

        $this->artisan('legacy:import', ['table' => 'accounts'])->assertSuccessful();

        $byLegacy = fn (int $id) => Account::findOrFail(DB::table('legacy_id_maps')->where(['source_table' => 'accounts', 'legacy_id' => $id])->value('new_id'));

        $a = $byLegacy(1);
        $this->assertSame($netflix->id, $a->product_id);
        $this->assertSame('rahasia-lama', $a->password);
        $this->assertSame('Profil 1', $a->profile);
        $this->assertSame('1234', $a->pin);
        $this->assertSame('2026-10-01', $a->started_at->toDateString());
        $this->assertSame('2026-10-30', $a->expires_at->toDateString());
        $this->assertSame(29, $a->active_days);
        $this->assertSame('Eksternal [Lama #1]', $a->note);
        $this->assertSame('available', $a->status->value);

        $this->assertSame('available', $byLegacy(2)->status->value);
        $this->assertSame('expired', $byLegacy(3)->status->value);
        $this->assertSame('Penjualan luar web (sistem lama)', $byLegacy(4)->sold_to);
        $this->assertNull($byLegacy(5)->sold_to);
        $this->assertSame('sold', $byLegacy(5)->status->value);
        $this->assertSame('problem', $byLegacy(6)->status->value);

        $this->artisan('legacy:import', ['table' => 'accounts'])->assertSuccessful();
        $this->assertSame(6, Account::count());
    }

    public function test_accounts_stop_when_product_missing(): void
    {
        $this->legacyAccount(['id' => 1, 'type' => 'canva', 'status' => 'available', 'expired_at' => '2026-10-30']);

        $this->artisan('legacy:import', ['table' => 'accounts'])->assertFailed();
        $this->assertSame(0, Account::count());
    }

    public function test_imports_orders_with_delivery_warranty_and_sold_account_and_skips_admin_test_orders(): void
    {
        $netflix = Product::factory()->create(['slug' => 'netflix-premium', 'name' => 'Netflix Premium']);
        $this->legacyUser(['id' => 15, 'email' => 'admin.lama@example.com', 'is_admin' => true]);
        $this->legacyUser(['id' => 20, 'name' => 'Budi', 'email' => 'budi@example.com']);
        $this->legacyAccount(['id' => 64, 'status' => 'sold', 'expired_at' => '2026-10-30']);
        $this->legacyAccount(['id' => 90, 'type' => 'netflix', 'status' => 'sold', 'expired_at' => '2026-10-30']);
        $this->artisan('legacy:import', ['table' => 'users']);
        $this->artisan('legacy:import', ['table' => 'accounts']);

        $order = fn (array $a) => DB::connection('legacy')->table('orders')->insert($a + [
            'warranty_type_id' => 5, 'status' => 'completed', 'product_price' => 42400, 'warranty_price' => 6000, 'service_fee_amount' => 1000,
            'biaya_admin' => 0, 'voucher_code' => 'LAMA', 'voucher_discount' => 5000, 'total_amount' => 44400, 'buyer_name' => 'Budi',
            'buyer_email' => 'Budi@Example.com', 'buyer_phone' => '081234567890', 'created_at' => '2026-09-17 10:00:00', 'updated_at' => '2026-09-17 10:05:00',
        ]);
        $order(['id' => 96, 'user_id' => 20, 'order_number' => 'PRO20260917AAAAA']);
        $order(['id' => 95, 'user_id' => 20, 'order_number' => 'INV20260704BBBBB', 'status' => 'cancelled', 'warranty_type_id' => 4, 'notes' => 'Salah transfer']);
        $order(['id' => 112, 'user_id' => 15, 'order_number' => 'PRO20260902CCCCC']);
        DB::connection('legacy')->table('order_items')->insert([
            ['order_id' => 96, 'product_id' => 13, 'accountable_id' => 64, 'price' => 42400, 'cogs_snapshot' => 37200],
            ['order_id' => 95, 'product_id' => 13, 'accountable_id' => null, 'price' => 42400, 'cogs_snapshot' => 37200],
            ['order_id' => 112, 'product_id' => 13, 'accountable_id' => 90, 'price' => 42400, 'cogs_snapshot' => 0],
        ]);
        DB::connection('legacy')->table('payments')->insert([
            ['order_id' => 96, 'status' => 'success', 'paid_at' => '2026-09-17 10:04:00', 'claimed_paid_at' => '2026-09-17 10:02:00'],
            ['order_id' => 95, 'status' => 'pending', 'paid_at' => null, 'claimed_paid_at' => null],
        ]);
        $full = \App\Models\Warranty::factory()->create(['name' => 'Garansi Penuh']);

        $this->artisan('legacy:import', ['table' => 'orders'])->assertSuccessful();

        $o = \App\Models\Order::where('code', 'PRO20260917AAAAA')->firstOrFail();
        $this->assertSame('completed', $o->status->value);
        $this->assertSame([$netflix->id, 'Netflix Premium', 1, 'budi@example.com'], [$o->product_id, $o->product_name, $o->months, $o->customer_email]);
        $this->assertSame([42400, 42400, 6000, 1000, 5000, 44400], [$o->list_price, $o->product_price, $o->warranty_price, $o->service_fee, $o->voucher_discount, $o->total]);
        $this->assertSame([$full->id, 'Garansi Full (Maksimal)', 28], [$o->warranty_id, $o->warranty_name, $o->warranty_days]);
        $this->assertSame('2026-09-17 10:04:00', $o->paid_at->format('Y-m-d H:i:s'));
        $d = $o->deliveries()->sole();
        $this->assertSame('2026-10-15', $d->warranty_ends_on->toDateString());
        $this->assertSame(37200, $d->cogs);
        $account = Account::findOrFail($d->account_id);
        $this->assertSame(['Budi (PRO20260917AAAAA)', 37200], [$account->sold_to, $account->cogs]);

        $cancelled = \App\Models\Order::where('code', 'INV20260704BBBBB')->firstOrFail();
        $this->assertSame(['cancelled', null, 'Garansi 15 Hari (Minimal)', 'Salah transfer'], [$cancelled->status->value, $cancelled->warranty_id, $cancelled->warranty_name, $cancelled->admin_note]);
        $this->assertNull($cancelled->deliveries()->sole()->account_id);

        $this->assertFalse(\App\Models\Order::where('code', 'PRO20260902CCCCC')->exists());
        $this->assertSame(1, Account::count()); // akun pesanan uji admin dihapus

        $this->artisan('legacy:import', ['table' => 'orders'])->assertSuccessful();
        $this->assertSame(2, \App\Models\Order::count());

        // Voucher yang dipakai pesanan lama dibuat nonaktif dan pesanannya ditautkan.
        $this->artisan('legacy:import', ['table' => 'vouchers'])->assertSuccessful();
        $voucher = \App\Models\Voucher::where('code', 'LAMA')->firstOrFail();
        $this->assertSame(['fixed', 5000, false, false], [$voucher->discount_type->value ?? $voucher->discount_type, $voucher->discount_value, (bool) $voucher->is_active, (bool) $voucher->is_listed]);
        $this->assertSame(1, $voucher->used_count);
        $this->assertSame($voucher->id, $o->fresh()->voucher_id);
        $this->artisan('legacy:import', ['table' => 'vouchers'])->assertSuccessful();
        $this->assertSame(1, $voucher->fresh()->used_count);

        // Riwayat ganti akun -> klaim garansi selesai.
        $this->legacyAccount(['id' => 63, 'status' => 'replaced', 'expired_at' => '2026-10-30', 'notes' => 'Eksternal']);
        DB::table('legacy_id_maps')->where(['source_table' => 'accounts', 'legacy_id' => 63])->delete();
        $this->artisan('legacy:import', ['table' => 'accounts']);
        Schema::connection('legacy')->create('order_account_histories', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_item_id');
            $t->unsignedBigInteger('old_accountable_id');
            $t->unsignedBigInteger('new_accountable_id');
            $t->text('reason')->nullable();
            $t->unsignedBigInteger('replaced_by')->nullable();
            $t->timestamp('replaced_at');
            $t->timestamp('created_at')->nullable();
        });
        DB::connection('legacy')->table('order_account_histories')->insert([
            'id' => 4, 'order_item_id' => 1, 'old_accountable_id' => 63, 'new_accountable_id' => 64, 'reason' => 'Incorrect Password',
            'replaced_by' => 15, 'replaced_at' => '2026-09-20 22:03:46', 'created_at' => '2026-09-20 22:03:46',
        ]);

        $this->artisan('legacy:import', ['table' => 'order_account_histories'])->assertSuccessful();

        $claim = \App\Models\WarrantyClaim::sole();
        $old = Account::where('note', 'like', '%[Lama #63]%')->firstOrFail();
        $this->assertSame([$o->id, $d->id, 'done', 'stock', 'incorrect_password', false, null], [$claim->order_id, $claim->delivery_id, $claim->status->value ?? $claim->status, $claim->mode, $claim->reason->value ?? $claim->reason, (bool) $claim->outside_warranty, $claim->handled_by]);
        $this->assertSame([$old->id, $account->id], [$claim->old_account_id, $claim->new_account_id]);
        $this->assertStringContainsString('Diganti (PRO20260917AAAAA, 20/09/2026): Incorrect Password', $old->note);
        $this->assertSame('2026-09-20 22:03:46', $claim->replaced_at->format('Y-m-d H:i:s'));

        $this->artisan('legacy:import', ['table' => 'order_account_histories'])->assertSuccessful();
        $this->assertSame(1, \App\Models\WarrantyClaim::count());
    }
}

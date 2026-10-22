<?php

namespace Tests\Feature\Admin;

use App\Models\ServiceFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceFeeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function fee(string $name = 'Biaya Admin', int $amount = 1000, bool $active = false): ServiceFee
    {
        return ServiceFee::create(['name' => $name, 'amount' => $amount, 'is_active' => $active]);
    }

    public function test_guests_and_regular_users_cannot_reach_the_routes(): void
    {
        $fee = $this->fee();
        $routes = [['get', '/admin/biaya-layanan'], ['post', '/admin/biaya-layanan'], ['put', "/admin/biaya-layanan/{$fee->id}"], ['patch', "/admin/biaya-layanan/{$fee->id}/aktif"], ['delete', "/admin/biaya-layanan/{$fee->id}"]];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
    }

    public function test_the_page_lists_fees_with_the_active_one_first(): void
    {
        $this->fee('Beta', 2000);
        $this->fee('Alfa', 1000, true);

        $this->get('/admin/biaya-layanan')->assertOk()->assertInertia(fn ($page) => $page
            ->component('ServiceFees/Index')->has('fees', 2)
            ->where('fees.0', fn ($f) => $f['name'] === 'Alfa' && $f['amount'] === 1000 && $f['is_active'] === true)
            ->where('fees.1.name', 'Beta'));
    }

    public function test_a_fee_can_be_created_with_normalised_numbers(): void
    {
        $this->post('/admin/biaya-layanan', ['name' => 'Biaya Admin', 'amount' => '01500', 'is_active' => true])->assertSessionHas('success');

        $fee = ServiceFee::firstOrFail();
        $this->assertSame(['Biaya Admin', 1500, true], [$fee->name, $fee->amount, $fee->is_active]);
        $this->assertSame(1500, ServiceFee::current());
    }

    public function test_validation(): void
    {
        $this->fee('Sudah Ada');

        $this->post('/admin/biaya-layanan', [])->assertSessionHasErrors(['name', 'amount']);
        $this->post('/admin/biaya-layanan', ['name' => 'X', 'amount' => 0])->assertSessionHasErrors('amount');
        $this->post('/admin/biaya-layanan', ['name' => 'X', 'amount' => 'murah'])->assertSessionHasErrors('amount');
        $this->post('/admin/biaya-layanan', ['name' => 'X', 'amount' => 1000001])->assertSessionHasErrors('amount');
        $this->post('/admin/biaya-layanan', ['name' => str_repeat('a', 61), 'amount' => 1000])->assertSessionHasErrors('name');
        $this->post('/admin/biaya-layanan', ['name' => 'Sudah Ada', 'amount' => 1000])->assertSessionHasErrors(['name' => 'Sudah ada biaya layanan dengan nama itu.']);
        $this->assertSame(1, ServiceFee::count());
    }

    public function test_only_one_fee_can_be_active_creating_or_editing_an_active_one_deactivates_the_others(): void
    {
        $old = $this->fee('Lama', 1000, true);

        $this->post('/admin/biaya-layanan', ['name' => 'Baru', 'amount' => 2000, 'is_active' => true])
            ->assertSessionHas('success', 'Biaya layanan "Baru" ditambahkan. "Lama" dinonaktifkan karena hanya satu biaya yang boleh aktif.');
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame(2000, ServiceFee::current());

        $this->put("/admin/biaya-layanan/{$old->id}", ['name' => 'Lama', 'amount' => 1000, 'is_active' => true])->assertSessionHas('success');
        $this->assertSame(['Lama'], ServiceFee::where('is_active', true)->pluck('name')->all());
    }

    public function test_an_inactive_fee_leaves_the_active_one_alone(): void
    {
        $active = $this->fee('Aktif', 1000, true);

        $this->post('/admin/biaya-layanan', ['name' => 'Cadangan', 'amount' => 3000, 'is_active' => false])->assertSessionHas('success', 'Biaya layanan "Cadangan" ditambahkan.');

        $this->assertTrue($active->fresh()->is_active);
        $this->assertSame(1000, ServiceFee::current());
    }

    public function test_update_keeps_the_name_unique_but_allows_saving_itself(): void
    {
        $a = $this->fee('Alfa');
        $this->fee('Beta');

        $this->put("/admin/biaya-layanan/{$a->id}", ['name' => 'Alfa', 'amount' => 1500])->assertSessionHasNoErrors();
        $this->put("/admin/biaya-layanan/{$a->id}", ['name' => 'Beta', 'amount' => 1500])->assertSessionHasErrors('name');
        $this->assertSame(1500, $a->fresh()->amount);
    }

    public function test_the_toggle_switches_exclusively_and_can_turn_the_fee_off(): void
    {
        $a = $this->fee('Alfa', 1000, true);
        $b = $this->fee('Beta', 2000);

        $this->patch("/admin/biaya-layanan/{$b->id}/aktif")->assertSessionHas('success', 'Biaya layanan "Beta" diaktifkan. "Alfa" dinonaktifkan karena hanya satu biaya yang boleh aktif.');
        $this->assertSame([false, true], [$a->fresh()->is_active, $b->fresh()->is_active]);

        $this->patch("/admin/biaya-layanan/{$b->id}/aktif")->assertSessionHas('success', 'Biaya layanan "Beta" dinonaktifkan.');
        $this->assertSame(0, ServiceFee::current());
    }

    public function test_deleting_the_active_fee_removes_the_charge_for_new_orders(): void
    {
        $fee = $this->fee('Aktif', 1000, true);

        $this->delete("/admin/biaya-layanan/{$fee->id}")->assertSessionHas('success');

        $this->assertSame(0, ServiceFee::count());
        $this->assertSame(0, ServiceFee::current());
    }

    public function test_the_old_fee_card_is_gone_from_settings(): void
    {
        $this->get('/admin/pengaturan')->assertInertia(fn ($page) => $page->missing('fee')->has('chat'));
        $this->put('/admin/pengaturan/biaya-layanan', ['fee_enabled' => true, 'fee_amount' => 1000])->assertNotFound();
    }
}

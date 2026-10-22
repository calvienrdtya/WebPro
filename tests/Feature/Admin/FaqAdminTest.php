<?php

namespace Tests\Feature\Admin;

use App\Enums\FaqCategory;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'umum', 'question' => 'Apa itu ProAkses?', 'answer' => 'Toko akun premium.',
            'is_active' => true, 'show_on_home' => false, 'show_on_product' => false,
        ], $overrides);
    }

    public function test_guests_and_regular_users_cannot_reach_faq_routes(): void
    {
        $faq = Faq::factory()->create();
        $routes = [
            ['get', '/admin/faq'], ['post', '/admin/faq'], ['put', "/admin/faq/{$faq->id}"], ['patch', "/admin/faq/{$faq->id}/aktif"],
            ['patch', "/admin/faq/{$faq->id}/urut"], ['delete', "/admin/faq/{$faq->id}"], ['patch', '/admin/faq/massal/aktif'], ['delete', '/admin/faq/massal'],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertNotNull($faq->fresh());
    }

    public function test_index_lists_in_display_order_with_counts_stats_and_filters(): void
    {
        $b = Faq::factory()->create(['question' => 'Bayar?', 'category' => FaqCategory::Payment]);
        $a = Faq::factory()->onHome()->create(['question' => 'Umum satu?']);
        Faq::factory()->inactive()->onProduct()->create(['question' => 'Garansi?', 'category' => FaqCategory::Warranty]);

        $this->get('/admin/faq')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Faqs/Index')
            ->where('faqs.data.0.id', $b->id)
            ->where('faqs.data.1.id', $a->id)
            ->where('counts.all', 3)->where('counts.umum', 1)->where('counts.pembayaran', 1)->where('counts.akun', 0)->where('counts.garansi', 1)
            ->where('stats', ['total' => 3, 'active' => 2, 'home' => 1, 'product' => 0]));

        $this->get('/admin/faq?category=garansi')->assertInertia(fn ($page) => $page->has('faqs.data', 1));
        $this->get('/admin/faq?q=bayar')->assertInertia(fn ($page) => $page->has('faqs.data', 1)->where('faqs.data.0.id', $b->id));
        $this->get('/admin/faq?category=ngawur')->assertInertia(fn ($page) => $page->has('faqs.data', 3)->where('filters.category', 'all'));
    }

    public function test_store_appends_to_the_end_and_saves_flags(): void
    {
        Faq::factory()->create(['sort_order' => 7]);

        $this->post('/admin/faq', $this->payload(['show_on_home' => true, 'show_on_product' => true]))
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $faq = Faq::where('question', 'Apa itu ProAkses?')->firstOrFail();
        $this->assertSame(8, $faq->sort_order);
        $this->assertSame([FaqCategory::General, true, true, true], [$faq->category, $faq->is_active, $faq->show_on_home, $faq->show_on_product]);
    }

    public function test_store_validation_rules(): void
    {
        $cases = [
            'kategori kosong' => [['category' => ''], 'category'],
            'kategori tidak dikenal' => [['category' => 'lain'], 'category'],
            'pertanyaan kosong' => [['question' => ''], 'question'],
            'pertanyaan terlalu panjang' => [['question' => str_repeat('a', 201)], 'question'],
            'jawaban kosong' => [['answer' => ''], 'answer'],
            'jawaban terlalu panjang' => [['answer' => str_repeat('a', 2001)], 'answer'],
        ];

        foreach ($cases as $name => [$override, $field]) {
            $this->post('/admin/faq', $this->payload($override))->assertSessionHasErrors($field, $name);
        }
        $this->assertDatabaseCount('faqs', 0);
    }

    public function test_update_changes_fields_but_keeps_position(): void
    {
        $faq = Faq::factory()->create(['sort_order' => 3]);

        $this->put("/admin/faq/{$faq->id}", $this->payload(['question' => 'Baru?', 'category' => 'akun', 'is_active' => false]))->assertSessionHasNoErrors();

        $faq->refresh();
        $this->assertSame(['Baru?', FaqCategory::Account, false, 3], [$faq->question, $faq->category, $faq->is_active, $faq->sort_order]);
    }

    public function test_toggle_and_bulk_activation(): void
    {
        [$a, $b] = Faq::factory()->count(2)->create();

        $this->patch("/admin/faq/{$a->id}/aktif")->assertSessionHas('success');
        $this->assertFalse($a->fresh()->is_active);

        $this->patch('/admin/faq/massal/aktif', ['ids' => [$a->id, $b->id], 'active' => false])->assertSessionHasNoErrors();
        $this->assertSame([false, false], [$a->fresh()->is_active, $b->fresh()->is_active]);

        $this->patch('/admin/faq/massal/aktif', ['ids' => [$a->id]])->assertSessionHasErrors('active');
        $this->patch('/admin/faq/massal/aktif', ['ids' => [9999], 'active' => true])->assertSessionHasErrors('ids.0');
    }

    public function test_move_swaps_with_the_neighbor_in_the_same_category_only(): void
    {
        $a = Faq::factory()->create(['question' => 'A?']);
        $other = Faq::factory()->create(['question' => 'Lain?', 'category' => FaqCategory::Payment]);
        $b = Faq::factory()->create(['question' => 'B?']);
        $c = Faq::factory()->create(['question' => 'C?']);

        $this->patch("/admin/faq/{$b->id}/urut", ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame(['B?', 'A?', 'C?'], Faq::where('category', 'umum')->ordered()->pluck('question')->all());
        $this->assertSame(2, $other->fresh()->sort_order); // kategori lain tidak tergeser

        $this->patch("/admin/faq/{$b->id}/urut", ['direction' => 'down'])->assertSessionHasNoErrors();
        $this->assertSame(['A?', 'B?', 'C?'], Faq::where('category', 'umum')->ordered()->pluck('question')->all());

        // Sudah di ujung: tidak berubah dan tidak galat.
        $this->patch("/admin/faq/{$a->id}/urut", ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->patch("/admin/faq/{$c->id}/urut", ['direction' => 'down'])->assertSessionHasNoErrors();
        $this->assertSame(['A?', 'B?', 'C?'], Faq::where('category', 'umum')->ordered()->pluck('question')->all());

        $this->patch("/admin/faq/{$a->id}/urut", ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    }

    public function test_destroy_and_bulk_destroy(): void
    {
        [$a, $b, $c] = Faq::factory()->count(3)->create();

        $this->delete("/admin/faq/{$a->id}")->assertSessionHas('success');
        $this->delete('/admin/faq/massal', ['ids' => [$b->id, $c->id]])->assertSessionHas('success', '2 FAQ dihapus.');

        $this->assertDatabaseCount('faqs', 0);
    }
}

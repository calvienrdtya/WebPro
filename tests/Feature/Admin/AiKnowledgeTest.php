<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\KnowledgeStatus;
use App\Jobs\ProcessKnowledgeEntry;
use App\Models\Account;
use App\Models\Faq;
use App\Models\KnowledgeEntry;
use App\Models\Product;
use App\Models\ServiceFee;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\Ai\KnowledgeBase;
use App\Services\Ai\PromptBuilder;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://router.uji.test/v1';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Setting::put(AiSettings::ADVANCED_PREFIX.'knowledge_cache_seconds', '0');
    }

    private function connect(): void
    {
        Setting::put(AiSettings::BASE_URL, self::URL);
        AiSettings::putApiKey('sk-uji');
        Setting::put(AiSettings::CHAT_MODEL, 'model-chat');
        Setting::put(AiSettings::VISION_MODEL, 'model-vision');
    }

    private function knowledge(): string
    {
        return app(KnowledgeBase::class)->forPrompt();
    }

    private function pdf(int $pages = 1, string $name = 'panduan.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat("1 0 obj << /Type /Page >> endobj\n", $pages)."%%EOF");
    }

    private function entry(array $attrs = [], KnowledgeStatus $status = KnowledgeStatus::Ready): KnowledgeEntry
    {
        $entry = KnowledgeEntry::create(['title' => 'Entri Uji', 'raw_text' => 'teks', 'body' => '## Entri Uji', 'is_active' => true, ...$attrs]);
        $entry->forceFill(['status' => $status])->save();

        return $entry;
    }

    public function test_guests_and_regular_users_cannot_reach_the_routes(): void
    {
        $entry = $this->entry();
        $routes = [['get', '/admin/ai/pengetahuan'], ['post', '/admin/ai/pengetahuan'], ['put', "/admin/ai/pengetahuan/{$entry->id}"], ['patch', "/admin/ai/pengetahuan/{$entry->id}/setujui"], ['delete', "/admin/ai/pengetahuan/{$entry->id}"], ['get', '/admin/ai/pengetahuan/bagian/faqs']];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }
        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
    }

    public function test_the_policy_entry_is_built_in_active_and_cannot_be_deleted(): void
    {
        $policy = KnowledgeEntry::where('is_builtin', true)->sole();

        $this->assertSame([KnowledgeStatus::Ready, true], [$policy->status, $policy->is_active]);
        $this->assertStringContainsString('Pembayaran hanya lewat QRIS', $this->knowledge());

        $this->delete("/admin/ai/pengetahuan/{$policy->id}")->assertSessionHas('error');
        $this->delete('/admin/ai/pengetahuan/massal', ['ids' => [$policy->id]])->assertSessionHas('success', '0 entri dihapus. Entri bawaan dilewati.');
        $this->assertNotSoftDeleted($policy);
    }

    public function test_shop_data_matches_the_store_and_leaves_out_internal_data(): void
    {
        $product = Product::factory()->create(['name' => 'Netflix Uji', 'price' => 50000, 'discount_percent' => 20]);
        Account::factory()->count(2)->create(['product_id' => $product->id, 'cogs' => 17777]);
        Account::factory()->status(AccountStatus::Sold)->create(['product_id' => $product->id]);
        Product::factory()->inactive()->create(['name' => 'Produk Tersembunyi']);
        Warranty::factory()->forAllProducts()->create(['name' => 'Garansi Penuh', 'duration_days' => 30, 'price' => 7000]);
        ServiceFee::create(['name' => 'Biaya Admin', 'amount' => 1500, 'is_active' => true]);
        Voucher::factory()->create(['code' => 'TAMPIL10', 'requires_claim' => false]);
        Voucher::factory()->create(['code' => 'RAHASIA', 'is_listed' => false]);
        Faq::factory()->create(['question' => 'Bisa di TV?', 'answer' => 'Bisa.']);
        Faq::factory()->create(['question' => 'FAQ mati?', 'is_active' => false]);

        $text = $this->knowledge();

        $this->assertStringContainsString('### Netflix Uji', $text);
        $this->assertStringContainsString('- Stok: 2 akun tersedia', $text);
        $this->assertStringContainsString('  - 1 Bulan: Rp40.000, diskon 20% dari Rp50.000', $text);
        $this->assertStringContainsString('- Netflix Uji: Garansi Penuh (30 hari) Rp7.000', $text);
        $this->assertStringContainsString('biaya layanan Rp1.500', $text);
        $this->assertStringContainsString('**TAMPIL10**', $text);
        $this->assertStringContainsString('bisa langsung diketik saat checkout', $text);
        $this->assertStringContainsString("### Bisa di TV?\nBisa.", $text);
        foreach (['Produk Tersembunyi', 'RAHASIA', 'FAQ mati?', '17777', '17.777'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $text);
        }
    }

    public function test_sections_can_be_switched_off_and_previewed(): void
    {
        Faq::factory()->create(['question' => 'Bisa di TV?']);

        $this->getJson('/admin/ai/pengetahuan/bagian/faqs')->assertOk()->assertJson(fn ($json) => $json->where('text', fn ($t) => str_starts_with($t, "## FAQ\n### Bisa di TV?")));
        $this->getJson('/admin/ai/pengetahuan/bagian/lain')->assertNotFound();

        $this->patch('/admin/ai/pengetahuan/bagian/faqs', ['enabled' => false])->assertSessionHas('success', 'Bagian "FAQ" tidak dipakai AI.');
        $this->assertStringNotContainsString('Bisa di TV?', $this->knowledge());

        $this->get('/admin/ai/pengetahuan')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Ai/Knowledge')
            ->where('sections.4', fn ($s) => $s['key'] === 'faqs' && $s['enabled'] === false && $s['tokens'] > 0)
            ->where('size.limit', 30000)
            ->where('size.overhead', 2500)
            ->has('entries.data', 1));
    }

    public function test_the_knowledge_reaches_the_system_prompt(): void
    {
        $this->entry(['body' => '## Error Household']);

        $this->assertStringContainsString("=== PENGETAHUAN TOKO ===\n## Produk, harga, dan stok", app(PromptBuilder::class)->system());
        $this->assertStringContainsString('## Error Household', app(PromptBuilder::class)->system());
    }

    public function test_a_text_entry_without_ai_waits_for_review_then_is_used(): void
    {
        Queue::fake();
        $product = Product::factory()->create();

        $this->post('/admin/ai/pengetahuan', ['title' => 'Cara login', 'raw_text' => '## Cara login TV', 'use_ai' => false, 'products' => [$product->id]])->assertSessionHas('success');

        $entry = KnowledgeEntry::latest('id')->first();
        $this->assertSame([KnowledgeStatus::Review, '## Cara login TV', false], [$entry->status, $entry->body, $entry->is_active]);
        $this->assertSame([$product->id], $entry->products->modelKeys());
        $this->assertStringNotContainsString('Cara login TV', $this->knowledge());
        Queue::assertNothingPushed();

        $this->patch("/admin/ai/pengetahuan/{$entry->id}/setujui")->assertSessionHas('success');
        $this->assertSame([KnowledgeStatus::Ready, true], [$entry->fresh()->status, $entry->fresh()->is_active]);
        $this->assertStringContainsString('## Cara login TV', $this->knowledge());

        $this->patch("/admin/ai/pengetahuan/{$entry->id}/aktif")->assertSessionHas('success');
        $this->assertStringNotContainsString('Cara login TV', $this->knowledge());
    }

    public function test_an_entry_with_attachments_is_queued_for_ai(): void
    {
        Queue::fake();

        $this->post('/admin/ai/pengetahuan', [
            'title' => 'Error Household',
            'raw_text' => 'Klik ini adalah akun saya',
            'use_ai' => true,
            'with_qa' => true,
            'attachments' => [UploadedFile::fake()->image('layar.png'), $this->pdf(3)],
        ])->assertSessionHas('success', 'Entri ditambahkan dan sedang dirapikan AI.');

        $entry = KnowledgeEntry::with('attachments')->latest('id')->first();
        $this->assertSame(KnowledgeStatus::Processing, $entry->status);
        $this->assertSame(['image', 'pdf'], $entry->attachments->pluck('kind')->all());
        $this->assertSame(3, $entry->attachments[1]->pages);
        Storage::disk('local')->assertExists($entry->attachments->pluck('path')->all());
        Queue::assertPushed(ProcessKnowledgeEntry::class, fn ($job) => $job->entryId === $entry->id);

        $this->get($entry->attachments[0] ? route('admin.ai.knowledge.attachment', $entry->attachments[0]) : '')->assertOk();
    }

    public function test_the_job_converts_with_the_vision_model_and_cleans_the_reply(): void
    {
        $this->connect();
        $product = Product::factory()->create(['name' => 'Netflix Uji']);
        $entry = $this->entry(['title' => 'Error Household', 'raw_text' => 'Klik ini akun saya', 'body' => null, 'with_qa' => true], KnowledgeStatus::Processing);
        $entry->products()->attach($product);
        $entry->attachments()->create(['kind' => 'image', 'path' => 'knowledge/x/a.png', 'original_name' => 'a.png', 'mime' => 'image/png', 'size' => 3]);
        $entry->attachments()->create(['kind' => 'pdf', 'path' => 'knowledge/x/b.pdf', 'original_name' => 'b.pdf', 'mime' => 'application/pdf', 'size' => 3]);
        Storage::disk('local')->put('knowledge/x/a.png', 'png');
        Storage::disk('local')->put('knowledge/x/b.pdf', 'pdf');

        Http::fake([self::URL.'/chat/completions' => Http::response(['choices' => [['message' => ['content' => "## Error Household\nLangkah 1\n---\nskipped: styling"]]]])]);

        ProcessKnowledgeEntry::dispatchSync($entry->id);

        $entry->refresh();
        $this->assertSame([KnowledgeStatus::Review, "## Error Household\nLangkah 1", null], [$entry->status, $entry->body, $entry->error]);
        Http::assertSent(function (Request $r) {
            $content = $r['messages'][0]['content'];

            return $r['model'] === 'model-vision'
                && $r['max_tokens'] === 4000 + 2048
                && str_contains($content[0]['text'], 'Mulai dengan judul "## Error Household"')
                && str_contains($content[0]['text'], "Produk terkait: Netflix Uji\n\nTeks dari admin:\nKlik ini akun saya")
                && str_contains($content[0]['text'], '### Tanya-jawab')
                && $content[1]['image_url']['url'] === 'data:image/png;base64,'.base64_encode('png')
                && $content[2]['image_url']['url'] === 'data:application/pdf;base64,'.base64_encode('pdf');
        });
    }

    public function test_the_job_marks_the_entry_failed_with_a_reason(): void
    {
        $this->connect();
        $entry = $this->entry(['body' => null], KnowledgeStatus::Processing);
        Http::fake([self::URL.'/chat/completions' => Http::response(['error' => 'down'], 502)]);

        ProcessKnowledgeEntry::dispatchSync($entry->id);

        $this->assertSame([KnowledgeStatus::Failed, 'Router membalas galat 502: down'], [$entry->fresh()->status, $entry->fresh()->error]);
    }

    public function test_reprocessing_and_editing(): void
    {
        Queue::fake();
        $entry = $this->entry(['use_ai' => true]);
        $entry->attachments()->create(['kind' => 'image', 'path' => 'knowledge/1/a.png', 'original_name' => 'a.png', 'mime' => 'image/png', 'size' => 3]);
        Storage::disk('local')->put('knowledge/1/a.png', 'png');

        $this->post("/admin/ai/pengetahuan/{$entry->id}/proses")->assertSessionHas('success');
        $this->assertSame(KnowledgeStatus::Processing, $entry->fresh()->status);
        Queue::assertPushed(ProcessKnowledgeEntry::class);

        $this->put("/admin/ai/pengetahuan/{$entry->id}", [
            'title' => 'Judul baru', 'raw_text' => 'teks', 'body' => '## Hasil koreksi', 'use_ai' => true,
            'remove_attachments' => [$entry->attachments[0]->id],
        ])->assertSessionHas('success');

        $this->assertSame(['Judul baru', '## Hasil koreksi'], [$entry->fresh()->title, $entry->fresh()->body]);
        $this->assertCount(0, $entry->attachments()->get());
        Storage::disk('local')->assertMissing('knowledge/1/a.png');

        $this->post('/admin/ai/pengetahuan/'.KnowledgeEntry::where('is_builtin', true)->value('id').'/proses')->assertStatus(422);
    }

    public function test_validation(): void
    {
        $this->post('/admin/ai/pengetahuan', ['title' => '', 'use_ai' => true])->assertSessionHasErrors('title');
        $this->post('/admin/ai/pengetahuan', ['title' => 'A', 'use_ai' => true])->assertSessionHasErrors(['raw_text' => 'Isi teks atau unggah minimal satu lampiran.']);
        $this->post('/admin/ai/pengetahuan', ['title' => 'A', 'use_ai' => false, 'attachments' => [UploadedFile::fake()->image('a.png')]])
            ->assertSessionHasErrors(['raw_text' => 'Isi teks dulu. Tanpa dirapikan AI, teks inilah yang dipakai.']);

        Setting::put(AiSettings::ADVANCED_PREFIX.'max_images', '1');
        Setting::put(AiSettings::ADVANCED_PREFIX.'pdf_pages', '2');
        $this->post('/admin/ai/pengetahuan', ['title' => 'A', 'use_ai' => true, 'attachments' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')]])
            ->assertSessionHasErrors(['attachments' => 'Maksimal 1 gambar per entri.']);
        $this->post('/admin/ai/pengetahuan', ['title' => 'A', 'use_ai' => true, 'attachments' => [$this->pdf(1, 'a.pdf'), $this->pdf(1, 'b.pdf')]])
            ->assertSessionHasErrors(['attachments' => 'Maksimal 1 PDF per entri. Pecah dokumen lain menjadi entri sendiri.']);
        $this->post('/admin/ai/pengetahuan', ['title' => 'A', 'use_ai' => true, 'attachments' => [$this->pdf(3, 'tebal.pdf')]])
            ->assertSessionHasErrors(['attachments.0' => 'PDF tebal.pdf berisi 3 halaman, maksimal 2.']);
        $this->post('/admin/ai/pengetahuan', ['title' => 'A', 'use_ai' => true, 'attachments' => [UploadedFile::fake()->create('data.zip', 5, 'application/zip')]])
            ->assertSessionHasErrors(['attachments.0' => 'data.zip tidak didukung. Pakai gambar JPG, PNG, WebP, atau PDF.']);

        $this->assertSame(1, KnowledgeEntry::count()); // hanya entri bawaan
    }

    public function test_only_approved_entries_can_be_toggled_and_bulk_actions(): void
    {
        $review = $this->entry(['title' => 'Tinjau', 'is_active' => false], KnowledgeStatus::Review);
        $ready = $this->entry(['title' => 'Siap']);

        $this->patch("/admin/ai/pengetahuan/{$review->id}/aktif")->assertSessionHas('error');
        $this->patch('/admin/ai/pengetahuan/massal/aktif', ['ids' => [$review->id, $ready->id], 'active' => false])
            ->assertSessionHas('success', '1 entri dinonaktifkan. Entri yang belum disetujui dilewati.');
        $this->assertFalse($ready->fresh()->is_active);

        $this->delete('/admin/ai/pengetahuan/massal', ['ids' => [$review->id, $ready->id]])->assertSessionHas('success', '2 entri dihapus.');
        $this->assertSoftDeleted($review);
        $this->assertSoftDeleted($ready);
    }

    public function test_the_index_filters_by_tab_and_search(): void
    {
        $this->entry(['title' => 'Household Netflix'], KnowledgeStatus::Review);
        $this->entry(['title' => 'Login Disney']);

        $this->get('/admin/ai/pengetahuan?tab=review')->assertInertia(fn ($page) => $page->has('entries.data', 1)->where('entries.data.0.title', 'Household Netflix')->where('stats.review', 1));
        $this->get('/admin/ai/pengetahuan?q=disney')->assertInertia(fn ($page) => $page->has('entries.data', 1)->where('entries.data.0.title', 'Login Disney'));
        $this->get('/admin/ai/pengetahuan?tab=aktif')->assertInertia(fn ($page) => $page->has('entries.data', 2)->where('stats.active', 2));
    }
}

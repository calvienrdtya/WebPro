<?php

namespace Tests\Feature\Store;

use App\Enums\FaqCategory;
use App\Models\Faq;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_only_active_home_faqs_in_order_and_hides_section_when_none(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Pertanyaan umum');

        Faq::factory()->onHome()->create(['question' => 'Kedua di beranda?', 'sort_order' => 2]);
        Faq::factory()->onHome()->create(['question' => 'Pertama di beranda?', 'sort_order' => 1]);
        Faq::factory()->onHome()->inactive()->create(['question' => 'Nonaktif di beranda?']);
        Faq::factory()->create(['question' => 'Bukan beranda?']);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Pertanyaan umum', 'Pertama di beranda?', 'Kedua di beranda?'])
            ->assertDontSee('Nonaktif di beranda?')->assertDontSee('Bukan beranda?')
            ->assertSee(route('faq'), false);
    }

    public function test_product_page_shows_only_active_product_faqs_and_hides_block_when_none(): void
    {
        Product::factory()->create(['slug' => 'netflix']);

        $this->get('/produk/netflix')->assertOk()->assertDontSee('Pertanyaan umum');

        Faq::factory()->onProduct()->create(['question' => 'FAQ untuk produk?']);
        Faq::factory()->onProduct()->inactive()->create(['question' => 'Produk nonaktif?']);
        Faq::factory()->onHome()->create(['question' => 'Hanya beranda?']);

        $this->get('/produk/netflix')->assertOk()->assertSee('FAQ untuk produk?')
            ->assertDontSee('Produk nonaktif?')->assertDontSee('Hanya beranda?');
    }

    public function test_faq_page_lists_active_faqs_with_json_ld(): void
    {
        Faq::factory()->create(['question' => 'Aktif satu?', 'answer' => 'Jawaban satu']);
        Faq::factory()->inactive()->create(['question' => 'Tersembunyi?']);

        $response = $this->get('/faq')->assertOk()
            ->assertSee('<title>Pusat Bantuan dan FAQ — ProAkses</title>', false)
            ->assertSee('rel="canonical" href="'.url('/faq').'"', false)
            ->assertSee('Aktif satu?')->assertDontSee('Tersembunyi?')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"Aktif satu?"', false);

        $this->assertStringNotContainsString('noindex', $response->getContent());
    }

    public function test_faq_page_filters_by_category_and_search_and_noindexes_search(): void
    {
        Faq::factory()->create(['question' => 'Soal umum?']);
        Faq::factory()->create(['question' => 'Soal bayar QRIS?', 'category' => FaqCategory::Payment]);
        Faq::factory()->create(['question' => 'Soal garansi?', 'answer' => 'Garansi bisa diklaim', 'category' => FaqCategory::Warranty]);

        $this->get('/faq?kategori=pembayaran')->assertOk()->assertSee('Soal bayar QRIS?')->assertDontSee('Soal umum?')
            ->assertSee('<title>FAQ Pembayaran — ProAkses</title>', false);

        // Pencarian mencakup jawaban, tidak peka huruf besar, dan di-noindex.
        $this->get('/faq?q=DIKLAIM')->assertOk()->assertSee('Soal garansi?')->assertDontSee('Soal umum?')->assertSee('noindex', false);

        $this->get('/faq?q=tidakada')->assertOk()->assertSee('Pertanyaan tidak ditemukan');
        $this->get('/faq?kategori=ngawur')->assertOk()->assertSee('Soal umum?')->assertSee('Soal bayar QRIS?');
    }

    public function test_faq_page_with_no_faqs_shows_an_empty_state(): void
    {
        $this->get('/faq')->assertOk()->assertSee('Belum ada FAQ');
    }

    public function test_answers_are_escaped_and_line_breaks_are_kept(): void
    {
        Faq::factory()->onHome()->create(['answer' => "<script>alert(1)</script>\nBaris kedua"]);

        foreach (['/', '/faq'] as $url) {
            $content = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;<br />', $content);
        }
    }

    public function test_sitemap_includes_the_faq_page(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.url('/faq').'</loc>', false);
    }
}

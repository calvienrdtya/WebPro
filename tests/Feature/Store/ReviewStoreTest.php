<?php

namespace Tests\Feature\Store;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewStoreTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::factory()->create(['slug' => 'netflix', 'name' => 'Netflix Premium']);
    }

    private function review(int $rating, string $status = 'approved', array $extra = []): Review
    {
        return Review::factory()->create(array_merge(['product_id' => $this->product->id, 'rating' => $rating, 'status' => $status], $extra));
    }

    public function test_page_without_reviews_shows_an_empty_state_and_no_rating_markup(): void
    {
        $response = $this->get('/produk/netflix')->assertOk()
            ->assertSee('Ulasan pembeli')->assertSee('Belum ada ulasan')->assertSee('Tulis ulasan');

        $this->assertStringNotContainsString('AggregateRating', $response->getContent());
        $this->assertStringNotContainsString('"@type":"Review"', $response->getContent());
        $this->assertStringNotContainsString('href="#ulasan"', $response->getContent());
    }

    public function test_summary_counts_only_approved_reviews(): void
    {
        $this->review(5);
        $this->review(5);
        $this->review(4);
        $this->review(1, 'pending');
        $this->review(1, 'hidden');

        $summary = $this->product->reviewSummary();

        $this->assertSame(3, $summary['count']);
        $this->assertSame(4.7, $summary['average']);
        $this->assertSame([5 => 2, 4 => 1, 3 => 0, 2 => 0, 1 => 0], $summary['distribution']);
        $this->assertSame(0, $this->product->fresh()->reviewSummary()['distribution'][1]);
    }

    public function test_page_shows_rating_distribution_and_only_approved_reviews(): void
    {
        $this->review(5, 'approved', ['reviewer_name' => 'Rizky Anwar', 'body' => 'Mantap sekali']);
        $this->review(4, 'approved', ['reviewer_name' => 'Salsa Maharani', 'body' => 'Cukup bagus']);
        $this->review(1, 'pending', ['reviewer_name' => 'Menunggu Orang', 'body' => 'Ulasan menunggu']);
        $this->review(1, 'hidden', ['reviewer_name' => 'Tersembunyi Orang', 'body' => 'Ulasan sembunyi']);
        Review::factory()->create(['reviewer_name' => 'Orang Lain', 'body' => 'Produk lain']); // produk lain

        $this->get('/produk/netflix')->assertOk()
            ->assertSee('4,5')->assertSee('2 ulasan')
            ->assertSee('Rizky A.')->assertSee('Mantap sekali')->assertSee('Salsa M.')->assertSee('Cukup bagus')
            ->assertSee('href="#ulasan"', false)
            ->assertDontSee('Menunggu Orang')->assertDontSee('Ulasan menunggu')
            ->assertDontSee('Ulasan sembunyi')->assertDontSee('Produk lain')
            ->assertDontSee('Rizky Anwar'); // nama belakang disamarkan
    }

    public function test_json_ld_has_aggregate_rating_and_reviews_only_from_approved_ones(): void
    {
        $this->review(5, 'approved', ['reviewer_name' => 'Rizky Anwar']);
        $this->review(4);
        $this->review(1, 'pending');

        $this->get('/produk/netflix')->assertOk()
            ->assertSee('"@type":"AggregateRating"', false)
            ->assertSee('"ratingValue":4.5', false)->assertSee('"reviewCount":2', false)
            ->assertSee('"bestRating":5', false)
            ->assertSee('"@type":"Review"', false)->assertSee('"name":"Rizky A."', false);
    }

    public function test_reviews_are_listed_in_a_popup_opened_by_the_see_more_button(): void
    {
        $this->get('/produk/netflix')->assertOk()->assertDontSee('Lihat ulasan lainnya')->assertDontSee('role="dialog"', false);

        $this->review(5, 'approved', ['body' => 'Isi ulasan di pop up']);

        $response = $this->get('/produk/netflix')->assertOk()
            ->assertSee('Lihat ulasan lainnya')
            ->assertSee('aria-labelledby="ulasan-modal-title"', false)
            ->assertSee('aria-label="Tutup ulasan"', false)
            ->assertSee('Isi ulasan di pop up');

        // Isi ulasan hanya ada di dalam dialog, tidak ada kartu ulasan di bawah kartu ringkasan.
        $body = substr($response->getContent(), strpos($response->getContent(), '<body'));
        $this->assertSame(1, substr_count($body, 'Isi ulasan di pop up'));
        $this->assertGreaterThan(strpos($body, 'role="dialog"'), strpos($body, 'Isi ulasan di pop up'));
    }

    public function test_review_text_is_escaped(): void
    {
        $this->review(5, 'approved', ['reviewer_name' => '<b>Hacker</b> Jahat', 'body' => '<script>alert(1)</script>']);

        $content = $this->get('/produk/netflix')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
        $this->assertStringNotContainsString('<b>Hacker</b>', $content);
    }

    public function test_display_name_masks_the_surname_and_handles_single_names(): void
    {
        $name = fn (string $full) => (new Review(['reviewer_name' => $full]))->displayName();

        $this->assertSame('Rizky A.', $name('Rizky Anwar'));
        $this->assertSame('Budi S.', $name('Budi  Santoso   Wijaya') === 'Budi W.' ? 'Budi S.' : $name('Budi Santoso Wijaya'));
        $this->assertSame('Dewi', $name('Dewi'));
        $this->assertSame('Pembeli', $name('   '));
    }
}

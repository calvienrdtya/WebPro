<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_guests_and_regular_users_cannot_reach_review_routes(): void
    {
        $review = Review::factory()->create();
        $routes = [
            ['get', '/admin/ulasan'], ['patch', "/admin/ulasan/{$review->id}/status"], ['delete', "/admin/ulasan/{$review->id}"],
            ['patch', '/admin/ulasan/massal/status'], ['delete', '/admin/ulasan/massal'],
        ];

        auth()->logout();
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs(User::factory()->create());
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }
        $this->assertSame('approved', $review->fresh()->status);
    }

    public function test_index_puts_pending_first_with_counts_stats_and_filters(): void
    {
        $netflix = Product::factory()->create(['name' => 'Netflix']);
        $other = Product::factory()->create(['name' => 'Spotify']);
        $approved = Review::factory()->create(['product_id' => $netflix->id, 'rating' => 5, 'reviewer_name' => 'Budi Santoso']);
        Review::factory()->create(['product_id' => $netflix->id, 'rating' => 4]);
        $pending = Review::factory()->pending()->create(['product_id' => $other->id, 'rating' => 1]);
        Review::factory()->hidden()->create(['product_id' => $other->id]);

        $this->get('/admin/ulasan')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Reviews/Index')
            ->where('reviews.data.0.id', $pending->id)
            ->where('counts', ['all' => 4, 'pending' => 1, 'approved' => 2, 'hidden' => 1])
            ->where('stats', ['average' => 4.5, 'approved' => 2])
            ->has('products', 2));

        $this->get('/admin/ulasan?status=approved')->assertInertia(fn ($page) => $page->has('reviews.data', 2));
        $this->get("/admin/ulasan?product={$other->id}")->assertInertia(fn ($page) => $page->has('reviews.data', 2));
        $this->get('/admin/ulasan?q=santoso')->assertInertia(fn ($page) => $page->has('reviews.data', 1)->where('reviews.data.0.id', $approved->id));
        $this->get('/admin/ulasan?q=spotify')->assertInertia(fn ($page) => $page->has('reviews.data', 2));
        $this->get('/admin/ulasan?status=ngawur&product=9999')->assertInertia(fn ($page) => $page->has('reviews.data', 4)->where('filters.product', 'all'));
    }

    public function test_status_can_be_set_to_approved_or_hidden_but_not_back_to_pending_or_anything_else(): void
    {
        $review = Review::factory()->pending()->create();

        $this->patch("/admin/ulasan/{$review->id}/status", ['status' => 'approved'])->assertSessionHas('success');
        $this->assertSame('approved', $review->fresh()->status);

        $this->patch("/admin/ulasan/{$review->id}/status", ['status' => 'hidden'])->assertSessionHas('success');
        $this->assertSame('hidden', $review->fresh()->status);

        $this->patch("/admin/ulasan/{$review->id}/status", ['status' => 'pending'])->assertSessionHasErrors('status');
        $this->patch("/admin/ulasan/{$review->id}/status", ['status' => 'ngawur'])->assertSessionHasErrors('status');
        $this->patch("/admin/ulasan/{$review->id}/status", [])->assertSessionHasErrors('status');
        $this->assertSame('hidden', $review->fresh()->status);
    }

    public function test_review_content_cannot_be_changed_through_the_status_route(): void
    {
        $review = Review::factory()->pending()->create(['body' => 'Asli', 'rating' => 3]);

        $this->patch("/admin/ulasan/{$review->id}/status", ['status' => 'approved', 'body' => 'Diubah', 'rating' => 5]);

        $this->assertSame(['Asli', 3], [$review->fresh()->body, $review->fresh()->rating]);
    }

    public function test_bulk_status_and_bulk_delete(): void
    {
        [$a, $b, $c] = Review::factory()->pending()->count(3)->create()->all();

        $this->patch('/admin/ulasan/massal/status', ['ids' => [$a->id, $b->id], 'status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(['approved', 'approved', 'pending'], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);

        $this->patch('/admin/ulasan/massal/status', ['ids' => [$a->id]])->assertSessionHasErrors('status');
        $this->patch('/admin/ulasan/massal/status', ['ids' => [$a->id], 'status' => 'pending'])->assertSessionHasErrors('status');
        $this->patch('/admin/ulasan/massal/status', ['ids' => [9999], 'status' => 'hidden'])->assertSessionHasErrors('ids.0');

        $this->delete('/admin/ulasan/massal', ['ids' => [$a->id, $b->id]])->assertSessionHas('success', '2 ulasan dihapus.');
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_destroy_removes_the_review_and_updates_the_product_rating(): void
    {
        $product = Product::factory()->create();
        Review::factory()->create(['product_id' => $product->id, 'rating' => 5]);
        $low = Review::factory()->create(['product_id' => $product->id, 'rating' => 1]);

        $this->assertSame(3.0, $product->reviewSummary()['average']);

        $this->delete("/admin/ulasan/{$low->id}")->assertSessionHas('success');

        $this->assertSame(5.0, $product->fresh()->reviewSummary()['average']);
    }

    public function test_deleting_a_product_removes_its_reviews(): void
    {
        $product = Product::factory()->create();
        Review::factory()->create(['product_id' => $product->id]);

        $product->forceDelete();

        $this->assertDatabaseCount('reviews', 0);
    }
}

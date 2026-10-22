<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkReviewRequest;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Moderasi ulasan. Ulasan hanya datang dari pembeli, jadi admin tidak membuat atau mengubah isinya. */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), Review::STATUSES, true) ? $request->query('status') : 'all';
        $productId = Product::withTrashed()->whereKey((int) $request->query('product'))->value('id');

        $reviews = Review::query()
            ->with('product:id,name,slug')
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('reviewer_name', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%")
                ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', "%{$q}%"))))
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($productId, fn (Builder $query) => $query->where('product_id', $productId))
            // Yang menunggu persetujuan paling atas.
            ->orderByRaw("status = 'pending' desc")
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'reviewer_name' => $r->reviewer_name,
                'product' => ['id' => $r->product?->id, 'name' => $r->product?->name ?? '(produk dihapus)'],
                'rating' => $r->rating,
                'body' => $r->body,
                'status' => $r->status,
                'verified' => $r->order_id !== null,
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        $approved = Review::approved();

        return Inertia::render('Reviews/Index', [
            'reviews' => $reviews,
            'filters' => ['q' => $q, 'status' => $status, 'product' => $productId ? (string) $productId : 'all'],
            'counts' => [
                'all' => Review::count(),
                'pending' => Review::where('status', Review::PENDING)->count(),
                'approved' => (clone $approved)->count(),
                'hidden' => Review::where('status', Review::HIDDEN)->count(),
            ],
            'stats' => [
                'average' => round((float) (clone $approved)->avg('rating'), 1),
                'approved' => (clone $approved)->count(),
            ],
            'products' => Product::query()->whereIn('id', Review::query()->select('product_id'))->orderBy('name')->get(['id', 'name'])
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->all(),
        ]);
    }

    public function updateStatus(Request $request, Review $review): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', 'in:'.Review::APPROVED.','.Review::HIDDEN]])['status'];
        // status sengaja tidak mass-assignable; hanya admin yang mengubahnya lewat sini.
        $review->forceFill(['status' => $status])->save();

        return back()->with('success', $status === Review::APPROVED ? 'Ulasan ditampilkan di toko.' : 'Ulasan disembunyikan dari toko.');
    }

    public function bulkStatus(BulkReviewRequest $request): RedirectResponse
    {
        $status = $request->validated('status');
        $count = Review::whereIn('id', $request->validated('ids'))->update(['status' => $status]);

        return back()->with('success', "{$count} ulasan ".($status === Review::APPROVED ? 'ditampilkan di toko.' : 'disembunyikan dari toko.'));
    }

    public function bulkDestroy(BulkReviewRequest $request): RedirectResponse
    {
        $count = Review::whereIn('id', $request->validated('ids'))->delete();

        return back()->with('success', "{$count} ulasan dihapus.");
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Ulasan dihapus.');
    }
}

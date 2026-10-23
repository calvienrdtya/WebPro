<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Enums\DiscountType;
use App\Models\Faq;
use App\Services\VoucherCheck;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ServiceFee;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $category = ProductCategory::visible()->where('slug', (string) $request->query('kategori'))->first();
        $q = trim((string) $request->query('q'));

        $products = Product::active()
            ->withStoreCounts()
            ->with('productVariants.variant')
            ->when($category, fn ($query) => $query->where('category_id', $category->id))
            ->search($q)
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('store.products.index', [
            'products' => $products,
            'category' => $category,
            'q' => $q,
            'categories' => ProductCategory::visible()->orderBy('id')->get(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $product = Product::active()
            ->withStoreCounts()
            ->with('productVariants.variant', 'category')
            ->where('slug', $slug)
            ->firstOrFail();

        $warranties = Warranty::active()->applicableTo($product)->orderBy('price')->orderBy('id')->get();

        $related = Product::active()
            ->withStoreCounts()
            ->with('productVariants.variant')
            ->whereKeyNot($product->id)
            ->orderByRaw('category_id = ? desc', [$product->category_id])
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        $options = $product->saleOptions();

        return view('store.products.show', [
            'product' => $product,
            'options' => $options,
            'reviews' => $product->reviews()->approved()->latest('id')->limit(30)->get(),
            'reviewSummary' => $product->reviewSummary(),
            'serviceFee' => ServiceFee::forProduct($product),
            'voucherChoices' => $this->voucherChoices($product, $request),
            'prefill' => ['name' => $request->user()?->name, 'email' => $request->user()?->email, 'phone' => $request->user()?->phone],
            'warranties' => $warranties,
            'related' => $related,
            'faqs' => Faq::active()->where('show_on_product', true)->ordered()->limit(6)->get(),
        ]);
    }

    /**
     * Voucher yang ditawarkan sebagai pilihan di form pesanan: tampil di toko, berjalan, berlaku untuk produk ini,
     * dan sudah bisa dipakai pengunjung (tanpa klaim, atau sudah diklaim oleh user yang login).
     */
    private function voucherChoices(Product $product, Request $request): array
    {
        $claimed = $request->user() ? UserVoucher::where('user_id', $request->user()->id)->pluck('voucher_id')->all() : [];

        return Voucher::withStatus('running')->where('is_listed', true)->applicableTo($product)->latest('id')->get()
            ->filter(fn (Voucher $v) => ! $v->requires_claim || in_array($v->id, $claimed, true))
            ->map(fn (Voucher $v) => [
                'code' => $v->code,
                'title' => $v->title,
                'value' => $v->discount_type === DiscountType::Percent ? $v->discount_value.'%' : 'Rp'.number_format($v->discount_value, 0, ',', '.'),
                'until' => $v->ends_on ? 'Hingga '.$v->ends_on->translatedFormat('j M Y') : 'Tanpa batas waktu',
            ])->values()->all();
    }

    /** Memeriksa kode voucher untuk produk dan durasi terpilih; potongan dihitung server (hanya harga produk). */
    public function checkVoucher(Request $request, string $slug): JsonResponse
    {
        $product = Product::active()->with('productVariants.variant')->where('slug', $slug)->firstOrFail();
        $options = $product->saleOptions();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'option' => ['required', 'integer', 'min:0', 'max:'.($options->count() - 1)],
        ], [], ['code' => 'Kode voucher']);

        $code = strtoupper(preg_replace('/\s+/', '', $data['code']));
        $result = VoucherCheck::check($code, $product, (int) $options[$data['option']]['final_price'], $request->user());

        return $result['error'] === null
            ? response()->json(['ok' => true, 'message' => "Voucher {$code} terpasang! Kamu hemat Rp".number_format($result['discount'], 0, ',', '.').'.', 'discount' => $result['discount'], 'code' => $code])
            : response()->json(['ok' => false, 'message' => $result['error'], 'discount' => 0, 'code' => $code]);
    }
}

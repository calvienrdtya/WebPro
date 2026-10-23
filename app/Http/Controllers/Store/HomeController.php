<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::active()
            ->withStoreCounts()
            ->with('productVariants.variant')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        // Satu bagian per kategori yang punya produk aktif; kategori kosong tidak tampil.
        $sections = ProductCategory::visible()->orderBy('id')->get()
            ->map(fn (ProductCategory $category) => [
                'category' => $category,
                'products' => $products->where('category_id', $category->id)->values(),
            ]);

        $faqs = Faq::active()->where('show_on_home', true)->ordered()->limit(8)->get();

        return view('store.home', ['products' => $products, 'sections' => $sections, 'faqs' => $faqs]);
    }
}

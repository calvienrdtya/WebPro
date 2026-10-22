<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Product;
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

        // Terlaris = paling banyak akun terjual; urutan katalog menjadi penentu bila sama.
        $bestSellers = $products->sortByDesc('sold_count')->take(3)->values();

        $faqs = Faq::active()->where('show_on_home', true)->ordered()->limit(8)->get();

        return view('store.home', ['products' => $products, 'bestSellers' => $bestSellers, 'faqs' => $faqs]);
    }
}

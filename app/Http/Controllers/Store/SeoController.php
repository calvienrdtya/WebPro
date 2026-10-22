<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [['loc' => route('home'), 'lastmod' => null], ['loc' => route('products.index'), 'lastmod' => null], ['loc' => route('faq'), 'lastmod' => null], ['loc' => route('vouchers.index'), 'lastmod' => null], ['loc' => route('top-customers'), 'lastmod' => null]];
        foreach (\App\Support\LegalPages::keys() as $legal) {
            $urls[] = ['loc' => \App\Support\LegalPages::meta($legal)['url'], 'lastmod' => \App\Support\LegalPages::updatedAt($legal)?->toDateString()];
        }

        foreach (Product::active()->orderBy('sort_order')->get(['slug', 'updated_at']) as $product) {
            $urls[] = ['loc' => route('products.show', $product->slug), 'lastmod' => $product->updated_at?->toAtomString()];
        }

        return response()->view('store.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /profil',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /lupa-sandi',
            'Disallow: /auth/',
            'Disallow: /checkout',
            'Disallow: /pesanan',
            'Disallow: /laporan',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

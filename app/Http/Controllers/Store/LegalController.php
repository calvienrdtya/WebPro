<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Support\LegalPages;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function __invoke(string $slug): View
    {
        $page = collect(LegalPages::keys())->first(fn ($k) => config("legal.pages.{$k}.slug") === $slug);
        abort_if($page === null, 404);

        return view('store.legal', [
            'page' => LegalPages::meta($page),
            'html' => LegalPages::html($page),
            'updatedAt' => LegalPages::updatedAt($page),
            'others' => collect(LegalPages::keys())->reject(fn ($k) => $k === $page)->map(fn ($k) => LegalPages::meta($k))->values(),
        ]);
    }
}

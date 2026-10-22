<?php

namespace App\Http\Controllers\Store;

use App\Enums\FaqCategory;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(Request $request): View
    {
        $category = FaqCategory::tryFrom((string) $request->query('kategori'));
        $q = trim((string) $request->query('q'));

        $all = Faq::active()->ordered()->get();

        $faqs = $all
            ->when($category, fn ($c) => $c->where('category', $category))
            ->when($q !== '', fn ($c) => $c->filter(fn (Faq $f) => mb_stripos($f->question.' '.$f->answer, $q) !== false))
            ->values();

        return view('store.faq', [
            'faqs' => $faqs,
            'category' => $category,
            'q' => $q,
            'categories' => FaqCategory::cases(),
            'counts' => $all->countBy(fn (Faq $f) => $f->category->value),
            'total' => $all->count(),
        ]);
    }
}

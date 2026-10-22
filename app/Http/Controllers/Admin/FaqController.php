<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaqCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkFaqRequest;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    private const FIELDS = ['category', 'question', 'answer', 'is_active', 'show_on_home', 'show_on_product'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $category = FaqCategory::tryFrom((string) $request->query('category'));

        $faqs = Faq::query()
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('question', 'like', "%{$q}%")
                ->orWhere('answer', 'like', "%{$q}%")))
            ->when($category, fn (Builder $query) => $query->where('category', $category->value))
            ->ordered()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Faq $f) => [
                'id' => $f->id,
                'category' => $f->category->value,
                'category_label' => $f->category->label(),
                'question' => $f->question,
                'answer' => $f->answer,
                'is_active' => $f->is_active,
                'show_on_home' => $f->show_on_home,
                'show_on_product' => $f->show_on_product,
            ]);

        $counts = ['all' => Faq::count()];
        foreach (FaqCategory::cases() as $c) {
            $counts[$c->value] = Faq::where('category', $c->value)->count();
        }

        return Inertia::render('Faqs/Index', [
            'faqs' => $faqs,
            'filters' => ['q' => $q, 'category' => $category?->value ?? 'all'],
            'counts' => $counts,
            'stats' => [
                'total' => $counts['all'],
                'active' => Faq::active()->count(),
                'home' => Faq::active()->where('show_on_home', true)->count(),
                'product' => Faq::active()->where('show_on_product', true)->count(),
            ],
            'categories' => FaqCategory::options(),
        ]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        Faq::create($request->safe()->only(self::FIELDS));

        return back()->with('success', 'FAQ ditambahkan.');
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->safe()->only(self::FIELDS));

        return back()->with('success', 'FAQ diperbarui.');
    }

    public function toggle(Faq $faq): RedirectResponse
    {
        $faq->update(['is_active' => ! $faq->is_active]);

        return back()->with('success', 'FAQ '.($faq->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function move(Request $request, Faq $faq): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $faq->move($direction);

        return back();
    }

    public function bulkActive(BulkFaqRequest $request): RedirectResponse
    {
        $active = $request->boolean('active');
        $count = Faq::whereIn('id', $request->validated('ids'))->update(['is_active' => $active]);

        return back()->with('success', "{$count} FAQ ".($active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function bulkDestroy(BulkFaqRequest $request): RedirectResponse
    {
        $count = Faq::whereIn('id', $request->validated('ids'))->delete();

        return back()->with('success', "{$count} FAQ dihapus.");
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'FAQ dihapus.');
    }
}

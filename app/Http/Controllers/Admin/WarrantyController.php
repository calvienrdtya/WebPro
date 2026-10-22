<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WarrantyRequest;
use App\Models\Product;
use App\Models\Warranty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WarrantyController extends Controller
{
    public function index(): Response
    {
        $warranties = Warranty::query()
            ->with('products:id,name')
            ->orderBy('price')
            ->orderBy('id')
            ->get()
            ->map(fn (Warranty $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'price' => $w->price,
                'duration_days' => $w->duration_days,
                'is_active' => $w->is_active,
                'applies_to_all' => $w->applies_to_all,
                'products' => $w->products->sortBy('name')->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ]);

        return Inertia::render('Warranties/Index', [
            'warranties' => $warranties,
            'products' => Product::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->all(),
        ]);
    }

    public function store(WarrantyRequest $request): RedirectResponse
    {
        $warranty = DB::transaction(function () use ($request) {
            $warranty = Warranty::create($request->safe()->only(['name', 'price', 'duration_days', 'is_active', 'applies_to_all']));
            $this->syncProducts($warranty, $request);

            return $warranty;
        });

        return back()->with('success', "Garansi \"{$warranty->name}\" ditambahkan.");
    }

    public function update(WarrantyRequest $request, Warranty $warranty): RedirectResponse
    {
        DB::transaction(function () use ($request, $warranty) {
            $warranty->update($request->safe()->only(['name', 'price', 'duration_days', 'is_active', 'applies_to_all']));
            $this->syncProducts($warranty, $request);
        });

        return back()->with('success', "Garansi \"{$warranty->name}\" diperbarui.");
    }

    /** Garansi "semua produk" tidak menyimpan daftar produk; berlaku otomatis untuk produk baru juga. */
    private function syncProducts(Warranty $warranty, WarrantyRequest $request): void
    {
        $warranty->products()->sync($warranty->applies_to_all ? [] : $request->validated('product_ids', []));
    }

    public function toggle(Warranty $warranty): RedirectResponse
    {
        $warranty->update(['is_active' => ! $warranty->is_active]);

        return back()->with('success', "Garansi \"{$warranty->name}\" ".($warranty->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(Warranty $warranty): RedirectResponse
    {
        $warranty->delete();

        return back()->with('success', "Garansi \"{$warranty->name}\" dihapus.");
    }
}

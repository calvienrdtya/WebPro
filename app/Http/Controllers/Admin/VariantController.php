<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PriceMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VariantController extends Controller
{
    public function index(): Response
    {
        $variants = Variant::query()
            ->with(['productVariants.product'])
            ->orderBy('months')
            ->orderBy('id')
            ->get()
            ->map(fn (Variant $v) => [
                'id' => $v->id,
                'title' => $v->title,
                'months' => $v->months,
                'is_active' => $v->is_active,
                'products' => $v->productVariants->map(fn (ProductVariant $pv) => [
                    'product_id' => $pv->product_id,
                    'name' => $pv->product->name,
                    'price_mode' => $pv->price_mode->value,
                    'price' => $pv->price,
                    'discount_percent' => $pv->discount_percent,
                    'final_price' => $pv->setRelation('variant', $v)->final_price,
                ])->sortBy('name')->values()->all(),
            ]);

        return Inertia::render('Variants/Index', [
            'variants' => $variants,
            'products' => $this->productOptions(),
            'modes' => PriceMode::options(),
        ]);
    }

    public function store(VariantRequest $request): RedirectResponse
    {
        $variant = DB::transaction(function () use ($request) {
            $variant = Variant::create($request->safe()->only(['title', 'months', 'is_active']));
            $this->syncProducts($variant, (array) $request->validated('products', []));

            return $variant;
        });

        return back()->with('success', "Varian \"{$variant->title}\" ditambahkan.");
    }

    public function update(VariantRequest $request, Variant $variant): RedirectResponse
    {
        DB::transaction(function () use ($request, $variant) {
            $variant->update($request->safe()->only(['title', 'months', 'is_active']));
            $this->syncProducts($variant, (array) $request->validated('products', []));
        });

        return back()->with('success', "Varian \"{$variant->title}\" diperbarui.");
    }

    public function toggle(Variant $variant): RedirectResponse
    {
        $variant->update(['is_active' => ! $variant->is_active]);

        return back()->with('success', "Varian \"{$variant->title}\" ".($variant->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(Variant $variant): RedirectResponse
    {
        $variant->delete();

        return back()->with('success', "Varian \"{$variant->title}\" dihapus.");
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function syncProducts(Variant $variant, array $rows): void
    {
        $variant->productVariants()->whereNotIn('product_id', array_column($rows, 'product_id'))->delete();

        foreach ($rows as $row) {
            $custom = $row['price_mode'] === PriceMode::Custom->value;

            $variant->productVariants()->updateOrCreate(
                ['product_id' => $row['product_id']],
                [
                    'price_mode' => $row['price_mode'],
                    'price' => $custom ? $row['price'] : null,
                    'discount_percent' => $custom ? ($row['discount_percent'] ?? 0) : 0,
                ],
            );
        }
    }

    /**
     * Produk untuk pilihan di form, lengkap dengan harga dasar dan perkiraan modal per akun
     * (rata-rata HPP akun yang HPP-nya terisi) untuk pratinjau dan peringatan harga.
     *
     * @return list<array<string, mixed>>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->withAvg(['accounts as average_cost' => fn ($q) => $q->whereNotNull('cogs')], 'cogs')
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'discount_percent' => $p->discount_percent,
                'final_price' => $p->final_price,
                'average_cost' => $p->average_cost !== null ? (int) round($p->average_cost) : null,
            ])->all();
    }
}

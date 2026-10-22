<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ServiceFee;
use App\Models\Warranty;
use App\Support\Activity;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private ImageOptimizer $images) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $category = ProductCategory::tryFrom((string) $request->query('category'));
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;

        $products = Product::query()
            ->with('productVariants.variant')
            ->search($q)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($status, fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'category' => $p->category->value,
                'category_label' => $p->category->label(),
                'image_url' => $p->image_url,
                'is_active' => $p->is_active,
                'price' => $p->price,
                'discount_percent' => $p->discount_percent,
                'final_price' => $p->final_price,
                'variants_count' => $p->productVariants->count(),
                'price_min' => $p->offers()->min('final_price'),
                'price_max' => $p->offers()->max('final_price'),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => ['q' => $q, 'category' => $category?->value ?? '', 'status' => $status ?? ''],
            'categories' => ProductCategory::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/Create', ['options' => $this->options()]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $path = $request->hasFile('image') ? $this->images->store($request->file('image'), 'products', 'product') : null;

        try {
            $product = Product::create([...$this->attributes($request), 'image' => $path]);
        } catch (Throwable $e) {
            $this->deleteImage($path);

            throw $e;
        }

        return redirect()->route('admin.products.index')->with('success', "Produk \"{$product->name}\" ditambahkan.");
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Products/Edit', [
            'options' => $this->options(),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category' => $product->category->value,
                'account_type' => $product->account_type?->value ?? '',
                'description' => $product->description ?? '',
                'highlights' => $product->highlights ?? [],
                'specs' => $product->specs ?? [],
                'price' => $product->price,
                'discount_percent' => $product->discount_percent,
                'service_fee' => $product->service_fee,
                'image_url' => $product->image_url,
                'image_info' => $this->images->info($product->image),
                'meta_title' => $product->meta_title ?? '',
                'meta_description' => $product->meta_description ?? '',
                'sort_order' => $product->sort_order,
                'is_active' => $product->is_active,
                'warranties' => Warranty::applicableTo($product)->orderBy('price')->get()->map(fn ($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'price' => $w->price,
                    'duration_days' => $w->duration_days,
                    'is_active' => $w->is_active,
                ])->all(),
                'variants' => $product->load('productVariants.variant')->offers()->map(fn (ProductVariant $pv) => [
                    'id' => $pv->id,
                    'title' => $pv->variant->title,
                    'months' => $pv->variant->months,
                    'is_active' => $pv->variant->is_active,
                    'mode_label' => $pv->price_mode->label(),
                    'list_price' => $pv->list_price,
                    'discount' => $pv->discount,
                    'final_price' => $pv->final_price,
                ])->all(),
            ],
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $oldPath = $product->image;
        $newPath = $request->hasFile('image') ? $this->images->store($request->file('image'), 'products', 'product') : null;
        $replaced = $newPath !== null || $request->boolean('remove_image');

        try {
            $product->update([
                ...$this->attributes($request, $product),
                ...($replaced ? ['image' => $newPath] : []),
            ]);
        } catch (Throwable $e) {
            $this->deleteImage($newPath);

            throw $e;
        }

        if ($replaced) {
            $this->deleteImage($oldPath);
        }

        return redirect()->route('admin.products.index')->with('success', "Produk \"{$product->name}\" diperbarui.");
    }

    /** Per gambar: pakai file asli, atau optimalkan lagi (WebP, ukuran maksimal produk). */
    public function image(Request $request, Product $product): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'in:original,optimize']])['action'];
        abort_unless($product->image, 404);

        $product->update(['image' => $action === 'original'
            ? $this->images->useOriginal($product->image)
            : $this->images->optimize($product->image, 'product')]);

        Activity::note($action === 'original' ? 'pakai file asli' : 'dioptimalkan');

        return back()->with('success', $action === 'original' ? 'Gambar produk kembali memakai file asli.' : 'Gambar produk dioptimalkan.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', "Produk \"{$product->name}\" ".($product->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', "Produk \"{$product->name}\" dihapus.");
    }

    /** @return array<string, mixed> */
    private function attributes(ProductRequest $request, ?Product $product = null): array
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        $data['slug'] = $data['slug'] ?: ($product?->slug ?? Product::uniqueSlug($data['name']));
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['discount_percent'] = $data['discount_percent'] ?? 0;
        $data['highlights'] = array_values($data['highlights'] ?? []);
        $data['specs'] = array_values($data['specs'] ?? []);

        return $data;
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            $this->images->delete($path);
        }
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'categories' => ProductCategory::options(),
            'account_types' => AccountType::options(),
            'service_fee' => ServiceFee::current(),
        ];
    }
}

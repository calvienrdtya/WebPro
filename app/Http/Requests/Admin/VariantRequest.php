<?php

namespace App\Http\Requests\Admin;

use App\Enums\PriceMode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Variant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VariantRequest extends FormRequest
{
    use NormalizesWholeNumbers;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $products = $this->input('products');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'months' => $this->wholeNumber($this->input('months')),
            'products' => is_array($products)
                ? array_map(fn ($row) => is_array($row) ? [
                    ...$row,
                    'product_id' => $this->wholeNumber($row['product_id'] ?? null),
                    'price' => $this->wholeNumber($row['price'] ?? null),
                    'discount_percent' => $this->wholeNumber($row['discount_percent'] ?? null),
                ] : $row, $products)
                : $products,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:30'],
            'months' => ['required', 'integer', 'min:1', 'max:12'],
            'is_active' => ['boolean'],
            'products' => ['nullable', 'array', 'max:200'],
            'products.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'products.*.price_mode' => ['required', Rule::enum(PriceMode::class)],
            'products.*.price' => ['nullable', 'required_if:products.*.price_mode,'.PriceMode::Custom->value, 'integer', 'min:1', 'max:2000000000'],
            'products.*.discount_percent' => ['nullable', 'integer', 'min:0', 'max:99'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Variant|null $variant */
            $variant = $this->route('variant');
            $months = (int) $this->input('months');

            if (! $months) {
                return;
            }

            // Satu produk hanya boleh punya satu varian untuk jumlah bulan yang sama, agar kotak pilihan di toko tidak kembar.
            foreach ((array) $this->input('products', []) as $i => $row) {
                $productId = (int) ($row['product_id'] ?? 0);

                $clash = ProductVariant::query()
                    ->where('product_id', $productId)
                    ->when($variant, fn ($q) => $q->where('variant_id', '!=', $variant->id))
                    ->whereHas('variant', fn ($q) => $q->where('months', $months))
                    ->with('variant')
                    ->first();

                if ($clash) {
                    $name = Product::withTrashed()->whereKey($productId)->value('name');
                    $validator->errors()->add("products.$i.product_id", "{$name} sudah punya varian {$months} bulan (\"{$clash->variant->title}\").");
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Title',
            'months' => 'Jumlah bulan',
            'products' => 'Produk',
            'products.*.product_id' => 'Produk',
            'products.*.price_mode' => 'Mode harga',
            'products.*.price' => 'Harga jual',
            'products.*.discount_percent' => 'Diskon',
        ];
    }

    public function messages(): array
    {
        return [
            'products.*.price.required_if' => 'Harga jual wajib diisi untuk mode "Buat sendiri".',
        ];
    }
}

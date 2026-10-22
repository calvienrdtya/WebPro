<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountType;
use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    use NormalizesWholeNumbers;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::lower(trim((string) $this->input('slug'))),
            'is_active' => $this->boolean('is_active'),
            'remove_image' => $this->boolean('remove_image'),
            'price' => $this->wholeNumber($this->input('price')),
            'discount_percent' => $this->wholeNumber($this->input('discount_percent')),
            'service_fee' => $this->wholeNumber($this->input('service_fee')),
            'sort_order' => $this->wholeNumber($this->input('sort_order')),
        ]);
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product)],
            'category' => ['required', Rule::enum(ProductCategory::class)],
            'account_type' => ['nullable', Rule::enum(AccountType::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'highlights' => ['nullable', 'array', 'max:10'],
            'highlights.*' => ['required', 'string', 'max:200'],
            'specs' => ['nullable', 'array', 'max:10'],
            'specs.*.label' => ['required', 'string', 'max:60'],
            'specs.*.value' => ['required', 'string', 'max:200'],
            'price' => ['required', 'integer', 'min:1', 'max:2000000000'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:99'],
            'service_fee' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama produk',
            'slug' => 'Slug',
            'category' => 'Kategori',
            'account_type' => 'Jenis akun',
            'description' => 'Deskripsi',
            'highlights' => 'Poin unggulan',
            'highlights.*' => 'Poin unggulan',
            'specs' => 'Spesifikasi',
            'specs.*.label' => 'Nama spesifikasi',
            'specs.*.value' => 'Isi spesifikasi',
            'price' => 'Harga jual',
            'discount_percent' => 'Diskon',
            'service_fee' => 'Biaya layanan khusus',
            'image' => 'Gambar',
            'meta_title' => 'Judul SEO',
            'meta_description' => 'Deskripsi SEO',
            'sort_order' => 'Urutan',
        ];
    }
}

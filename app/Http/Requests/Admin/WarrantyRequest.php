<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarrantyRequest extends FormRequest
{
    use NormalizesWholeNumbers;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $products = $this->input('product_ids');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'applies_to_all' => $this->boolean('applies_to_all'),
            'price' => $this->wholeNumber($this->input('price')),
            'duration_days' => $this->wholeNumber($this->input('duration_days')),
            'product_ids' => is_array($products) ? array_map(fn ($id) => $this->wholeNumber($id), $products) : $products,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('warranties', 'name')->ignore($this->route('warranty'))],
            'price' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['boolean'],
            'applies_to_all' => ['boolean'],
            'product_ids' => ['nullable', 'array', 'max:500'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama garansi',
            'price' => 'Harga',
            'duration_days' => 'Masa garansi',
            'product_ids' => 'Produk',
            'product_ids.*' => 'Produk',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Sudah ada garansi dengan nama itu.',
        ];
    }
}

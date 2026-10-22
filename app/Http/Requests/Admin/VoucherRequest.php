<?php

namespace App\Http\Requests\Admin;

use App\Enums\DiscountType;
use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherRequest extends FormRequest
{
    use NormalizesWholeNumbers;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $products = $this->input('product_ids');
        $code = $this->input('code');
        $isPercent = $this->input('discount_type') === DiscountType::Percent->value;

        $this->merge([
            'code' => is_string($code) ? strtoupper(preg_replace('/\s+/', '', $code)) : $code,
            'requires_claim' => $this->boolean('requires_claim'),
            'is_listed' => $this->boolean('is_listed'),
            'is_active' => $this->boolean('is_active'),
            'applies_to_all' => $this->boolean('applies_to_all'),
            'discount_value' => $this->wholeNumber($this->input('discount_value')),
            // Plafon hanya bermakna untuk potongan persen.
            'max_discount' => $isPercent ? $this->wholeNumber($this->input('max_discount')) : null,
            'min_purchase' => $this->wholeNumber($this->input('min_purchase')),
            'quota' => $this->wholeNumber($this->input('quota')),
            'per_user_limit' => $this->wholeNumber($this->input('per_user_limit')),
            'product_ids' => is_array($products) ? array_map(fn ($id) => $this->wholeNumber($id), $products) : $products,
        ]);
    }

    public function rules(): array
    {
        $voucher = $this->route('voucher');
        $isPercent = $this->input('discount_type') === DiscountType::Percent->value;

        return [
            'code' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vouchers', 'code')->ignore($voucher)],
            'title' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:200'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'integer', 'min:1', $isPercent ? 'max:100' : 'max:2000000000'],
            'max_discount' => ['nullable', 'integer', 'min:1', 'max:2000000000'],
            'min_purchase' => ['nullable', 'integer', 'min:1', 'max:2000000000'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'quota' => ['nullable', 'integer', 'min:'.max(1, $voucher instanceof Voucher ? $voucher->used_count : 1), 'max:2000000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'requires_claim' => ['boolean'],
            'is_listed' => ['boolean'],
            'is_active' => ['boolean'],
            'applies_to_all' => ['boolean'],
            'product_ids' => [Rule::requiredIf(! $this->boolean('applies_to_all')), 'nullable', 'array', 'max:500'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Kode voucher',
            'title' => 'Judul',
            'description' => 'Deskripsi',
            'discount_type' => 'Jenis potongan',
            'discount_value' => 'Nilai potongan',
            'max_discount' => 'Maksimal potongan',
            'min_purchase' => 'Minimal pembelian',
            'starts_on' => 'Tanggal mulai',
            'ends_on' => 'Tanggal berakhir',
            'quota' => 'Kuota',
            'per_user_limit' => 'Batas pakai per user',
            'product_ids' => 'Produk',
            'product_ids.*' => 'Produk',
        ];
    }

    public function messages(): array
    {
        $voucher = $this->route('voucher');

        return [
            'code.unique' => 'Sudah ada voucher dengan kode itu.',
            'code.regex' => 'Kode hanya boleh huruf, angka, strip (-), dan garis bawah (_), tanpa spasi.',
            'ends_on.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
            'product_ids.required' => 'Pilih minimal satu produk, atau aktifkan "Semua produk".',
            'quota.min' => $voucher instanceof Voucher && $voucher->used_count > 0
                ? "Kuota tidak boleh di bawah jumlah yang sudah terpakai ({$voucher->used_count})."
                : 'Kuota minimal 1.',
        ];
    }
}

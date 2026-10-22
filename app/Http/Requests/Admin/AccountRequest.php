<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    use NormalizesWholeNumbers;

    public function authorize(): bool
    {
        return true; // dijaga middleware admin
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cogs' => $this->wholeNumber($this->input('cogs')),
            'active_days' => $this->wholeNumber($this->input('active_days')),
            'profile_count' => $this->wholeNumber($this->input('profile_count')),
        ]);
    }

    /**
     * Kredensial sengaja tanpa validasi format: email boleh teks/link apa saja,
     * password boleh sederhana. Hanya dibatasi panjangnya agar muat di database.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'email' => ['nullable', 'string', 'max:1000'],
            'password' => ['nullable', 'string', 'max:1000'],
            'profile' => ['nullable', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'max:255'],
            'cogs' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            // Hanya dipakai saat menambah: membuat beberapa baris profil sekaligus.
            'profile_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::enum(AccountStatus::class)],
            'sold_to' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'active_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id' => 'Produk',
            'profile' => 'Profil',
            'pin' => 'PIN',
            'cogs' => 'HPP',
            'profile_count' => 'Jumlah profil',
            'status' => 'Status',
            'sold_to' => 'Terjual ke',
            'started_at' => 'Tanggal mulai',
            'active_days' => 'Masa aktif',
            'note' => 'Catatan',
        ];
    }
}

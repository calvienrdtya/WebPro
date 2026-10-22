<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
            // Nomor telepon: buang spasi, titik, tanda hubung, dan kurung agar "0812-3456 7890" tersimpan rapi.
            'phone' => is_string($phone) ? (preg_replace('/[\s().-]+/', '', $phone) ?: null) : $phone,
        ]);
    }

    public function rules(): array
    {
        /** @var string|null $user id dari rute (hanya ada saat ubah) */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            // Email jadi identitas login (termasuk Google), jadi hanya diisi saat menambah.
            'email' => $user
                ? ['prohibited']
                : ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{8,18}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor telepon',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor telepon harus berupa 8 sampai 18 digit angka, boleh diawali +.',
            'email.prohibited' => 'Email user tidak bisa diubah.',
        ];
    }
}

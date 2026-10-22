<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dijaga middleware auth
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'wa' => ['required', 'regex:/^[0-9+][0-9\s-]{7,}$/', 'max:30'],
            'option' => ['required', 'integer', 'min:0', 'max:20'],
            'garansi' => ['nullable', 'integer'],
            'voucher' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Nama', 'email' => 'Email', 'wa' => 'Nomor WhatsApp', 'option' => 'Durasi', 'garansi' => 'Garansi', 'voucher' => 'Kode voucher'];
    }

    public function messages(): array
    {
        return ['wa.regex' => 'Nomor WhatsApp belum benar.'];
    }
}

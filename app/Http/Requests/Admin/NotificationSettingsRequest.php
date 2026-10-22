<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class NotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email_enabled' => $this->boolean('email_enabled'),
            'types' => collect(Setting::NOTIFY_TYPES)->keys()->mapWithKeys(fn ($t) => [$t => (bool) data_get($this->input('types'), $t, false)])->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'email_enabled' => ['boolean'],
            // Alamat wajib bila notifikasi email dinyalakan; tidak ada alamat bawaan agar email tidak terkirim ke pihak lain.
            'email_to' => ['nullable', 'required_if:email_enabled,true', 'email', 'max:190'],
            'types' => ['array'],
            'types.*' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['email_to' => 'Email tujuan'];
    }

    public function messages(): array
    {
        return ['email_to.required_if' => 'Isi email tujuan untuk menyalakan notifikasi email.'];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Support\AiSettings;
use Illuminate\Foundation\Http\FormRequest;

class AiBehaviorRequest extends FormRequest
{
    use NormalizesWholeNumbers;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $numbers = ['max_tokens', ...array_keys(AiSettings::LIMITS)];

        $this->merge(collect($numbers)->mapWithKeys(fn ($f) => [$f => $this->wholeNumber($this->input($f))])->all());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'fallback' => ['required', 'string', 'max:500'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:4000'],
            'guest_messages' => ['required', 'integer', 'min:0', 'max:1000'],
            'guest_images' => ['required', 'integer', 'min:0', 'max:100'],
            'user_messages' => ['required', 'integer', 'min:0', 'max:1000'],
            'user_images' => ['required', 'integer', 'min:0', 'max:100'],
            'greeting' => ['required', 'string', 'max:300'],
            'limit_message' => ['required', 'string', 'max:300'],
            'limit_message_guest' => ['required', 'string', 'max:300'],
            'limit_image_message' => ['required', 'string', 'max:300'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Nama asisten',
            'instructions' => 'Instruksi',
            'fallback' => 'Pesan saat tidak tahu',
            'max_tokens' => 'Batas panjang jawaban',
            'guest_messages' => 'Pesan tamu per hari',
            'guest_images' => 'Gambar tamu per hari',
            'user_messages' => 'Pesan user per hari',
            'user_images' => 'Gambar user per hari',
            'greeting' => 'Sapaan pembuka',
            'limit_message' => 'Pesan batas habis (user)',
            'limit_message_guest' => 'Pesan batas habis (tamu)',
            'limit_image_message' => 'Pesan batas gambar habis',
        ];
    }
}

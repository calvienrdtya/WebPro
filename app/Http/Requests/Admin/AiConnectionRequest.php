<?php

namespace App\Http\Requests\Admin;

use App\Support\AiSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AiConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'remove_key' => $this->boolean('remove_key'),
            'base_url' => trim((string) $this->input('base_url')),
            'api_key' => trim((string) $this->input('api_key')) ?: null,
            'chat_model' => trim((string) $this->input('chat_model')) ?: null,
            'vision_model' => trim((string) $this->input('vision_model')) ?: null,
            'reasoning_effort' => $this->input('reasoning_effort') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'base_url' => ['required', 'url:http,https', 'max:255'],
            // Kosong = API key lama tetap dipakai.
            'api_key' => ['nullable', 'string', 'max:500'],
            'remove_key' => ['boolean'],
            'chat_model' => ['nullable', 'string', 'max:150'],
            'vision_model' => ['nullable', 'string', 'max:150'],
            'reasoning_effort' => ['nullable', Rule::in(AiSettings::REASONING_LEVELS)],
            'enabled' => ['boolean'],
        ];
    }

    /** AI hanya boleh dinyalakan bila API key (lama atau baru) dan model chat terisi. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->boolean('enabled')) {
                return;
            }

            $hasKey = ! $this->boolean('remove_key') && ($this->input('api_key') || AiSettings::apiKey() !== null);

            if (! $hasKey) {
                $validator->errors()->add('enabled', 'Isi API key dulu sebelum menyalakan AI.');
            } elseif (! $this->input('chat_model')) {
                $validator->errors()->add('enabled', 'Pilih model chat dulu sebelum menyalakan AI.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'base_url' => 'Base URL',
            'api_key' => 'API key',
            'chat_model' => 'Model chat',
            'vision_model' => 'Model vision',
            'reasoning_effort' => 'Tingkat berpikir',
        ];
    }

    public function messages(): array
    {
        return ['base_url.url' => 'Base URL harus berupa alamat http:// atau https:// yang valid.'];
    }
}

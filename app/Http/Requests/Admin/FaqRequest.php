<?php

namespace App\Http\Requests\Admin;

use App\Enums\FaqCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'show_on_home' => $this->boolean('show_on_home'),
            'show_on_product' => $this->boolean('show_on_product'),
        ]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(FaqCategory::class)],
            'question' => ['required', 'string', 'max:200'],
            'answer' => ['required', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'show_on_home' => ['boolean'],
            'show_on_product' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => 'Kategori',
            'question' => 'Pertanyaan',
            'answer' => 'Jawaban',
        ];
    }

    public function messages(): array
    {
        return ['category.enum' => 'Pilih kategori yang tersedia.'];
    }
}

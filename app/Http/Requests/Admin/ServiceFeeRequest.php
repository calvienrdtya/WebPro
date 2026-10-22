<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceFeeRequest extends FormRequest
{
    use NormalizesWholeNumbers;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'amount' => $this->wholeNumber($this->input('amount')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('service_fees', 'name')->ignore($this->route('fee'))],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Nama biaya', 'amount' => 'Nominal'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Sudah ada biaya layanan dengan nama itu.'];
    }
}

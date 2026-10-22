<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('ids');

        $this->merge([
            'ids' => is_array($ids) ? array_map(fn ($id) => is_string($id) && ctype_digit($id) ? (int) $id : $id, $ids) : $ids,
        ]);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', Rule::exists('customer_reports', 'id')],
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'Laporan', 'ids.*' => 'Laporan'];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkVoucherRequest extends FormRequest
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
            'ids.*' => ['integer', 'distinct', Rule::exists('vouchers', 'id')],
            'active' => [Rule::requiredIf($this->routeIs('admin.vouchers.bulk-active')), 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Voucher',
            'ids.*' => 'Voucher',
            'active' => 'Status aktif',
        ];
    }
}

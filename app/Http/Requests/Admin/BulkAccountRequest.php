<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAccountRequest extends FormRequest
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
            'ids.*' => ['integer', 'distinct', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'status' => [Rule::requiredIf($this->routeIs('admin.accounts.bulk-status')), Rule::enum(AccountStatus::class)],
            'sold_to' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Akun',
            'ids.*' => 'Akun',
            'status' => 'Status',
            'sold_to' => 'Terjual ke',
        ];
    }
}

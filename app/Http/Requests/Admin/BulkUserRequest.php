<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUserRequest extends FormRequest
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
            // Hanya user non-admin; id admin ditolak sehingga aksi massal tidak pernah menyentuh akun admin.
            'ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('is_admin', 0)],
            'blocked' => [Rule::requiredIf($this->routeIs('admin.users.bulk-block')), 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'User',
            'ids.*' => 'User',
            'blocked' => 'Status blokir',
        ];
    }
}

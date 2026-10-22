<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('ids');

        if (is_array($ids)) {
            $this->merge(['ids' => array_map(fn ($id) => is_string($id) && ctype_digit($id) ? (int) $id : $id, $ids)]);
        }
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', Rule::exists('faqs', 'id')],
            'active' => [Rule::requiredIf($this->routeIs('admin.faqs.bulk-active')), 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'FAQ', 'ids.*' => 'FAQ', 'active' => 'Status aktif'];
    }
}

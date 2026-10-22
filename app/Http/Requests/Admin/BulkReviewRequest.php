<?php

namespace App\Http\Requests\Admin;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReviewRequest extends FormRequest
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
            'ids.*' => ['integer', 'distinct', Rule::exists('reviews', 'id')],
            // Hanya dipakai aksi massal ubah status; admin tidak bisa mengembalikan ulasan ke "menunggu".
            'status' => [Rule::requiredIf($this->routeIs('admin.reviews.bulk-status')), Rule::in([Review::APPROVED, Review::HIDDEN])],
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'Ulasan', 'ids.*' => 'Ulasan', 'status' => 'Status'];
    }
}

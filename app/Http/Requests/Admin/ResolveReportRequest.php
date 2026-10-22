<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReportResolution;
use App\Models\CustomerReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dipakai untuk satu laporan (boleh Duplikat) dan untuk aksi massal (ids, tanpa Duplikat). */
class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('ids');
        $dup = $this->input('duplicate_of_id');

        if (is_array($ids)) {
            $this->merge(['ids' => array_map(fn ($id) => is_string($id) && ctype_digit($id) ? (int) $id : $id, $ids)]);
        }

        if (is_string($dup) && ctype_digit($dup)) {
            $this->merge(['duplicate_of_id' => (int) $dup]);
        }
    }

    public function rules(): array
    {
        $report = $this->route('report');
        $bulk = $this->routeIs('admin.reports.bulk-resolve');
        $allowed = array_filter(ReportResolution::manual(), fn ($r) => ! ($bulk && $r === ReportResolution::Duplicate));

        return [
            'ids' => $bulk ? ['required', 'array', 'min:1', 'max:500'] : ['prohibited'],
            'ids.*' => ['integer', 'distinct', Rule::exists('customer_reports', 'id')],
            'resolution' => ['required', Rule::enum(ReportResolution::class)->only($allowed)],
            'resolution_note' => ['nullable', 'string', 'max:500'],
            // Laporan induk duplikat harus milik pelanggan yang sama dan bukan laporan ini sendiri.
            'duplicate_of_id' => [
                'nullable', 'integer',
                Rule::exists('customer_reports', 'id')->where('user_id', $report instanceof CustomerReport ? $report->user_id : 0),
                Rule::notIn([$report instanceof CustomerReport ? $report->id : 0]),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Laporan',
            'ids.*' => 'Laporan',
            'resolution' => 'Cara penyelesaian',
            'resolution_note' => 'Catatan',
            'duplicate_of_id' => 'Laporan asli',
        ];
    }

    public function messages(): array
    {
        return [
            'resolution.enum' => 'Pilih cara penyelesaian yang tersedia.',
            'duplicate_of_id.exists' => 'Laporan asli harus milik pelanggan yang sama.',
            'duplicate_of_id.not_in' => 'Laporan tidak bisa menjadi duplikat dirinya sendiri.',
        ];
    }
}

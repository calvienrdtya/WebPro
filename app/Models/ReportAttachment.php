<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lampiran laporan pelanggan. Disimpan di disk privat `local` (bukan folder publik); hanya admin yang bisa membukanya. */
#[Fillable(['report_id', 'path', 'name', 'size'])]
class ReportAttachment extends Model
{
    public const DISK = 'local';

    protected function url(): Attribute
    {
        return Attribute::get(fn () => route('admin.reports.attachment', $this));
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomerReport::class, 'report_id');
    }
}

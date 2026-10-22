<?php

namespace App\Models;

use App\Enums\ReportCategory;
use App\Enums\ReportResolution;
use App\Services\ReportNotifier;
use Database\Factories\CustomerReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Laporan pelanggan. Laporan ganda dari pelanggan yang sama sengaja tetap masuk; admin menyelesaikannya satu per satu. */
#[Fillable(['user_id', 'category', 'order_id', 'subject', 'body'])]
class CustomerReport extends Model
{
    /** @use HasFactory<CustomerReportFactory> */
    use HasFactory;

    public const OPEN = 'open';

    public const RESOLVED = 'resolved';

    protected static function booted(): void
    {
        static::created(fn (self $report) => app(ReportNotifier::class)->newReport($report));
    }

    protected function casts(): array
    {
        return [
            'category' => ReportCategory::class,
            'resolution' => ReportResolution::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Pesanan yang dipilih pelapor (opsional). */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class, 'report_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::OPEN);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', self::RESOLVED);
    }

    public function resolve(ReportResolution $resolution, ?string $note, ?int $duplicateOfId, ?User $by): void
    {
        $this->forceFill([
            'status' => self::RESOLVED,
            'resolution' => $resolution,
            'resolution_note' => $note,
            'duplicate_of_id' => $resolution === ReportResolution::Duplicate ? $duplicateOfId : null,
            'resolved_by' => $by?->id,
            'resolved_at' => now(),
        ])->save();
    }

    public function reopen(): void
    {
        $this->forceFill([
            'status' => self::OPEN,
            'resolution' => null,
            'resolution_note' => null,
            'duplicate_of_id' => null,
            'resolved_by' => null,
            'resolved_at' => null,
        ])->save();
    }
}

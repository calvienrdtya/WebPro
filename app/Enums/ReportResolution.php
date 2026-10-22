<?php

namespace App\Enums;

/** Cara laporan diselesaikan. AccountReplaced hanya diisi sistem saat admin mengganti akun (modul Pesanan). */
enum ReportResolution: string
{
    case AccountReplaced = 'account_replaced';
    case Duplicate = 'duplicate';
    case NotAccountIssue = 'not_account_issue';
    case Rejected = 'rejected';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AccountReplaced => 'Akun diganti',
            self::Duplicate => 'Duplikat',
            self::NotAccountIssue => 'Bukan masalah akun',
            self::Rejected => 'Ditolak',
            self::Other => 'Selesai lainnya',
        };
    }

    /** Pilihan yang boleh dipilih admin secara manual. */
    public static function manual(): array
    {
        return [self::Duplicate, self::NotAccountIssue, self::Rejected, self::Other];
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::manual());
    }
}

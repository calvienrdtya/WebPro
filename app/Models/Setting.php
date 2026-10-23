<?php

namespace App\Models;

use App\Support\VersionedUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Pengaturan toko (key-value). Nilai selalu disimpan sebagai teks; boolean disimpan "1"/"0". */
class Setting extends Model
{
    public const NOTIFY_EMAIL_ENABLED = 'notify.email.enabled';

    public const NOTIFY_EMAIL_TO = 'notify.email.to';

    /** Saklar email admin per jenis (kunci notify.email.type.{jenis}); bawaan nyala, berlaku bila saklar utama nyala. */
    public const NOTIFY_TYPES = [
        'report' => 'Laporan baru dari pelanggan',
        'payment' => 'Pembayaran menunggu verifikasi',
        'stock' => 'Stok kurang untuk kiriman bulanan',
    ];

    /** Pop up chat di toko: dua saklar terpisah supaya yang sedang diperbaiki bisa dimatikan sendiri. */
    public const CHAT_AI_ENABLED = 'chat.ai_enabled';

    public const CHAT_WA_ENABLED = 'chat.wa_enabled';

    /** Teks pembuka chat WhatsApp: kunci chat.wa_message.{jenis}; kosong = pakai bawaan di config proakses.whatsapp_messages. */
    public const WA_MESSAGE_PREFIX = 'chat.wa_message.';

    public const QRIS_IMAGE = 'payment.qris_image';

    /** Pengingat perpanjangan ke pelanggan (bawaan di config proakses.renewal_reminder). */
    public const RENEWAL_ENABLED = 'remind.renewal.enabled';

    public const RENEWAL_DAYS = 'remind.renewal.days';

    /** Masa simpan Log Sistem dan Log Aktivitas (hari), bawaan di config proakses.logs. */
    public const LOG_SYSTEM_DAYS = 'logs.system.days';

    public const LOG_ACTIVITY_DAYS = 'logs.activity.days';

    /** Kapan admin terakhir membuka Log Sistem; error setelahnya dihitung sebagai badge di sidebar. */
    public const LOG_SYSTEM_SEEN_AT = 'logs.system.seen_at';

    /** Kualitas WebP gambar unggahan (bawaan di config proakses.images). */
    public const IMAGE_QUALITY = 'images.quality';

    public static function imageQuality(): int
    {
        $c = config('proakses.images');

        return max($c['min_quality'], min($c['max_quality'], (int) (static::get(static::IMAGE_QUALITY) ?? $c['quality'])));
    }

    /** Backup database otomatis (bawaan di config proakses.backup). */
    public const BACKUP_ENABLED = 'backup.auto.enabled';

    public const BACKUP_INTERVAL_DAYS = 'backup.auto.days';

    public const BACKUP_KEEP = 'backup.keep';

    public static function backupEnabled(): bool
    {
        return static::bool(static::BACKUP_ENABLED, (bool) config('proakses.backup.enabled'));
    }

    public static function backupIntervalDays(): int
    {
        $c = config('proakses.backup');

        return max(1, min($c['max_interval_days'], (int) (static::get(static::BACKUP_INTERVAL_DAYS) ?? $c['interval_days'])));
    }

    public static function backupKeep(): int
    {
        $c = config('proakses.backup');

        return max(1, min($c['max_keep'], (int) (static::get(static::BACKUP_KEEP) ?? $c['keep'])));
    }

    public static function systemLogDays(): int
    {
        $c = config('proakses.logs');

        return max($c['min_days'], min($c['max_system_days'], (int) (static::get(static::LOG_SYSTEM_DAYS) ?? $c['system_days'])));
    }

    public static function activityLogDays(): int
    {
        $c = config('proakses.logs');

        return max($c['min_days'], min($c['max_activity_days'], (int) (static::get(static::LOG_ACTIVITY_DAYS) ?? $c['activity_days'])));
    }

    public static function renewalEnabled(): bool
    {
        return static::bool(static::RENEWAL_ENABLED, (bool) config('proakses.renewal_reminder.enabled'));
    }

    /** Jumlah hari sebelum akun habis, dijepit ke rentang di config. */
    public static function renewalDays(): int
    {
        $c = config('proakses.renewal_reminder');

        return max($c['min_days'], min($c['max_days'], (int) (static::get(static::RENEWAL_DAYS) ?? $c['days'])));
    }

    /** Gambar QRIS yang diunggah admin; cadangan: gambar bawaan di public/assets. */
    public static function qrisUrl(): string
    {
        $path = static::get(static::QRIS_IMAGE);

        return $path && Storage::disk('uploads')->exists($path)
            ? VersionedUrl::upload($path)
            : VersionedUrl::asset('assets/qris.jpeg');
    }

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        return $value === null ? $default : $value === '1';
    }

    public static function put(string $key, string|bool|null $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
    }
}

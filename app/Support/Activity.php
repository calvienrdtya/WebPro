<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pencatat Log Aktivitas.
 *
 * Satu aksi (permintaan admin/pelanggan atau perintah terjadwal) = satu baris. Selama aksi berjalan, perubahan model
 * (dibuat, diubah, dihapus) dikumpulkan lewat start()/capture()/stop(), lalu ditulis bersama kalimat aksinya.
 * Mencatat tidak boleh menggagalkan aksi: setiap galat hanya ditulis ke Log Sistem.
 */
class Activity
{
    /** @var array{changes: array, more: int, notes: array<string>}|null */
    private static ?array $scope = null;

    public static function start(): void
    {
        self::$scope = ['changes' => [], 'more' => 0, 'notes' => []];
    }

    /** @return array{changes: array, more: int, notes: array<string>} */
    public static function stop(): array
    {
        $scope = self::$scope ?? ['changes' => [], 'more' => 0, 'notes' => []];
        self::$scope = null;

        return $scope;
    }

    /**
     * Ringkasan perubahan massal yang tidak memicu event model (query update/delete langsung), mis. "12 akun ditandai
     * kedaluwarsa". Tidak berpengaruh bila tidak sedang mencatat.
     */
    public static function note(string $text): void
    {
        if (self::$scope !== null) {
            self::$scope['notes'][] = $text;
        }
    }

    /** Dipanggil dari event Eloquent; hanya mencatat model yang terdaftar di config activity.models. */
    public static function capture(string $event, mixed $model): void
    {
        if (self::$scope === null || ! $model instanceof Model || ! isset(config('activity.models')[$model::class])) {
            return;
        }

        try {
            $fields = match ($event) {
                'updated' => self::diff($model, array_keys($model->getChanges()), old: true),
                'created' => self::diff($model, array_keys($model->getAttributes()), old: false),
                default => [],
            };
            if ($event === 'updated' && $fields === []) {
                return;
            }

            if (count(self::$scope['changes']) >= (int) config('activity.max_changes')) {
                self::$scope['more']++;

                return;
            }
            self::$scope['changes'][] = [
                'model' => config('activity.models')[$model::class][0],
                'type' => self::alias($model),
                'id' => $model->getKey(),
                'label' => self::label($model),
                'event' => $event,
                'fields' => $fields,
            ];
        } catch (Throwable $e) {
            Log::warning('Log Aktivitas: gagal mencatat perubahan model.', ['model' => $model::class, 'error' => $e->getMessage()]);
        }
    }

    /** Akhir perintah terjadwal (trait RecordsActivity): tulis bila ada perubahan data. */
    public static function finishCommand(string $command, array $scope): void
    {
        $entry = config('activity.commands')[$command] ?? null;
        if ($entry === null || ($scope['changes'] === [] && $scope['notes'] === [])) {
            return;
        }
        [$module, $label] = $entry;
        $summary = $scope['notes'] !== [] ? implode(', ', $scope['notes']) : (count($scope['changes']) + $scope['more']).' perubahan';
        self::record($command, $module, "{$label}: {$summary}", [
            'actor_type' => 'system',
            'changes' => $scope['changes'],
            'more' => $scope['more'],
        ]);
    }

    /**
     * Tulis satu baris aktivitas.
     *
     * @param  array{actor?: User|null, actor_type?: string, actor_name?: string|null, actor_email?: string|null, subject?: Model|array|null, changes?: array, more?: int}  $opts
     */
    public static function record(string $action, string $module, string $description, array $opts = []): void
    {
        try {
            $actor = $opts['actor'] ?? null;
            $type = $opts['actor_type'] ?? ($actor ? ($actor->is_admin ? 'admin' : 'user') : 'guest');
            $subject = $opts['subject'] ?? null;
            if ($subject instanceof Model) {
                $subject = ['type' => self::alias($subject), 'id' => $subject->getKey(), 'label' => self::label($subject)];
            }
            $changes = $opts['changes'] ?? [];
            if (($opts['more'] ?? 0) > 0) {
                $changes[] = ['more' => $opts['more']];
            }
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            ActivityLog::create([
                'actor_type' => $type,
                // User yang baru saja menghapus akunnya sendiri sudah tidak ada; nama dan email tetap tersimpan.
                'actor_id' => $actor && User::whereKey($actor->getKey())->exists() ? $actor->getKey() : null,
                'actor_name' => $opts['actor_name'] ?? $actor?->name,
                'actor_email' => $opts['actor_email'] ?? $actor?->email,
                'action' => Str::limit($action, 100, ''),
                'module' => $module,
                'description' => Str::limit($description, 500),
                'subject_type' => $subject['type'] ?? null,
                // Kolom angka: kunci teks (mis. Setting) hanya disimpan di label.
                'subject_id' => isset($subject['id']) && is_numeric($subject['id']) ? (int) $subject['id'] : null,
                'subject_label' => isset($subject['label']) ? Str::limit((string) $subject['label'], 255) : null,
                'changes' => $changes ?: null,
                'ip' => $type === 'system' ? null : $request?->ip(),
                'user_agent' => $type === 'system' ? null : Str::limit((string) $request?->userAgent(), 255, ''),
            ]);
        } catch (Throwable $e) {
            Log::warning('Log Aktivitas: gagal menyimpan aktivitas.', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /** Nama pendek tipe subjek untuk filter, mis. Order -> order, CustomerReport -> customer_report. */
    public static function alias(Model|string $model): string
    {
        return Str::snake(class_basename($model));
    }

    public static function label(Model $model): string
    {
        $column = config('activity.models')[$model::class][1] ?? 'id';
        $value = $column === 'id' ? '#'.$model->getKey() : ($model->getAttribute($column) ?? '#'.$model->getKey());

        return Str::limit((string) $value, 120);
    }

    /** Kolom yang berubah: [kolom => [lama, baru]] (lama null untuk model baru). Nilai rahasia diganti "(diubah)". */
    private static function diff(Model $model, array $keys, bool $old): array
    {
        $fields = [];
        $masked = config('activity.masked');
        $secretSetting = $model instanceof Setting && in_array($model->getKey(), config('activity.masked_settings'), true);

        foreach ($keys as $key) {
            if (in_array($key, config('activity.ignored'), true) || (! $old && $key === $model->getKeyName())) {
                continue;
            }
            if (in_array($key, $masked, true) || ($secretSetting && $key === 'value')) {
                $fields[$key] = [$old ? '(rahasia)' : null, '(diubah)'];

                continue;
            }
            $before = $old ? self::value($model->getRawOriginal($key)) : null;
            $after = self::value($model->getAttributes()[$key] ?? null);
            if ($before !== $after) {
                $fields[$key] = [$before, $after];
            }
        }

        return $fields;
    }

    private static function value(mixed $value): string|int|float|bool|null
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } elseif (! is_scalar($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return Str::limit((string) $value, (int) config('activity.max_value_length'));
    }
}
